<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\ActivityRecord;
use Cake\ORM\Locator\LocatorInterface;
use Cake\ORM\Query\SelectQuery;
use DomainException;
use JsonException;

/**
 * Single authority for writing and reading the activity stream.
 *
 * The API is intentionally small: one `record()` entry point and one `forActor()` query helper.
 * Activity recording is a deliberate, explicit call at the end of a successful mutation; the
 * application is not converted into an event-driven architecture to support history.
 *
 * Failure policy: a recording failure raises, it is never silently swallowed, so a broken
 * history write is visible instead of producing a misleading partial audit trail.
 */
class ActivityRecorder
{
    /**
     * Subject and context kinds that may be referenced by an activity record.
     *
     * @var list<string>
     */
    public const SUBJECT_TYPES = [
        'todo',
        'tag',
        'project',
        'notebook',
        'library',
        'capability_grant',
        'cross_context_request',
    ];

    /**
     * Metadata keys whose values are never persisted.
     *
     * @var list<string>
     */
    public const REDACTED_KEYS = ['password', 'token', 'secret', 'api_key', 'authorization', 'credential'];

    /**
     * Maximum stored length of any single metadata string value.
     */
    public const MAX_VALUE_LENGTH = 255;

    /**
     * @param \Cake\ORM\Locator\LocatorInterface $tables Table locator used to resolve models.
     */
    public function __construct(private LocatorInterface $tables)
    {
    }

    /**
     * Record a single activity entry for a completed mutation.
     *
     * @param array<string, mixed> $metadata Small, non-sensitive supporting detail.
     * @throws \DomainException When the record is malformed or cannot be persisted.
     */
    public function record(
        int $actorUserId,
        string $action,
        string $subjectType,
        int $subjectId,
        array $metadata = [],
        ?string $contextType = null,
        ?int $contextId = null,
    ): ActivityRecord {
        $action = $this->normalizeAction($action);
        if (!in_array($subjectType, self::SUBJECT_TYPES, true)) {
            throw new DomainException('Unknown activity subject type.');
        }
        if ($contextType !== null && !in_array($contextType, self::SUBJECT_TYPES, true)) {
            throw new DomainException('Unknown activity context type.');
        }
        if (($contextType === null) !== ($contextId === null)) {
            throw new DomainException('Activity context type and id must be supplied together.');
        }

        $table = $this->tables->get('ActivityRecords');
        $record = $table->newEmptyEntity();
        $record->set('actor_user_id', $actorUserId);
        $record->set('action', $action);
        $record->set('subject_type', $subjectType);
        $record->set('subject_id', $subjectId);
        $record->set('context_type', $contextType);
        $record->set('context_id', $contextId);
        $record->set('metadata', $this->encodeMetadata($metadata));

        if (!$table->save($record)) {
            throw new DomainException('Unable to record activity.');
        }

        /** @var \App\Model\Entity\ActivityRecord $record */
        return $record;
    }

    /**
     * Build the query of activity visible to an actor.
     *
     * Visibility is deliberately narrow: an actor sees only the activity they themselves produced.
     *
     * @param array<string, mixed> $filters Optional `subject_type`, `subject_id` and `action` filters.
     * @return \Cake\ORM\Query\SelectQuery<\App\Model\Entity\ActivityRecord>
     * @throws \DomainException When a filter value is malformed.
     */
    public function forActor(int $actorUserId, array $filters = []): SelectQuery
    {
        $conditions = ['ActivityRecords.actor_user_id' => $actorUserId];

        $subjectType = $filters['subject_type'] ?? null;
        if ($subjectType !== null && $subjectType !== '') {
            if (!is_string($subjectType) || !in_array($subjectType, self::SUBJECT_TYPES, true)) {
                throw new DomainException('Unknown activity subject type.');
            }
            $conditions['ActivityRecords.subject_type'] = $subjectType;
        }

        $subjectId = $filters['subject_id'] ?? null;
        if ($subjectId !== null && $subjectId !== '') {
            if (!is_numeric($subjectId) || (int)$subjectId < 1) {
                throw new DomainException('Invalid activity subject id.');
            }
            $conditions['ActivityRecords.subject_id'] = (int)$subjectId;
        }

        $action = $filters['action'] ?? null;
        if ($action !== null && trim((string)$action) !== '') {
            $conditions['ActivityRecords.action'] = $this->normalizeAction((string)$action);
        }

        /** @var \Cake\ORM\Query\SelectQuery<\App\Model\Entity\ActivityRecord> $query */
        $query = $this->tables->get('ActivityRecords')
            ->find()
            ->where($conditions)
            ->orderBy(['ActivityRecords.created' => 'DESC', 'ActivityRecords.id' => 'DESC']);

        return $query;
    }

    /**
     * Normalize an action name into the canonical stored form.
     *
     * @throws \DomainException When the action is empty or too long.
     */
    public function normalizeAction(string $action): string
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $action) ?? ''));
        if ($normalized === '') {
            throw new DomainException('Activity action is required.');
        }
        if (strlen($normalized) > 64) {
            throw new DomainException('Activity action is too long.');
        }

        return $normalized;
    }

    /**
     * Redact and serialize metadata for storage.
     *
     * @param array<string, mixed> $metadata
     * @throws \DomainException When the metadata cannot be serialized.
     */
    private function encodeMetadata(array $metadata): ?string
    {
        $safe = $this->redact($metadata);
        if ($safe === []) {
            return null;
        }

        try {
            return json_encode($safe, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DomainException('Activity metadata could not be serialized.');
        }
    }

    /**
     * Drop sensitive keys, flatten unsupported values and truncate long strings.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function redact(array $metadata): array
    {
        $safe = [];
        foreach ($metadata as $key => $value) {
            $key = (string)$key;
            if (in_array(strtolower($key), self::REDACTED_KEYS, true)) {
                continue;
            }
            if (is_array($value)) {
                $nested = $this->redact($value);
                if ($nested !== []) {
                    $safe[$key] = $nested;
                }
                continue;
            }
            if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
                $safe[$key] = $value;
                continue;
            }
            if (is_string($value)) {
                $safe[$key] = mb_substr($value, 0, self::MAX_VALUE_LENGTH);
                continue;
            }
        }

        return $safe;
    }
}
