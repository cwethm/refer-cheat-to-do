<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Http\Exception\ValidationException;
use App\Model\Entity\Record;
use App\Model\Entity\Tag;
use App\Model\Table\RecordsTable;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ConflictException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Owner-only records of discussions and events, listed as a timeline (Slice 27).
 *
 * The tag attach and detach actions repeat the reference-card ones on purpose: each owned type
 * keeps its own join table and activity subject, and the duplication is small (Slice 27 contract).
 */
class RecordsController extends AppController
{
    /**
     * Longest accepted search text, so a search stays a bounded, escaped substring match.
     */
    private const MAX_SEARCH_CHARS = 255;

    /**
     * Columns matched by `q`.
     *
     * @var list<string>
     */
    private const SEARCH_FIELDS = ['title', 'summary', 'participants', 'details'];

    /**
     * An ISO 8601 date and time with a timezone designator, e.g. `2026-10-11T09:30:00Z` or
     * `2026-10-11T09:30+02:00`. Seconds and fractions are optional.
     */
    private const TIMESTAMP_PATTERN =
        '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2})(?:\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})$/';

    /**
     * A calendar date, used by the timeline's date-range filter.
     */
    private const DATE_PATTERN = '/^(\d{4})-(\d{2})-(\d{2})$/';

    /**
     * The caller's timeline, newest first, filtered by kind, tag, date range and text.
     */
    public function index(): Response
    {
        $userId = $this->requireUserId();
        $search = $this->readSearchFilter();
        $kind = $this->readKindFilter();
        $tagId = $this->readPositiveIdFilter('tag');
        $from = $this->readDateBound('from', false);
        $to = $this->readDateBound('to', true);
        if ($from !== null && $to !== null && $from > $to) {
            throw new BadRequestException('The `from` date must not be after the `to` date.');
        }
        $page = $this->readPositiveQueryInt('page', 1);
        $limit = min($this->readPositiveQueryInt('limit', 20), 100);

        /** @var \App\Model\Table\RecordsTable $records */
        $records = $this->fetchTable('Records');
        $query = $records->find('withTags')->where(['Records.user_id' => $userId]);
        if ($search !== null) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $conditions = [];
            foreach (self::SEARCH_FIELDS as $field) {
                $conditions[] = ['Records.' . $field . ' ILIKE' => $like];
            }
            $query->where(['OR' => $conditions]);
        }
        if ($kind !== null) {
            $query->where(['Records.kind' => $kind]);
        }
        if ($tagId !== null) {
            $query->matching('Tags', function ($q) use ($tagId, $userId) {
                return $q->where(['Tags.id' => $tagId, 'Tags.user_id' => $userId]);
            });
        }
        if ($from !== null) {
            $query->where(['Records.occurred_at >=' => $from]);
        }
        if ($to !== null) {
            $query->where(['Records.occurred_at <=' => $to]);
        }

        $total = (clone $query)->count();
        $items = [];
        foreach (
            $query->orderBy(['Records.occurred_at' => 'DESC', 'Records.id' => 'DESC'])
                ->limit($limit)
                ->offset(($page - 1) * $limit)
                ->all() as $record
        ) {
            /** @var \App\Model\Entity\Record $record */
            $items[] = $record->toApiArray();
        }

        return $this->respond(
            ['items' => $items],
            ['pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]],
        );
    }

    /**
     * Create a record owned by the authenticated user. Only a title is required: the kind
     * defaults to a discussion and `occurred_at` to now.
     */
    public function add(): Response
    {
        $userId = $this->requireUserId();
        $data = $this->readBody() + ['kind' => RecordsTable::KIND_DISCUSSION];
        $data['occurred_at'] = array_key_exists('occurred_at', $data)
            ? $this->parseOccurredAt($data['occurred_at'])
            : new DateTime(DateTime::now('UTC')->format('Y-m-d H:i:s'), 'UTC');
        /** @var \App\Model\Table\RecordsTable $records */
        $records = $this->fetchTable('Records');
        /** @var \App\Model\Entity\Record $record */
        $record = $records->newEntity($data, ['fields' => RecordsTable::WRITABLE_FIELDS]);
        $record->set('user_id', $userId);
        if ($record->hasErrors() || !$records->save($record)) {
            throw new ValidationException('Invalid record payload.');
        }

        $this->activity()->record($userId, 'record.created', 'record', (int)$record->id);

        $created = $this->fetchOwnedRecordOrFail((string)$record->id, $userId);

        return $this->respond(['record' => $created->toApiArray()], [], 201);
    }

