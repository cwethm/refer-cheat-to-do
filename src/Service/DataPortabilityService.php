<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Table\ReferenceCardsTable;
use App\Model\Table\TodosTable;
use Cake\Database\Connection;
use Cake\ORM\Locator\LocatorInterface;
use DateTimeImmutable;
use DateTimeInterface;
use DomainException;
use Throwable;

/**
 * Owner-only, snapshot-consistent JSONL portability with database-backed restore staging.
 */
class DataPortabilityService
{
    public const SCHEMA = 'urn:cheat-to-do:export:1';
    public const BATCH_SIZE = 100;
    public const MAX_LINE_BYTES = 1048576;
    public const MAX_METADATA_BYTES = 8192;
    public const TEXT_CHUNK_CHARS = 16384;

    /** @var list<string> */
    private const TEXT_FIELDS = ['notes', 'description', 'terminal_objective', 'result_summary', 'content', 'body'];

    /**
     * Ordered dependencies and the complete portable field allowlist.
     *
     * @var array<string, string>
     */
    private const FIELDS = [
        'projects' => 'id,name,description,created,modified',
        'project_sections' => 'id,project_id,name,position,created,modified',
        'notebooks' => 'id,name,description,created,modified',
        'notebook_sections' => 'id,notebook_id,name,position,created,modified',
        'libraries' => 'id,name,description,created,modified',
        'research_collections' => 'id,name,description,created,modified',
        'tags' => 'id,name,created,modified',
        'reference_cards' => 'id,card_type,title,summary,body,situation,aim,approach,avoid,rationale,created,modified',
        'cheat_sheets' => 'id,name,description,created,modified',
        'todos' => 'id,title,notes,status,created,modified,project_section_id,notebook_section_id,'
            . 'archived_at,trashed_at,previous_status,last_reviewed_at,next_review_at,review_interval_days,'
            . 'parent_todo_id,terminal_objective,objective_satisfied_at,result_summary',
        'research_collection_items' => 'id,research_collection_id,title,content,source_uri,'
            . 'related_resource_type,related_resource_id,created,modified',
        'todos_tags' => 'todo_id,tag_id',
        'related_todos' => 'todo_id,related_todo_id,created',
        'libraries_projects' => 'library_id,project_id,created',
        'libraries_notebooks' => 'library_id,notebook_id,created',
        'reference_cards_tags' => 'reference_card_id,tag_id',
        'cheat_sheets_reference_cards' => 'cheat_sheet_id,reference_card_id,position,created',
        'activity_records' => 'id,action,subject_type,subject_id,context_type,context_id,metadata,created',
    ];

    /** @var array<string, string> */
    private const REFERENCES = [
        'project_id' => 'projects',
        'notebook_id' => 'notebooks',
        'library_id' => 'libraries',
        'todo_id' => 'todos',
        'related_todo_id' => 'todos',
        'tag_id' => 'tags',
        'project_section_id' => 'project_sections',
        'notebook_section_id' => 'notebook_sections',
        'parent_todo_id' => 'todos',
        'research_collection_id' => 'research_collections',
        'reference_card_id' => 'reference_cards',
        'cheat_sheet_id' => 'cheat_sheets',
    ];

    private Connection $connection;

    /**
     * @param \Cake\ORM\Locator\LocatorInterface $tables Application table locator.
     */
    public function __construct(private LocatorInterface $tables)
    {
        $this->connection = $tables->get('Users')->getConnection();
    }

    /**
     * Write to a caller-owned stream. HTTP callers must finish generation before sending headers.
     *
     * @param resource $stream Writable stream.
     */
    public function writeExport(int $userId, mixed $stream): void
    {
        $this->assertStream($stream);
        $this->snapshot(function () use ($userId, $stream): void {
            $this->assertUser($userId);
            $this->writeLine($stream, ['schema' => self::SCHEMA]);
            $count = 0;
            foreach (self::FIELDS as $type => $fields) {
                $keys = $this->keys($type);
                $last = null;
                do {
                    $rows = $this->exportBatch($type, $fields, $userId, $keys, $last);
                    foreach ($rows as $row) {
                        $last = array_map(static fn(string $key): int => (int)$row[$key], $keys);
                        $lengths = [];
                        foreach (self::TEXT_FIELDS as $field) {
                            if (isset($row['_length_' . $field])) {
                                $lengths[$field] = (int)$row['_length_' . $field];
                                if ($lengths[$field] > self::TEXT_CHUNK_CHARS) {
                                    $row[$field] = ['chunks' => $lengths[$field]];
                                }
                            }
                            unset($row['_length_' . $field]);
                        }
                        $this->sanitize($type, $row, $userId);
                        $this->writeLine($stream, ['type' => $type, 'data' => $row]);
                        $count++;
                        foreach ($lengths as $field => $length) {
                            if ($length <= self::TEXT_CHUNK_CHARS) {
                                continue;
                            }
                            for ($offset = 0; $offset < $length; $offset += self::TEXT_CHUNK_CHARS) {
                                $chunk = $this->connection->execute(
                                    "SELECT substring($field FROM CAST(? AS integer) FOR CAST(? AS integer)) "
                                        . "AS value FROM $type WHERE id = ?",
                                    [$offset + 1, self::TEXT_CHUNK_CHARS, $row['id']],
                                )->fetch('assoc');
                                $this->writeLine($stream, ['type' => 'text_chunks', 'data' => [
                                    'record_type' => $type, 'id' => $row['id'], 'field' => $field,
                                    'offset' => $offset, 'total' => $length, 'value' => $chunk['value'],
                                ]]);
                                $count++;
                            }
                        }
                    }
                    $size = count($rows);
                } while ($size === self::BATCH_SIZE);
            }
            $this->writeLine($stream, ['end' => true, 'records' => $count]);
        });
    }

