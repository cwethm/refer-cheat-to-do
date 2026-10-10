<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\ActivityRecorder;
use App\Service\CapabilityService;
use App\Service\DataPortabilityService;
use App\Service\TodoLifecycleService;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Database\Connection;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use DomainException;
use PHPUnit\Framework\AssertionFailedError;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;
use Throwable;

class DataPortabilityServiceTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users', 'app.Projects', 'app.ProjectSections', 'app.Notebooks', 'app.NotebookSections',
        'app.Libraries', 'app.Tags', 'app.Todos', 'app.TodosTags', 'app.RelatedTodos',
        'app.LibrariesProjects', 'app.LibrariesNotebooks', 'app.CapabilityGrants',
        'app.CrossContextRequests', 'app.ActivityRecords', 'app.ResearchCollections',
        'app.ResearchCollectionItems',
    ];

    /**
     * Application connection remapped by Cake's fixture manager.
     */
    private function connection(): Connection
    {
        return TableRegistry::getTableLocator()->get('Users')->getConnection();
    }

    public function testLargeResearchContentUsesBoundedChunksAndRestores(): void
    {
        $content = str_repeat('é🙂', 10000);
        $this->connection()->execute('UPDATE research_collection_items SET content=? WHERE id=990', [$content]);
        $records = $this->exported();
        $chunks = array_values(array_filter(
            $this->ofType($records, 'text_chunks'),
            static fn(array $row): bool => $row['record_type'] === 'research_collection_items',
        ));
        $this->assertNotEmpty($chunks);
        $stream = $this->encoded($records);
        try {
            $this->service()->restore(3, $stream);
        } finally {
            fclose($stream);
        }
        $restored = $this->connection()->execute(
            'SELECT i.content FROM research_collection_items i '
            . 'JOIN research_collections c ON c.id=i.research_collection_id WHERE c.user_id=3',
        )->fetch('assoc');
        $this->assertSame($content, $restored['content']);
    }

    /**
     * Service under test.
     */
    private function service(): DataPortabilityService
    {
        return new DataPortabilityService(TableRegistry::getTableLocator());
    }

    /**
     * @return resource
     */
    private function stream(string $contents = ''): mixed
    {
        $stream = fopen('php://memory', 'w+b');
        $this->assertIsResource($stream);
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exported(int $userId = 1): array
    {
        $stream = $this->stream();
        try {
            $this->service()->writeExport($userId, $stream);
            rewind($stream);
            $rows = [];
            while (($line = fgets($stream)) !== false) {
                $rows[] = json_decode($line, true, 32, JSON_THROW_ON_ERROR);
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param list<array<string, mixed>> $records JSONL records.
     * @return resource
     */
    private function encoded(array $records): mixed
    {
        return $this->stream(implode("\n", array_map(
            static fn(array $row): string => json_encode($row, JSON_THROW_ON_ERROR),
            $records,
        )) . "\n");
    }

    /**
     * @param list<array<string, mixed>> $records JSONL records.
     * @return list<array<string, mixed>>
     */
    private function ofType(array $records, string $type): array
    {
        return array_values(array_map(
            static fn(array $row): array => $row['data'],
            array_filter($records, static fn(array $row): bool => ($row['type'] ?? null) === $type),
        ));
    }

    /**
     * Complete source data plus deliberately unsafe historical references and edges.
     */
    private function populate(): void
    {
        $db = $this->connection();
        $db->execute(
            "UPDATE todos SET status='done', project_section_id=300, "
            . "terminal_objective='Complete objective', objective_satisfied_at='2026-01-02 03:04:05', "
            . "result_summary='Result', last_reviewed_at='2026-02-01 01:02:03', "
            . "next_review_at='2026-03-01 01:02:03', review_interval_days=28 WHERE id=10",
        );
        $db->execute('UPDATE todos SET parent_todo_id=10, notebook_section_id=500, '
            . "status='archived', previous_status='active', "
            . "archived_at='2026-02-02 01:02:03' WHERE id=11");
        $db->execute('INSERT INTO todos (id,user_id,title,status,trashed_at,previous_status,created,modified) '
            . "VALUES (13,1,'Trashed record','trashed','2026-02-01 01:02:03','inbox',"
            . "'2026-01-01 00:00:00.123456','2026-02-01 01:02:03.456789')");
        $db->execute("SELECT setval(pg_get_serial_sequence('todos','id'), (SELECT MAX(id) FROM todos))");
        $db->execute('INSERT INTO related_todos (todo_id, related_todo_id, created) '
            . "VALUES (10,11,'2026-02-03 01:02:03'), (10,12,'2026-02-03 01:02:03')");
        $db->execute('INSERT INTO libraries_projects (library_id, project_id, created) '
            . "VALUES (600,200,'2026-02-03 01:02:03'), (600,201,'2026-02-03 01:02:03')");
        $db->execute('INSERT INTO libraries_notebooks (library_id, notebook_id, created) '
            . "VALUES (600,400,'2026-02-03 01:02:03'), (600,401,'2026-02-03 01:02:03')");
        $db->execute('INSERT INTO todos_tags (todo_id, tag_id) VALUES (10,102), (12,100)');
        $recorder = new ActivityRecorder(TableRegistry::getTableLocator());
        $recorder->record(1, 'todo.updated', 'todo', 10, ['status' => 'done', 'tag_id' => 100], 'project', 200);
        $recorder->record(1, 'project.read', 'project', 201, ['foreign_id' => 201], 'notebook', 401);
        $recorder->record(
            1,
            'research_collection.updated',
            'research_collection',
            900,
            [],
            'research_collection_item',
            990,
        );
        $recorder->record(
            1,
            'research_collection_item.updated',
            'research_collection_item',
            990,
            [],
            'research_collection',
            900,
        );
        $recorder->record(2, 'todo.updated', 'todo', 12);
        $db->execute("UPDATE activity_records SET metadata='{\"password\":\"sensitive-history\"}' "
            . "WHERE actor_user_id=1 AND action='project.read'");
    }

    /**
     * Every record type, both endpoints of edges, and actor-only history.
     */
    public function testCompleteOwnerOnlyExportWithCapabilities(): void
    {
        $this->populate();
        $capabilities = new CapabilityService(TableRegistry::getTableLocator());
        $capabilities->grant(2, 1, 'read', 'project', 201);
        $capabilities->grant(1, 2, 'read', 'project', 200);
        $capabilities->grant(2, 1, 'read', 'notebook', 401);
        $capabilities->grant(2, 1, 'read', 'library', 602);
        $records = $this->exported();
        $this->assertSame('urn:cheat-to-do:export:1', DataPortabilityService::SCHEMA);
        $this->assertSame(['schema' => DataPortabilityService::SCHEMA], $records[0]);
        $expected = [
            'projects' => 1, 'project_sections' => 3, 'notebooks' => 1, 'notebook_sections' => 3,
            'libraries' => 2, 'research_collections' => 1, 'research_collection_items' => 1,
            'tags' => 2, 'todos' => 3, 'todos_tags' => 2, 'related_todos' => 1,
            'libraries_projects' => 1, 'libraries_notebooks' => 1, 'activity_records' => 4,
        ];
        foreach ($expected as $type => $count) {
            $this->assertCount($count, $this->ofType($records, $type), $type);
        }
        $this->assertSame([200], array_column($this->ofType($records, 'projects'), 'id'));
        $this->assertSame([201], array_column($this->ofType($this->exported(2), 'projects'), 'id'));
        $this->assertSame([12], array_column($this->ofType($this->exported(2), 'todos'), 'id'));
        $json = json_encode($records, JSON_THROW_ON_ERROR);
        foreach (['password', 'token', 'credential', 'sensitive-history', 'actor_user_id', 'user_id'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $this->assertSame([], $this->ofType($records, 'capability_grants'));
        $this->assertSame([], $this->ofType($records, 'cross_context_requests'));
        foreach ($this->ofType($records, 'activity_records') as $history) {
            if ($history['action'] === 'todo.updated') {
                $this->assertSame(['status' => 'done', 'tag_id' => 100], json_decode($history['metadata'], true));
            }
            if ($history['action'] === 'project.read') {
                $this->assertNull($history['metadata']);
                $this->assertSame(0, $history['subject_id']);
                $this->assertNull($history['context_type']);
                $this->assertNull($history['context_id']);
            }
        }
        $this->assertSame(['end' => true, 'records' => count($records) - 2], end($records));
    }

    /**
     * Dangling, capability-visible, and foreign pointers cannot leak IDs.
     */
    public function testForeignTodoPointersAreRedacted(): void
    {
        $this->connection()->execute(
            'UPDATE todos SET project_section_id=303, parent_todo_id=12 WHERE id=10',
        );
        $this->connection()->execute('UPDATE todos SET notebook_section_id=503 WHERE id=11');
        $rows = $this->ofType($this->exported(), 'todos');
        foreach (['project_section_id', 'notebook_section_id', 'parent_todo_id'] as $field) {
            $this->assertNull($rows[0][$field]);
            $this->assertNull($rows[1][$field]);
        }
    }

    public function testForeignResearchItemReferenceIsRedacted(): void
    {
        $this->connection()->execute(
            "UPDATE research_collection_items SET related_resource_type='todo', related_resource_id=12 WHERE id=990",
        );

        $item = $this->ofType($this->exported(), 'research_collection_items')[0];

        $this->assertNull($item['related_resource_type']);
        $this->assertNull($item['related_resource_id']);
    }

    /**
     * Round trip restores all record types with original data and remapped relationships.
     */
    public function testRoundTripAndTwoPassParentRestore(): void
    {
        $this->populate();
        // Parent sorts after child: a one-pass importer cannot resolve this.
        $this->connection()->execute('UPDATE todos SET parent_todo_id=null WHERE id=11');
        $this->connection()->execute('UPDATE todos SET parent_todo_id=11 WHERE id=10');
        $source = $this->exported();
        $stream = $this->encoded($source);
        try {
            $this->service()->restore(3, $stream);
        } finally {
            fclose($stream);
        }
        $target = $this->exported(3);
        $this->assertCount(count($source), $target);
        $idMaps = [];
        foreach ($source as $row) {
            if (!isset($row['type'], $row['data']['id'])) {
                continue;
            }
            $sourceRows = $this->ofType($source, $row['type']);
            $targetRows = $this->ofType($target, $row['type']);
            $idMaps[$row['type']] = array_combine(
                array_column($sourceRows, 'id'),
                array_column($targetRows, 'id'),
            );
        }
        $references = [
            'project_id' => 'projects', 'notebook_id' => 'notebooks', 'library_id' => 'libraries',
            'todo_id' => 'todos', 'related_todo_id' => 'todos', 'tag_id' => 'tags',
            'project_section_id' => 'project_sections', 'notebook_section_id' => 'notebook_sections',
            'parent_todo_id' => 'todos', 'research_collection_id' => 'research_collections',
        ];
        foreach ($source as $index => $row) {
            if (!isset($row['type'])) {
                $this->assertSame($row, $target[$index]);
                continue;
            }
            $expected = $row;
            foreach ($expected['data'] as $field => $value) {
                if ($field === 'id') {
                    $expected['data'][$field] = $idMaps[$row['type']][$value];
                } elseif (isset($references[$field]) && $value !== null) {
                    $expected['data'][$field] = $idMaps[$references[$field]][$value];
                } elseif ($field === 'related_resource_id' && $value !== null) {
                    $type = $row['data']['related_resource_type'];
                    $referenceTable = match ($type) {
                        'todo' => 'todos',
                        'project' => 'projects',
                        'notebook' => 'notebooks',
                    };
                    $expected['data'][$field] = $idMaps[$referenceTable][$value];
                } elseif (in_array($field, ['subject_id', 'context_id'], true) && $value) {
                    $kind = $row['data'][str_replace('_id', '_type', $field)];
                    $referenceTable = match ($kind) {
                        'library' => 'libraries',
                        'research_collection' => 'research_collections',
                        'research_collection_item' => 'research_collection_items',
                        default => $kind . 's',
                    };
                    $expected['data'][$field] = $idMaps[$referenceTable][$value];
                } elseif ($field === 'metadata' && $value !== null) {
                    $metadata = json_decode($value, true);
                    if (isset($metadata['tag_id'])) {
                        $metadata['tag_id'] = $idMaps['tags'][$metadata['tag_id']];
                    }
                    $expected['data'][$field] = json_encode($metadata);
                }
            }
            $this->assertSame($expected, $target[$index], $row['type']);
        }
        $this->assertSame($source, $this->exported(1));
    }

    /**
     * JSON object key ordering carries no semantic meaning.
     */
    public function testReorderedObjectKeysRestore(): void
    {
        $source = $this->exported();
        $records = array_map(static fn(array $row): array => array_reverse($row, true), $source);
        $stream = $this->encoded($records);
        try {
            $this->service()->restore(3, $stream);
        } finally {
            fclose($stream);
        }
        $this->assertCount(count($source), $this->exported(3));
    }

    /**
     * Credentials and foreign IDs in metadata are redacted; restored references remain file-local.
     */
    public function testMetadataSafetyAndReferenceValidation(): void
    {
        $recorder = new ActivityRecorder(TableRegistry::getTableLocator());
        $recorder->record(1, 'todo.updated', 'todo', 10, [
            'status' => 'inbox', 'tag_id' => 102, 'resource_type' => 'project', 'resource_id' => 201,
            'subject_user_id' => 2, 'unknown' => ['password' => 'secret'],
            'password_hash' => 'forbidden-hash', 'access_token' => 'forbidden-token',
            'fields' => ['title', 'notes'],
        ]);
        $source = $this->exported();
        $history = $this->ofType($source, 'activity_records')[0];
        $this->assertSame(['status' => 'inbox', 'fields' => ['title', 'notes']], json_decode($history['metadata'], true));
        foreach (
            [
            ['password' => 'forbidden'],
            ['password_hash' => 'forbidden-hash'],
            ['access_token' => 'forbidden-token'],
            ['tag_id' => 102],
            ['fields' => ['password']],
            ['resource_type' => 'project', 'resource_id' => 201],
            ['status' => ['password' => 'forbidden']],
            ] as $unsafe
        ) {
            $bad = $source;
            foreach ($bad as &$row) {
                if (($row['type'] ?? null) === 'activity_records') {
                    $row['data']['metadata'] = json_encode($unsafe);
                }
            }
            unset($row);
            $stream = $this->encoded($bad);
            try {
                $this->service()->restore(3, $stream);
                $this->fail('Unsafe activity metadata was restored.');
            } catch (Throwable $exception) {
                $this->assertNotInstanceOf(AssertionFailedError::class, $exception);
            } finally {
                fclose($stream);
            }
            $this->assertCount(2, $this->exported(3));
        }
    }

    /**
     * Input ordering is irrelevant; related pairs remain canonical after generated ID remapping.
     */
    public function testShuffledTodoOrderRestoresCanonicalRelatedPair(): void
    {
        $this->populate();
        $records = $this->exported();
        $positions = [];
        foreach ($records as $index => $record) {
            if (($record['type'] ?? null) === 'todos') {
                $positions[] = $index;
            }
        }
        [$records[$positions[0]], $records[$positions[1]]] = [$records[$positions[1]], $records[$positions[0]]];
        $stream = $this->encoded($records);
        try {
            $this->service()->restore(3, $stream);
        } finally {
            fclose($stream);
        }
        $related = $this->ofType($this->exported(3), 'related_todos')[0];
        $this->assertLessThan($related['related_todo_id'], $related['todo_id']);
    }

    /**
     * More than two batches of both scalar and composite keys.
     */
    public function testBoundedKeysetPagination(): void
    {
        $db = $this->connection();
        (new ActivityRecorder(TableRegistry::getTableLocator()))->record(1, 'todo.updated', 'todo', 10);
        $db->execute('UPDATE activity_records SET metadata=? WHERE actor_user_id=1', [
            str_repeat('x', DataPortabilityService::MAX_LINE_BYTES * 2),
        ]);
        for ($id = 1000; $id < 1235; $id++) {
            $db->execute("INSERT INTO todos (id,user_id,title,status) VALUES (?,1,'Batch','inbox')", [$id]);
            $db->execute('INSERT INTO todos_tags (todo_id,tag_id) VALUES (?,100),(?,101)', [$id, $id]);
        }
        $db->execute("SELECT setval(pg_get_serial_sequence('todos','id'), (SELECT MAX(id) FROM todos))");
        $logger = new class implements LoggerInterface {
            use LoggerTrait;

            /**
             * @var list<string>
             */
            public array $queries = [];

            /**
             * @param mixed $level Log level.
             * @param string|\Stringable $message Query message.
             * @param array<string, mixed> $context Query context.
             */
            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->queries[] = (string)$message;
            }
        };
        $driver = $db->getDriver();
        $previous = $driver->getLogger();
        $driver->setLogger($logger);
        $driver->enableQueryLogging();
        try {
            $records = $this->exported();
        } finally {
            $driver->disableQueryLogging();
            if ($previous !== null) {
                $driver->setLogger($previous);
            }
        }
        $todos = $this->ofType($records, 'todos');
        $edges = $this->ofType($records, 'todos_tags');
        $this->assertCount(237, $todos);
        $this->assertCount(472, $edges);
        $this->assertCount(237, array_unique(array_column($todos, 'id')));
        $batchQueries = array_values(array_filter(
            $logger->queries,
            static fn(string $sql): bool => str_contains($sql, 'SELECT r.'),
        ));
        $this->assertGreaterThan(10, count($batchQueries));
        foreach ($batchQueries as $sql) {
            $this->assertStringContainsString('LIMIT 100', $sql);
            $this->assertStringNotContainsString('OFFSET', $sql);
            $this->assertStringNotContainsString('SELECT *', $sql);
        }
        $this->assertStringContainsString(
            'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY',
            implode("\n", $logger->queries),
        );
        $activityQueries = array_values(array_filter(
            $logger->queries,
            static fn(string $sql): bool => str_contains($sql, 'FROM activity_records'),
        ));
        $this->assertNotEmpty($activityQueries);
        foreach ($activityQueries as $sql) {
            $this->assertStringContainsString('octet_length(ActivityRecords.metadata) <= 8192', $sql);
            $this->assertStringContainsString('LIMIT 100', $sql);
        }
        $this->assertNull($this->ofType($records, 'activity_records')[0]['metadata']);
        $this->assertStringContainsString('(r.todo_id,r.tag_id) >', implode("\n", $batchQueries));
        $stream = $this->encoded($records);
        try {
            $this->service()->restore(3, $stream);
        } finally {
            fclose($stream);
        }
        $this->assertCount(237, $this->ofType($this->exported(3), 'todos'));
        $this->assertCount(472, $this->ofType($this->exported(3), 'todos_tags'));
    }

    /**
     * Failure rolls back every table and leaves source/other users untouched.
     */
    public function testStrictValidationAndRollback(): void
    {
        $original = $this->exported();
        $cases = [];
        $bad = $original;
        $bad[0]['schema'] = 'unknown';
        $cases[] = $bad;
        $bad = $original;
        $bad[1]['data']['password'] = 'forbidden';
        $cases[] = $bad;
        $bad = $original;
        $bad[1]['data']['id'] = '200';
        $cases[] = $bad;
        $bad = $original;
        $bad[1]['data']['created'] = '2026-02-30 00:00:00';
        $cases[] = $bad;
        $bad = $original;
        foreach ($bad as &$row) {
            if (($row['type'] ?? null) === 'todos') {
                $row['data']['project_section_id'] = 303;
                break;
            }
        }
        unset($row);
        $cases[] = $bad;
        $bad = $original;
        foreach ($bad as &$row) {
            if (($row['type'] ?? null) === 'todos') {
                $row['data']['parent_todo_id'] = $row['data']['id'] === 10 ? 11 : 10;
            }
        }
        unset($row);
        $cases[] = $bad;
        $bad = $original;
        $bad[1]['type'] = 'users';
        $cases[] = $bad;
        $bad = $original;
        $bad[] = ['unexpected' => true];
        $cases[] = $bad;
        $bad = $original;
        array_pop($bad);
        $cases[] = $bad;
        $bad = $original;
        $bad[count($bad) - 1]['records']++;
        $cases[] = $bad;
        $bad = $original;
        array_splice($bad, 2, 0, [$bad[1]]);
        $bad[count($bad) - 1]['records']++;
        $cases[] = $bad;
        foreach ($cases as $index => $records) {
            $stream = $this->encoded($records);
            try {
                $this->service()->restore(3, $stream);
                $this->fail("Invalid case $index was accepted.");
            } catch (Throwable $exception) {
                $this->assertNotInstanceOf(AssertionFailedError::class, $exception);
            } finally {
                fclose($stream);
            }
            $this->assertSame(
                [['schema' => DataPortabilityService::SCHEMA], ['end' => true, 'records' => 0]],
                $this->exported(3),
            );
            $this->assertSame($original, $this->exported());
        }
    }

    /**
     * Malformed, oversized and truncated inputs are bounded and rejected.
     */
    public function testMalformedAndTruncatedInput(): void
    {
        foreach (
            [
            '', "{}\n", '{"schema":', "{broken}\n", str_repeat('x', DataPortabilityService::MAX_LINE_BYTES + 1),
            json_encode(['schema' => DataPortabilityService::SCHEMA]) . "\n" . '{"end":true,"records":0}',
            ] as $contents
        ) {
            $stream = $this->stream($contents);
            try {
                $this->service()->restore(3, $stream);
                $this->fail('Malformed input accepted.');
            } catch (Throwable $exception) {
                $this->assertNotInstanceOf(AssertionFailedError::class, $exception);
            } finally {
                fclose($stream);
            }
            $this->assertCount(2, $this->exported(3));
        }
    }

    /**
     * Existing user data is never merged, overwritten, or deleted.
     */
    public function testNonEmptyTargetRejectedWithoutChanges(): void
    {
        $before = $this->exported(2);
        $stream = $this->encoded($this->exported());
        try {
            $this->service()->restore(2, $stream);
            $this->fail('Non-empty target accepted.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('empty', $exception->getMessage());
        } finally {
            fclose($stream);
        }
        $this->assertSame($before, $this->exported(2));
    }

    /**
     * No implicit anonymous or missing account access.
     */
    public function testMissingUserRejected(): void
    {
        $stream = $this->stream();
        try {
            $this->expectException(DomainException::class);
            $this->service()->writeExport(0, $stream);
        } finally {
            fclose($stream);
        }
    }

    /**
     * The operator workflow uses exclusive private files and transactional restoration.
     */
    public function testOperatorCommands(): void
    {
        $path = getcwd() . '/.portability-test-' . bin2hex(random_bytes(8)) . '.jsonl';
        try {
            $this->exec('export_user_data owner@example.com ' . $path);
            $this->assertExitSuccess();
            $this->assertSame(0600, fileperms($path) & 0777);
            $contents = file_get_contents($path);
            $this->exec('export_user_data other@example.com ' . $path);
            $this->assertExitError();
            $this->assertSame($contents, file_get_contents($path));
            $this->exec('restore_user_data third@example.com ' . $path);
            $this->assertExitSuccess();
            $this->assertCount(count($this->exported()), $this->exported(3));
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Actor history alone makes a target non-empty; orphaned pointers are still history.
     */
    public function testHistoryOnlyTargetIsNotEmpty(): void
    {
        (new ActivityRecorder(TableRegistry::getTableLocator()))->record(3, 'todo.deleted', 'todo', 999999);
        $before = $this->exported(3);
        $stream = $this->encoded($this->exported());
        try {
            $this->service()->restore(3, $stream);
            $this->fail('History-only target accepted.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('empty', $exception->getMessage());
        } finally {
            fclose($stream);
        }
        $this->assertSame($before, $this->exported(3));
    }

    /**
     * The lifecycle authority clears archived_at when trashing but retains previous_status.
     */
    public function testArchiveThenTrashRoundTrip(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $lifecycle = new TodoLifecycleService($todos);
        $todo = $todos->get(10);
        $lifecycle->transition($todo, 'archived');
        $lifecycle->transition($todo, 'trashed');
        $source = $this->ofType($this->exported(), 'todos')[0];
        $this->assertSame('archived', $source['previous_status']);
        $this->assertNull($source['archived_at']);
        $stream = $this->encoded($this->exported());
        try {
            $this->service()->restore(3, $stream);
        } finally {
            fclose($stream);
        }
        $target = $this->ofType($this->exported(3), 'todos')[0];
        unset($source['id'], $target['id']);
        $this->assertSame($source, $target);
    }

    /**
     * Large unrestricted text is emitted in bounded chunks and restored without loss.
     */
    public function testLargeTextRoundTrip(): void
    {
        $text = str_repeat('é漢🙂', 120000);
        $this->connection()->execute('UPDATE todos SET notes=? WHERE id=10', [
            $text,
        ]);
        $records = $this->exported();
        $chunks = $this->ofType($records, 'text_chunks');
        $this->assertGreaterThan(10, count($chunks));
        $this->assertSame(['chunks' => mb_strlen($text)], $this->ofType($records, 'todos')[0]['notes']);
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(DataPortabilityService::TEXT_CHUNK_CHARS, mb_strlen($chunk['value']));
        }
        $stream = $this->encoded($records);
        try {
            $this->service()->restore(3, $stream);
        } finally {
            fclose($stream);
        }
        $restored = $this->connection()->execute('SELECT notes FROM todos WHERE user_id=3 ORDER BY id LIMIT 1')
            ->fetch('assoc');
        $this->assertSame($text, $restored['notes']);
        $this->connection()->execute('UPDATE todos SET parent_todo_id=NULL WHERE user_id=3');
        $this->connection()->execute('DELETE FROM todos WHERE user_id=3');
        $this->connection()->execute('DELETE FROM project_sections WHERE project_id IN (SELECT id FROM projects WHERE user_id=3)');
        $this->connection()->execute('DELETE FROM notebook_sections WHERE notebook_id IN (SELECT id FROM notebooks WHERE user_id=3)');
        $this->connection()->execute(
            'DELETE FROM research_collection_items WHERE research_collection_id IN '
            . '(SELECT id FROM research_collections WHERE user_id=3)',
        );
        foreach (['projects', 'notebooks', 'libraries', 'tags'] as $type) {
            $this->connection()->execute("DELETE FROM $type WHERE user_id=3");
        }
        $this->connection()->execute('DELETE FROM research_collections WHERE user_id=3');
        $bad = $records;
        foreach ($bad as $index => $row) {
            if (($row['type'] ?? null) === 'text_chunks') {
                array_splice($bad, $index, 1);
                break;
            }
        }
        $bad[count($bad) - 1]['records']--;
        $stream = $this->encoded($bad);
        try {
            $this->service()->restore(3, $stream);
            $this->fail('Incomplete text chunks restored.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('chunks', $exception->getMessage());
        } finally {
            fclose($stream);
        }
        $this->assertCount(2, $this->exported(3));
        $path = getcwd() . '/.portability-test-' . bin2hex(random_bytes(8)) . '.jsonl';
        try {
            $this->exec('export_user_data owner@example.com ' . $path);
            $this->assertExitSuccess();
            $this->assertGreaterThan(DataPortabilityService::MAX_LINE_BYTES, filesize($path));
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Operator export cannot follow or remove an existing symlink.
     */
    public function testOperatorDoesNotOverwriteSymlink(): void
    {
        $path = getcwd() . '/.portability-test-' . bin2hex(random_bytes(8)) . '.jsonl';
        symlink('nonexistent-portability-target', $path);
        try {
            $this->exec('export_user_data owner@example.com ' . $path);
            $this->assertExitError();
            $this->assertTrue(is_link($path));
            $this->assertSame('nonexistent-portability-target', readlink($path));
        } finally {
            unlink($path);
        }
    }
}