    /**
     * Read one of the caller's records.
     */
    public function view(string $id): Response
    {
        $userId = $this->requireUserId();

        return $this->respond(['record' => $this->fetchOwnedRecordOrFail($id, $userId)->toApiArray()]);
    }

    /**
     * Update one of the caller's records.
     */
    public function edit(string $id): Response
    {
        $userId = $this->requireUserId();
        $record = $this->fetchOwnedRecordOrFail($id, $userId);
        $data = $this->readBody();
        if (array_key_exists('occurred_at', $data)) {
            $data['occurred_at'] = $this->parseOccurredAt($data['occurred_at']);
        }
        /** @var \App\Model\Table\RecordsTable $records */
        $records = $this->fetchTable('Records');
        $record = $records->patchEntity($record, $data, ['fields' => RecordsTable::WRITABLE_FIELDS]);
        if ($record->hasErrors() || !$records->save($record)) {
            throw new ValidationException('Invalid record payload.');
        }

        $this->activity()->record(
            $userId,
            'record.updated',
            'record',
            (int)$record->id,
            ['fields' => array_values(array_intersect(array_keys($data), RecordsTable::WRITABLE_FIELDS))],
        );

        return $this->respond(['record' => $this->fetchOwnedRecordOrFail($id, $userId)->toApiArray()]);
    }

    /**
     * Permanently delete a record and its tag joins, never a tag.
     */
    public function delete(string $id): Response
    {
        $userId = $this->requireUserId();
        $record = $this->fetchOwnedRecordOrFail($id, $userId);
        $recordId = (int)$record->id;

        if (!$this->fetchTable('Records')->delete($record)) {
            throw new InternalErrorException('Unable to delete record.');
        }

        $this->activity()->record($userId, 'record.deleted', 'record', $recordId);

        return $this->respond(['message' => 'Record deleted.']);
    }

    /**
     * Attach one of the caller's tags to one of the caller's records.
     */
    public function attachTag(string $id, string $tagId): Response
    {
        $userId = $this->requireUserId();
        $record = $this->fetchOwnedRecordOrFail($id, $userId);
        $tag = $this->fetchOwnedTagOrFail($tagId, $userId);

        $inserted = $this->fetchTable('RecordsTags')->getConnection()->execute(
            'INSERT INTO records_tags (record_id, tag_id) VALUES (?, ?) ON CONFLICT DO NOTHING',
            [(int)$record->id, (int)$tag->id],
        )->rowCount();
        if ($inserted < 1) {
            throw new ConflictException('Tag is already attached to this record.');
        }

        $this->activity()->record($userId, 'record.tag_attached', 'record', (int)$record->id, [
            'tag_id' => (int)$tag->id,
        ]);

        return $this->respond([
            'record_id' => (int)$record->id,
            'tag_id' => (int)$tag->id,
            'message' => 'Tag attached.',
        ], [], 201);
    }

    /**
     * Detach one of the caller's tags from one of the caller's records.
     */
    public function detachTag(string $id, string $tagId): Response
    {
        $userId = $this->requireUserId();
        $record = $this->fetchOwnedRecordOrFail($id, $userId);
        $tag = $this->fetchOwnedTagOrFail($tagId, $userId);

        $deleted = $this->fetchTable('RecordsTags')->deleteAll([
            'record_id' => (int)$record->id,
            'tag_id' => (int)$tag->id,
        ]);
        if ($deleted < 1) {
            throw new NotFoundException('Tag attachment not found.');
        }

        $this->activity()->record($userId, 'record.tag_detached', 'record', (int)$record->id, [
            'tag_id' => (int)$tag->id,
        ]);

        return $this->respond([
            'record_id' => (int)$record->id,
            'tag_id' => (int)$tag->id,
            'message' => 'Tag detached.',
        ]);
    }

    /**
     * Resolve a record owned by the caller; another user's record and a missing one are both `404`.
     */
    private function fetchOwnedRecordOrFail(string $id, int $userId): Record
    {
        if (!ctype_digit($id) || (int)$id < 1 || (int)$id > 2147483647) {
            throw new NotFoundException('Record not found.');
        }
        /** @var \App\Model\Table\RecordsTable $records */
        $records = $this->fetchTable('Records');
        /** @var \App\Model\Entity\Record|null $record */
        $record = $records->find('withTags')
            ->where(['Records.id' => (int)$id, 'Records.user_id' => $userId])
            ->first();
        if ($record === null) {
            throw new NotFoundException('Record not found.');
        }

        return $record;
    }