    /**
     * Restore into an existing empty account, preserving stored values rather than mutation defaults.
     *
     * @param resource $stream Readable JSONL stream; need not be seekable.
     */
    public function restore(int $userId, mixed $stream): void
    {
        $this->assertStream($stream);
        $this->snapshot(function () use ($userId, $stream): void {
            $this->assertUser($userId, true);
            foreach (self::FIELDS as $type => $fields) {
                if (
                    $this->connection->execute(
                        "SELECT 1 FROM $type r WHERE " . $this->ownership($type) . ' LIMIT 1',
                        [$userId],
                    )->fetch('assoc')
                ) {
                    throw new DomainException('Restore target must have an empty workspace and activity history.');
                }
            }
            $this->connection->execute(
                'CREATE TEMP TABLE portability_rows (seq bigint PRIMARY KEY, kind text NOT NULL, '
                . 'source_key text NOT NULL, data jsonb NOT NULL, UNIQUE(kind, source_key)) ON COMMIT DROP',
            );
            $this->connection->execute(
                'CREATE TEMP TABLE portability_ids (kind text NOT NULL, old_id bigint NOT NULL, '
                . 'new_id bigint NOT NULL, PRIMARY KEY(kind, old_id)) ON COMMIT DROP',
            );
            $this->connection->execute(
                'CREATE TEMP TABLE portability_chunks (kind text NOT NULL, old_id bigint NOT NULL, '
                . 'field text NOT NULL, position bigint NOT NULL, total bigint NOT NULL, value text NOT NULL, '
                . 'PRIMARY KEY(kind, old_id, field, position)) ON COMMIT DROP',
            );
            $this->connection->execute(
                'CREATE TEMP TABLE portability_text (kind text NOT NULL, old_id bigint NOT NULL, '
                . 'field text NOT NULL, total bigint NOT NULL, PRIMARY KEY(kind,old_id,field)) ON COMMIT DROP',
            );
            $this->stage($stream);
            $this->assembleChunks();
            foreach (self::FIELDS as $type => $fields) {
                $last = 0;
                do {
                    $rows = $this->connection->execute(
                        'SELECT seq, data FROM portability_rows WHERE kind = ? AND seq > ? '
                        . 'ORDER BY seq LIMIT ' . self::BATCH_SIZE,
                        [$type, $last],
                    )->fetchAll('assoc');
                    foreach ($rows as $row) {
                        $last = (int)$row['seq'];
                        $data = json_decode($row['data'], true, 32, JSON_THROW_ON_ERROR);
                        $this->insertRecord($type, $data, $userId);
                    }
                    $size = count($rows);
                } while ($size === self::BATCH_SIZE);
            }
            $this->restoreText();
            $this->restoreParents();
        }, false);
    }

    /**
     * PostgreSQL's snapshot is shared by every batch and authorization query.
     */
    private function snapshot(callable $operation, bool $export = true): void
    {
        if ($this->connection->inTransaction()) {
            throw new DomainException('Portability requires its own transaction.');
        }
        $this->connection->begin();
        try {
            // Restore takes a user row lock before inspecting emptiness. READ COMMITTED ensures a
            // competing restore observes committed rows after waiting for that same lock.
            $this->connection->execute(
                $export
                ? 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY'
                : 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED',
            );
            $operation();
            $this->connection->commit();
        } catch (Throwable $exception) {
            $this->connection->rollback();
            throw $exception;
        }
    }

    /**
     * Verify account existence and optionally serialize concurrent restores.
     */
    private function assertUser(int $userId, bool $lock = false): void
    {
        if (
            $userId < 1 || !$this->connection->execute(
                'SELECT id FROM users WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''),
                [$userId],
            )->fetch('assoc')
        ) {
            throw new DomainException('An existing user is required.');
        }
    }

    /**
     * All identifiers below are internal constants, never input.
     */
    private function ownership(string $type): string
    {
        return match ($type) {
            'project_sections' => 'r.project_id IN (SELECT id FROM projects WHERE user_id = ?)',
            'notebook_sections' => 'r.notebook_id IN (SELECT id FROM notebooks WHERE user_id = ?)',
            'todos_tags' => 'r.todo_id IN (SELECT id FROM todos WHERE user_id = ?) '
                . 'AND r.tag_id IN (SELECT id FROM tags WHERE user_id = (SELECT user_id FROM todos '
                . 'WHERE id = r.todo_id))',
            'related_todos' => 'r.todo_id IN (SELECT id FROM todos WHERE user_id = ?) '
                . 'AND r.related_todo_id IN (SELECT id FROM todos WHERE user_id = '
                . '(SELECT user_id FROM todos WHERE id = r.todo_id))',
            'libraries_projects' => 'r.library_id IN (SELECT id FROM libraries WHERE user_id = ?) '
                . 'AND r.project_id IN (SELECT id FROM projects WHERE user_id = '
                . '(SELECT user_id FROM libraries WHERE id = r.library_id))',
            'libraries_notebooks' => 'r.library_id IN (SELECT id FROM libraries WHERE user_id = ?) '
                . 'AND r.notebook_id IN (SELECT id FROM notebooks WHERE user_id = '
                . '(SELECT user_id FROM libraries WHERE id = r.library_id))',
            'reference_cards_tags' => 'r.reference_card_id IN (SELECT id FROM reference_cards WHERE user_id = ?) '
                . 'AND r.tag_id IN (SELECT id FROM tags WHERE user_id = (SELECT user_id FROM reference_cards '
                . 'WHERE id = r.reference_card_id))',
            'cheat_sheets_reference_cards' => 'r.cheat_sheet_id IN (SELECT id FROM cheat_sheets WHERE user_id = ?) '
                . 'AND r.reference_card_id IN (SELECT id FROM reference_cards WHERE user_id = '
                . '(SELECT user_id FROM cheat_sheets WHERE id = r.cheat_sheet_id))',
            'research_collection_items' => 'r.research_collection_id IN '
                . '(SELECT id FROM research_collections WHERE user_id = ?)',
            'activity_records' => 'r.actor_user_id = ?',
            default => 'r.user_id = ?',
        };
    }

    /**
     * @return list<string>
     */
    private function keys(string $type): array
    {
        return match ($type) {
            'todos_tags' => ['todo_id', 'tag_id'],
            'related_todos' => ['todo_id', 'related_todo_id'],
            'libraries_projects' => ['library_id', 'project_id'],
            'libraries_notebooks' => ['library_id', 'notebook_id'],
            'reference_cards_tags' => ['reference_card_id', 'tag_id'],
            'cheat_sheets_reference_cards' => ['cheat_sheet_id', 'reference_card_id'],
            default => ['id'],
        };
    }