    /**
     * Resolve a tag owned by the caller.
     */
    private function fetchOwnedTagOrFail(string $id, int $userId): Tag
    {
        if (!ctype_digit($id) || (int)$id < 1 || (int)$id > 2147483647) {
            throw new NotFoundException('Tag not found.');
        }
        /** @var \App\Model\Entity\Tag|null $tag */
        $tag = $this->fetchTable('Tags')->find()
            ->where(['id' => (int)$id, 'user_id' => $userId])
            ->first();
        if ($tag === null) {
            throw new NotFoundException('Tag not found.');
        }

        return $tag;
    }

    /**
     * Parse `occurred_at`: an ISO 8601 date and time with a timezone, past or future, stored in
     * UTC to the second. Anything else is malformed and refused with `400`.
     */
    private function parseOccurredAt(mixed $value): DateTime
    {
        if (!is_string($value) || preg_match(self::TIMESTAMP_PATTERN, $value, $parts) !== 1) {
            throw new BadRequestException('Invalid occurred_at: use an ISO 8601 date and time with a timezone.');
        }
        $offsetValid = $parts[7] === 'Z'
            || ((int)substr($parts[7], 1, 2) <= 23 && (int)substr($parts[7], 4, 2) <= 59);
        if (
            !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])
            || (int)$parts[1] < 1
            || (int)$parts[4] > 23
            || (int)$parts[5] > 59
            || (int)$parts[6] > 59
            || !$offsetValid
        ) {
            throw new BadRequestException('Invalid occurred_at: use an ISO 8601 date and time with a timezone.');
        }

        $parsed = new DateTimeImmutable($value);

        return new DateTime($parsed->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'UTC');
    }

    /**
     * Resolve an optional date-range bound. A calendar date covers that whole UTC day, so
     * `from` starts at its first moment and `to` ends at its last; a full timestamp is exact.
     */
    private function readDateBound(string $field, bool $endOfDay): ?DateTime
    {
        $value = $this->request->getQuery($field);
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new BadRequestException(sprintf('Invalid %s filter.', $field));
        }
        if (preg_match(self::DATE_PATTERN, $value, $parts) === 1) {
            if (!checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]) || (int)$parts[1] < 1) {
                throw new BadRequestException(sprintf('Invalid %s filter.', $field));
            }
            $time = $endOfDay ? ' 23:59:59' : ' 00:00:00';

            return new DateTime($value . $time, 'UTC');
        }
        try {
            return $this->parseOccurredAt($value);
        } catch (BadRequestException) {
            throw new BadRequestException(sprintf('Invalid %s filter.', $field));
        }
    }

    /**
     * Resolve the optional search text: empty or whitespace applies no filter; longer than the
     * bound is refused rather than truncated.
     */
    private function readSearchFilter(): ?string
    {
        $query = $this->request->getQuery('q');
        if ($query === null || $query === '') {
            return null;
        }
        if (!is_string($query)) {
            throw new BadRequestException('Invalid search query.');
        }
        $query = trim($query);
        if ($query === '') {
            return null;
        }
        if (mb_strlen($query) > self::MAX_SEARCH_CHARS) {
            throw new BadRequestException('Search query is too long.');
        }

        return $query;
    }

    /**
     * Resolve the optional kind filter against the closed set.
     */
    private function readKindFilter(): ?string
    {
        $kind = $this->request->getQuery('kind');
        if ($kind === null || $kind === '') {
            return null;
        }
        if (!is_string($kind) || !in_array($kind, RecordsTable::KINDS, true)) {
            throw new BadRequestException('Invalid kind filter.');
        }

        return $kind;
    }

    /**
     * Resolve an optional positive id filter.
     */
    private function readPositiveIdFilter(string $field): ?int
    {
        $value = $this->request->getQuery($field);
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value) && ctype_digit($value) && (int)$value > 0 && (int)$value <= 2147483647) {
            return (int)$value;
        }

        throw new BadRequestException(sprintf('Invalid %s filter.', $field));
    }

    /**
     * Parse a positive pagination query parameter.
     */
    private function readPositiveQueryInt(string $field, int $default): int
    {
        $max = intdiv(PHP_INT_MAX, 100);
        $value = $this->request->getQuery($field, $default);
        if (is_int($value) && $value > 0 && $value <= $max) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && strlen($value) <= strlen((string)$max)) {
            $parsed = (int)$value;
            if ($parsed > 0 && $parsed <= $max) {
                return $parsed;
            }
        }

        throw new BadRequestException(sprintf('Invalid query parameter `%s`.', $field));
    }

    /**
     * Read a JSON object request body.
     *
     * @return array<string, mixed>
     */
    private function readBody(): array
    {
        $data = $this->request->getData();
        if (!is_array($data)) {
            throw new BadRequestException('Malformed request body.');
        }

        return $data;
    }
}