    /**
     * @param list<string> $keys Keyset columns.
     * @param list<int>|null $last Last composite key.
     * @return list<array<string, mixed>>
     */
    private function exportBatch(string $type, string $fields, int $userId, array $keys, ?array $last): array
    {
        if ($type === 'activity_records') {
            $selected = array_values(array_diff(explode(',', $fields), ['metadata']));
            $query = (new ActivityRecorder($this->tables))->forActor($userId)
                ->select($selected)->disableHydration()
                ->orderBy(['ActivityRecords.id' => 'ASC'], true)->limit(self::BATCH_SIZE);
            $query->select(['metadata' => $query->expr(
                'CASE WHEN octet_length(ActivityRecords.metadata) <= ' . self::MAX_METADATA_BYTES
                . ' THEN ActivityRecords.metadata ELSE NULL END',
            )]);
            if ($last !== null) {
                $query->where(['ActivityRecords.id >' => $last[0]]);
            }

            $rows = [];
            foreach ($query->all() as $entity) {
                $row = [];
                foreach (explode(',', $fields) as $field) {
                    $row[$field] = is_array($entity) ? $entity[$field] : $entity->get($field);
                }
                $rows[] = $row;
            }

            return $rows;
        }
        $columns = implode(',', array_map(
            static fn(string $field): string => in_array($field, self::TEXT_FIELDS, true)
                ? "CASE WHEN char_length(r.$field) <= " . self::TEXT_CHUNK_CHARS
                    . " THEN r.$field ELSE NULL END AS $field, char_length(r.$field) AS _length_$field"
                : 'r.' . $field,
            explode(',', $fields),
        ));
        $keySql = implode(',', array_map(static fn(string $key): string => 'r.' . $key, $keys));
        $sql = "SELECT $columns FROM $type r WHERE " . $this->ownership($type);
        $params = [$userId];
        if ($last !== null) {
            $sql .= " AND ($keySql) > (" . implode(',', array_fill(0, count($keys), '?')) . ')';
            $params = array_merge($params, $last);
        }

        $result = $this->connection->execute(
            $sql . " ORDER BY $keySql LIMIT " . self::BATCH_SIZE,
            $params,
        )->fetchAll('assoc');
        $rows = [];
        foreach ($result as $record) {
            $row = [];
            foreach (explode(',', $fields) as $field) {
                $row[$field] = $record[$field];
                if (in_array($field, self::TEXT_FIELDS, true)) {
                    $row['_length_' . $field] = $record['_length_' . $field];
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row Portable row.
     */
    private function sanitize(string $type, array &$row, int $userId): void
    {
        $capabilities = new CapabilityService($this->tables);
        if (in_array($type, ['projects', 'notebooks', 'libraries'], true)) {
            $kind = $type === 'libraries' ? 'library' : substr($type, 0, -1);
            if ($capabilities->ownerIdFor($kind, (int)$row['id']) !== $userId) {
                throw new DomainException('Resource is not owned by the export user.');
            }
        }
        if ($type === 'todos') {
            foreach (['project_section_id', 'notebook_section_id', 'parent_todo_id'] as $field) {
                if ($row[$field] !== null && !$this->owns(self::REFERENCES[$field], (int)$row[$field], $userId)) {
                    $row[$field] = null;
                }
            }
        }
        if ($type === 'research_collection_items' && $row['related_resource_id'] !== null) {
            $target = match ($row['related_resource_type']) {
                'todo' => 'todos',
                'project' => 'projects',
                'notebook' => 'notebooks',
                default => null,
            };
            if ($target === null || !$this->owns($target, (int)$row['related_resource_id'], $userId)) {
                $row['related_resource_type'] = null;
                $row['related_resource_id'] = null;
            }
        }
        if ($type === 'activity_records') {
            foreach (['subject', 'context'] as $pointer) {
                $kind = $this->subjectTable($row[$pointer . '_type']);
                $id = (int)$row[$pointer . '_id'];
                if ($kind === null || !$this->owns($kind, $id, $userId)) {
                    $row[$pointer . '_id'] = $pointer === 'subject' ? 0 : null;
                    if ($pointer === 'context') {
                        $row['context_type'] = null;
                    }
                }
            }
            $metadata = is_string($row['metadata']) ? json_decode($row['metadata'], true, 16) : null;
            $safe = is_array($metadata) ? $this->safeMetadata($metadata, $userId) : [];
            $row['metadata'] = $safe === [] ? null : json_encode($safe, JSON_THROW_ON_ERROR);
            if ($row['created'] instanceof DateTimeInterface) {
                $format = $row['created']->format('u') === '000000' ? 'Y-m-d H:i:s' : 'Y-m-d H:i:s.u';
                $row['created'] = $row['created']->format($format);
            }
        }
    }

    /**
     * Resolve only ownership, never capability-visible records.
     */
    private function owns(string $type, int $id, int $userId): bool
    {
        if (in_array($type, ['projects', 'notebooks', 'libraries', 'research_collections'], true)) {
            $kind = match ($type) {
                'libraries' => 'library',
                'research_collections' => CapabilityService::RESOURCE_RESEARCH_COLLECTION,
                default => substr($type, 0, -1),
            };

            return (new CapabilityService($this->tables))->ownerIdFor($kind, $id) === $userId;
        }

        return (bool)$this->connection->execute(
            "SELECT 1 FROM $type r WHERE r.id = ? AND " . $this->ownership($type),
            [$id, $userId],
        )->fetch('assoc');
    }

    /**
     * Resolve portable history subject kinds.
     */
    private function subjectTable(?string $type): ?string
    {
        return match ($type) {
            'todo' => 'todos',
            'tag' => 'tags',
            'project' => 'projects',
            'notebook' => 'notebooks',
            'library' => 'libraries',
            'research_collection' => 'research_collections',
            'research_collection_item' => 'research_collection_items',
            'reference_card' => 'reference_cards',
            'cheat_sheet' => 'cheat_sheets',
            default => null,
        };
    }

    /**
     * Preserve known application history details, never opaque arbitrary payloads.
     *
     * @param array<string, mixed> $metadata Stored metadata.
     * @return array<string, mixed>
     */
    private function safeMetadata(array $metadata, ?int $userId): array
    {
        $metadata = (new ActivityRecorder($this->tables))->redact($metadata);
        $safe = [];
        foreach (['status', 'previous_status'] as $field) {
            if (isset($metadata[$field]) && in_array($metadata[$field], TodosTable::ALLOWED_STATUSES, true)) {
                $safe[$field] = $metadata[$field];
            }
        }
        if (
            isset($metadata['capability'])
            && in_array($metadata['capability'], CapabilityService::CAPABILITIES, true)
        ) {
            $safe['capability'] = $metadata['capability'];
        }
        if (isset($metadata['fields']) && is_array($metadata['fields']) && array_is_list($metadata['fields'])) {
            $safe['fields'] = array_values(array_intersect(
                array_filter($metadata['fields'], 'is_string'),
                [
                    'title', 'notes', 'status', 'project_section_id', 'notebook_section_id',
                    'name', 'description', 'card_type', 'summary', 'body',
                    'situation', 'aim', 'approach', 'avoid', 'rationale',
                ],
            ));
        }
        foreach (self::REFERENCES as $field => $target) {
            $id = $metadata[$field] ?? null;
            if (is_int($id) && $id > 0 && ($userId === null || $this->owns($target, $id, $userId))) {
                $safe[$field] = $userId === null ? $this->mappedId($target, $id) : $id;
            }
        }
        foreach (['member', 'resource', 'target'] as $pointer) {
            $kind = $metadata[$pointer . '_type'] ?? null;
            $id = $metadata[$pointer . '_id'] ?? null;
            $target = is_string($kind) ? $this->subjectTable($kind) : null;
            if (
                $target !== null && is_int($id) && $id > 0
                && ($userId === null || $this->owns($target, $id, $userId))
            ) {
                $safe[$pointer . '_type'] = $kind;
                $safe[$pointer . '_id'] = $userId === null ? $this->mappedId($target, $id) : $id;
            }
        }

        return $safe;
    }

    /**
     * @param resource $stream Output stream.
     * @param array<string, mixed> $record JSON object.
     */
    private function writeLine(mixed $stream, array $record): void
    {
        $line = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n";
        if (strlen($line) > self::MAX_LINE_BYTES) {
            throw new DomainException('Record exceeds the portability line limit.');
        }
        while ($line !== '') {
            $written = fwrite($stream, $line);
            if ($written === false || $written === 0) {
                throw new DomainException('Unable to write export.');
            }
            $line = substr($line, $written);
        }
    }

    /**
     * @param resource $stream Input stream.
     * @return array<string, mixed>|null
     * @phpstan-impure
     */
    private function readLine(mixed $stream): ?array
    {
        $line = fgets($stream, self::MAX_LINE_BYTES + 1);
        if ($line === false) {
            if (!feof($stream)) {
                throw new DomainException('Unable to read export.');
            }

            return null;
        }
        if (!str_ends_with($line, "\n")) {
            throw new DomainException('Truncated or oversized JSONL line.');
        }
        $record = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($record) || array_is_list($record)) {
            throw new DomainException('Expected a JSON object.');
        }

        return $record;
    }

    /**
     * @param resource $stream Input stream.
     */
    private function stage(mixed $stream): void
    {
        if ($this->readLine($stream) !== ['schema' => self::SCHEMA]) {
            throw new DomainException('Unsupported portability schema.');
        }
        $count = 0;
        while (($record = $this->readLine($stream)) !== null) {
            if (isset($record['end'])) {
                $keys = array_keys($record);
                sort($keys);
                if (
                    $keys !== ['end', 'records'] || $record['end'] !== true || $record['records'] !== $count
                    || $this->readLine($stream) !== null
                ) {
                    throw new DomainException('Invalid export footer or trailing records.');
                }

                return;
            }
            $keys = array_keys($record);
            sort($keys);
            if ($keys === ['data', 'type'] && $record['type'] === 'text_chunks') {
                $this->stageChunk($record['data']);
                $count++;
                continue;
            }
            if (
                $keys !== ['data', 'type'] || !is_string($record['type'])
                || !isset(self::FIELDS[$record['type']]) || !is_array($record['data'])
            ) {
                throw new DomainException('Unknown record type or envelope fields.');
            }
            $type = $record['type'];
            $data = $record['data'];
            foreach (self::TEXT_FIELDS as $field) {
                if (isset($data[$field]) && is_array($data[$field])) {
                    if (
                        array_keys($data[$field]) !== ['chunks'] || !is_int($data[$field]['chunks'])
                        || $data[$field]['chunks'] <= self::TEXT_CHUNK_CHARS
                        || !isset($data['id']) || !is_int($data['id'])
                    ) {
                        throw new DomainException('Invalid chunk declaration.');
                    }
                    $this->connection->execute(
                        'INSERT INTO portability_text (kind,old_id,field,total) VALUES (?,?,?,?)',
                        [$type, $data['id'], $field, $data[$field]['chunks']],
                    );
                    $data[$field] = null;
                }
            }
            $this->validate($type, $data);
            $key = implode(':', array_map(
                static fn(string $field): string => (string)$data[$field],
                $this->keys($type),
            ));
            $this->connection->execute(
                'INSERT INTO portability_rows (seq, kind, source_key, data) VALUES (?, ?, ?, ?)',
                [++$count, $type, $key, json_encode($data, JSON_THROW_ON_ERROR)],
            );
        }
        throw new DomainException('Missing export footer.');
    }

    /**
     * @param mixed $data Untrusted chunk.
     */
    private function stageChunk(mixed $data): void
    {
        if (!is_array($data)) {
            throw new DomainException('Invalid text chunk.');
        }
        $keys = array_keys($data);
        sort($keys);
        if (
            $keys !== ['field', 'id', 'offset', 'record_type', 'total', 'value']
            || !is_string($data['record_type']) || !isset(self::FIELDS[$data['record_type']])
            || !is_string($data['field']) || !in_array($data['field'], self::TEXT_FIELDS, true)
            || !in_array($data['field'], explode(',', self::FIELDS[$data['record_type']]), true)
            || !is_int($data['id']) || $data['id'] < 1 || !is_int($data['offset']) || $data['offset'] < 0
            || !is_int($data['total']) || $data['total'] <= self::TEXT_CHUNK_CHARS
            || !is_string($data['value']) || str_contains($data['value'], "\0")
            || mb_strlen($data['value']) !== min(self::TEXT_CHUNK_CHARS, $data['total'] - $data['offset'])
            || $data['offset'] % self::TEXT_CHUNK_CHARS !== 0
        ) {
            throw new DomainException('Invalid text chunk fields.');
        }
        $this->connection->execute(
            'INSERT INTO portability_chunks (kind,old_id,field,position,total,value) VALUES (?,?,?,?,?,?)',
            [$data['record_type'], $data['id'], $data['field'], $data['offset'], $data['total'], $data['value']],
        );
    }

    /**
     * Reassemble text in PostgreSQL, not in application memory.
     */
    private function assembleChunks(): void
    {
        $invalid = $this->connection->execute(
            'SELECT 1 FROM portability_chunks c LEFT JOIN portability_rows r '
            . 'ON r.kind=c.kind AND r.source_key=c.old_id::text LEFT JOIN portability_text d '
            . 'ON d.kind=c.kind AND d.old_id=c.old_id AND d.field=c.field '
            . 'WHERE r.seq IS NULL OR d.total IS NULL OR d.total <> c.total '
            . "OR r.data->c.field <> 'null'::jsonb LIMIT 1",
        )->fetch('assoc');
        $missing = $this->connection->execute(
            'SELECT 1 FROM portability_text d WHERE NOT EXISTS (SELECT 1 FROM portability_chunks c '
            . 'WHERE c.kind=d.kind AND c.old_id=d.old_id AND c.field=d.field) LIMIT 1',
        )->fetch('assoc');
        $incomplete = $this->connection->execute(
            'SELECT 1 FROM portability_chunks GROUP BY kind,old_id,field HAVING MIN(position) <> 0 '
            . 'OR MIN(total) <> MAX(total) OR SUM(char_length(value)) <> MAX(total) '
            . 'OR MAX(position) + MAX(char_length(value)) < MAX(total) LIMIT 1',
        )->fetch('assoc');
        if ($invalid || $incomplete || $missing) {
            throw new DomainException('Missing, conflicting or incomplete text chunks.');
        }
    }

    /**
     * Large text never passes back through PHP during restore.
     */
    private function restoreText(): void
    {
        foreach (self::FIELDS as $type => $fields) {
            foreach (array_intersect(explode(',', $fields), self::TEXT_FIELDS) as $field) {
                $this->connection->execute(
                    "UPDATE $type r SET $field=c.value FROM (SELECT i.new_id, "
                    . "string_agg(c.value, '' ORDER BY c.position) AS value "
                    . 'FROM portability_chunks c JOIN portability_ids i ON i.kind=c.kind AND i.old_id=c.old_id '
                    . 'WHERE c.kind=? AND c.field=? GROUP BY i.new_id) c WHERE r.id=c.new_id',
                    [$type, $field],
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $data Untrusted portable fields.
     */
    private function validate(string $type, array $data): void
    {
        $expected = explode(',', self::FIELDS[$type]);
        $actual = array_keys($data);
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw new DomainException('Unknown or missing fields, including credentials, are forbidden.');
        }
        $schema = $this->tables->get($this->alias($type))->getSchema();
        foreach ($data as $field => $value) {
            $column = $schema->getColumn($field);
            if ($column === null) {
                throw new DomainException('Unknown database field.');
            }
            if ($value === null) {
                if (!$column['null']) {
                    throw new DomainException("Field $field cannot be null.");
                }
                continue;
            }
            if (in_array($column['type'], ['integer', 'biginteger'], true)) {
                if (
                    !is_int($value) || $value < 0 || $value > 2147483647
                    || ($value === 0 && !in_array($field, ['position', 'subject_id'], true))
                ) {
                    throw new DomainException("Invalid integer $field.");
                }
            } elseif (in_array($column['type'], ['datetime', 'timestamp'], true)) {
                $valid = is_string($value)
                    && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d{1,6})?$/D', $value);
                $parts = $valid ? explode('.', $value, 2) : [];
                $date = $valid ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $parts[0]) : false;
                if (!$date || $date->format('Y-m-d H:i:s') !== $parts[0]) {
                    throw new DomainException("Invalid timestamp $field.");
                }
            } elseif (
                !is_string($value) || str_contains($value, "\0")
                || ($column['length'] !== null && mb_strlen($value) > $column['length'])
            ) {
                throw new DomainException("Invalid string $field.");
            }
        }
        foreach (['title', 'name', 'action'] as $field) {
            if (isset($data[$field]) && (!is_string($data[$field]) || trim($data[$field]) === '')) {
                throw new DomainException("Empty $field.");
            }
        }
        if ($type === 'todos') {
            if (
                !in_array($data['status'], TodosTable::ALLOWED_STATUSES, true)
                || ($data['previous_status'] !== null
                && !in_array($data['previous_status'], TodosTable::ALLOWED_STATUSES, true))
                || $data['parent_todo_id'] === $data['id']
                || ($data['result_summary'] !== null && $data['objective_satisfied_at'] === null)
                || ($data['project_section_id'] !== null && $data['notebook_section_id'] !== null)
                || (($data['status'] === 'archived') !== ($data['archived_at'] !== null))
                || (($data['status'] === 'trashed') !== ($data['trashed_at'] !== null))
                || $data['review_interval_days'] > 365
            ) {
                throw new DomainException('Invalid ToDo lifecycle or objective fields.');
            }
        }
        if ($type === 'related_todos' && $data['todo_id'] >= $data['related_todo_id']) {
            throw new DomainException('Related ToDos must be a canonical distinct pair.');
        }
        if (
            $type === 'research_collection_items'
            && $data['content'] !== null
            && (!is_string($data['content']) || strlen($data['content']) > 65536)
        ) {
            throw new DomainException('Research collection item content exceeds its stored byte limit.');
        }
        if (
            $type === 'research_collection_items'
            && $data['related_resource_type'] !== null
            && !in_array($data['related_resource_type'], ['todo', 'project', 'notebook'], true)
        ) {
            throw new DomainException('Invalid research collection resource reference.');
        }
        if (
            $type === 'research_collection_items'
            && (($data['related_resource_type'] === null) !== ($data['related_resource_id'] === null))
        ) {
            throw new DomainException('Research collection resource type and id must be supplied together.');
        }
        if ($type === 'reference_cards') {
            if (
                !in_array($data['card_type'], ReferenceCardsTable::CARD_TYPES, true)
                || (is_string($data['body']) && strlen($data['body']) > ReferenceCardsTable::MAX_BODY_BYTES)
            ) {
                throw new DomainException('Invalid reference card type or body size.');
            }
            if ($data['card_type'] !== ReferenceCardsTable::TYPE_DIALOGUE_PATTERN) {
                foreach (ReferenceCardsTable::DIALOGUE_FIELDS as $field) {
                    if ($data[$field] !== null) {
                        throw new DomainException('Dialogue fields belong only to dialogue pattern cards.');
                    }
                }
            }
        }
        if (
            $type === 'cheat_sheets' && $data['description'] !== null && mb_strlen($data['description']) > 5000
        ) {
            throw new DomainException('Cheat sheet description exceeds its stored character limit.');
        }
        if ($type === 'activity_records') {
            if (
                !in_array($data['subject_type'], ActivityRecorder::SUBJECT_TYPES, true)
                || ($data['context_type'] !== null
                && !in_array($data['context_type'], ActivityRecorder::SUBJECT_TYPES, true))
                || (($data['context_type'] === null) !== ($data['context_id'] === null))
            ) {
                throw new DomainException('Invalid or unsafe activity record.');
            }
            if ($data['metadata'] !== null) {
                if (!is_string($data['metadata']) || strlen($data['metadata']) > self::MAX_METADATA_BYTES) {
                    throw new DomainException('Activity metadata exceeds the portable bound.');
                }
                $metadata = json_decode($data['metadata'], true, 16, JSON_THROW_ON_ERROR);
                if (!is_array($metadata) || array_is_list($metadata)) {
                    throw new DomainException('Activity metadata must be an object.');
                }
                $allowed = array_merge(
                    ['status', 'previous_status', 'capability', 'fields'],
                    array_keys(self::REFERENCES),
                    ['member_type', 'member_id', 'resource_type', 'resource_id', 'target_type', 'target_id'],
                );
                if (array_diff(array_keys($metadata), $allowed) !== []) {
                    throw new DomainException('Unknown or sensitive activity metadata fields.');
                }
            }
        }
    }

    /**
     * Convert a portable table identifier to its ORM alias.
     */
    private function alias(string $type): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $type)));
    }

    /**
     * @param array<string, mixed> $data Validated row.
     */
    private function insertRecord(string $type, array $data, int $userId): void
    {
        $oldId = $data['id'] ?? null;
        unset($data['id']);
        foreach (self::REFERENCES as $field => $target) {
            if (!array_key_exists($field, $data) || $data[$field] === null) {
                continue;
            }
            $data[$field] = $field === 'parent_todo_id' ? null : $this->mappedId($target, $data[$field]);
        }
        if ($type === 'research_collection_items' && $data['related_resource_id'] !== null) {
            $target = match ($data['related_resource_type']) {
                'todo' => 'todos',
                'project' => 'projects',
                'notebook' => 'notebooks',
                default => null,
            };
            if ($target === null) {
                throw new DomainException('Invalid research collection resource reference.');
            }
            $data['related_resource_id'] = $this->mappedId($target, $data['related_resource_id']);
        }
        if ($type === 'related_todos' && $data['todo_id'] > $data['related_todo_id']) {
            [$data['todo_id'], $data['related_todo_id']] = [$data['related_todo_id'], $data['todo_id']];
        }
        if ($type === 'activity_records') {
            if ($data['metadata'] !== null) {
                $metadata = json_decode($data['metadata'], true, 16, JSON_THROW_ON_ERROR);
                $safe = $this->safeMetadata($metadata, null);
                $keys = array_keys($safe);
                $originalKeys = array_keys($metadata);
                sort($keys);
                sort($originalKeys);
                if (
                    $keys !== $originalKeys
                    || (isset($metadata['fields']) && $safe['fields'] !== $metadata['fields'])
                ) {
                    throw new DomainException('Invalid activity metadata values or references.');
                }
                $data['metadata'] = json_encode($safe, JSON_THROW_ON_ERROR);
            }
            foreach (['subject', 'context'] as $pointer) {
                $id = $data[$pointer . '_id'];
                if ($id === null || $id === 0) {
                    continue;
                }
                $target = $this->subjectTable($data[$pointer . '_type']);
                if ($target === null) {
                    throw new DomainException('History pointer refers to an excluded record type.');
                }
                $data[$pointer . '_id'] = $this->mappedId($target, $id);
            }
            $data['actor_user_id'] = $userId;
        } elseif (
            in_array($type, ['projects', 'notebooks', 'libraries', 'tags', 'todos'], true)
            || in_array($type, ['reference_cards', 'cheat_sheets'], true)
        ) {
            $data['user_id'] = $userId;
        } elseif ($type === 'research_collections') {
            $data['user_id'] = $userId;
            $data['created_by_user_id'] = $userId;
        }
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $result = $this->connection->execute(
            "INSERT INTO $type ($columns) VALUES ($placeholders)" . ($oldId !== null ? ' RETURNING id' : ''),
            array_values($data),
        );
        if ($oldId !== null) {
            $row = $result->fetch('assoc');
            $this->connection->execute(
                'INSERT INTO portability_ids (kind, old_id, new_id) VALUES (?, ?, ?)',
                [$type, $oldId, $row['id']],
            );
        }
    }

    /**
     * Reject references outside this file, even when the numeric ID exists in the database.
     */
    private function mappedId(string $type, int $oldId): int
    {
        $row = $this->connection->execute(
            'SELECT new_id FROM portability_ids WHERE kind = ? AND old_id = ?',
            [$type, $oldId],
        )->fetch('assoc');
        if (!$row) {
            throw new DomainException('Reference is missing from this export.');
        }

        return (int)$row['new_id'];
    }

    /**
     * Self-references are resolved only after every ToDo has an ID map.
     */
    private function restoreParents(): void
    {
        $last = 0;
        do {
            $rows = $this->connection->execute(
                "SELECT seq, data FROM portability_rows WHERE kind = 'todos' AND seq > ? "
                . 'ORDER BY seq LIMIT ' . self::BATCH_SIZE,
                [$last],
            )->fetchAll('assoc');
            foreach ($rows as $row) {
                $last = (int)$row['seq'];
                $data = json_decode($row['data'], true, 32, JSON_THROW_ON_ERROR);
                if ($data['parent_todo_id'] !== null) {
                    $this->connection->execute(
                        'UPDATE todos SET parent_todo_id = ? WHERE id = ?',
                        [$this->mappedId('todos', $data['parent_todo_id']), $this->mappedId('todos', $data['id'])],
                    );
                }
            }
            $size = count($rows);
        } while ($size === self::BATCH_SIZE);
        $cycle = $this->connection->execute(
            'WITH RECURSIVE ancestry AS (SELECT i.new_id AS start, t.parent_todo_id AS parent, '
            . 'ARRAY[i.new_id] AS path, false AS cycle FROM portability_ids i JOIN todos t ON t.id = i.new_id '
            . "WHERE i.kind = 'todos' UNION ALL SELECT a.start, t.parent_todo_id, a.path || t.id, "
            . 't.id = ANY(a.path) FROM ancestry a JOIN todos t ON t.id = a.parent WHERE NOT a.cycle) '
            . 'SELECT 1 FROM ancestry WHERE cycle LIMIT 1',
        )->fetch('assoc');
        if ($cycle) {
            throw new DomainException('Cyclic ToDo parents.');
        }
    }

    /**
     * @param resource $stream Caller-owned stream.
     */
    private function assertStream(mixed $stream): void
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new DomainException('A stream resource is required.');
        }
    }
}
