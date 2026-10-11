<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Model\Table\TodosTable;
use App\Service\ActivityRecorder;
use App\Service\DashboardService;
use App\Service\OrphanDetectionService;
use App\Service\ReviewSchedulingService;
use App\Service\TodoLifecycleService;
use App\Test\TestCase\SignedInSessionTrait;
use Cake\Chronos\Chronos;
use Cake\Database\Log\LoggedQuery;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\AbstractLogger;
use ReflectionProperty;
use Stringable;

class DashboardServiceTest extends TestCase
{
    use IntegrationTestTrait;
    use SignedInSessionTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Projects',
        'app.ProjectSections',
        'app.Notebooks',
        'app.NotebookSections',
        'app.Todos',
        'app.Tags',
        'app.TodosTags',
        'app.Libraries',
        'app.ActivityRecords',
    ];

    private TodosTable $todos;

    private DashboardService $dashboard;

    private ?Chronos $originalTestNow;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalTestNow = DateTime::getTestNow();
        DateTime::setTestNow(new DateTime('2026-03-31 12:00:00'));
        $tables = TableRegistry::getTableLocator();
        $this->todos = $tables->get('Todos');
        $this->dashboard = new DashboardService(
            $tables,
            new OrphanDetectionService(),
            new ActivityRecorder($tables),
            new ReviewSchedulingService($this->todos),
        );
    }

    protected function tearDown(): void
    {
        DateTime::setTestNow($this->originalTestNow);
        unset($this->todos, $this->dashboard);
        parent::tearDown();
    }

    /**
     * @return array<string, array{array<string, mixed>, bool, int, int}>
     */
    public static function boundaryProvider(): array
    {
        return [
            'organized project, null schedule' => [[], true, 0, 0],
            'organized notebook' => [
                ['project_section_id' => null, 'notebook_section_id' => 500], true, 0, 0,
            ],
            'no section' => [['project_section_id' => null], true, 0, 1],
            'no tags' => [[], false, 0, 1],
            'review before now' => [['next_review_at' => '2026-03-31 11:59:59'], true, 1, 1],
            'review exactly now' => [['next_review_at' => '2026-03-31 12:00:00'], true, 1, 1],
            'review after now' => [['next_review_at' => '2026-03-31 12:00:01'], true, 0, 0],
            'stale before cutoff' => [['last_reviewed_at' => '2026-03-01 11:59:59'], true, 0, 1],
            'stale exactly cutoff' => [['last_reviewed_at' => '2026-03-01 12:00:00'], true, 0, 1],
            'fresh after cutoff' => [['last_reviewed_at' => '2026-03-01 12:00:01'], true, 0, 0],
            'old creation, recent review' => [
                ['created' => '2000-01-01 00:00:00'], true, 0, 0,
            ],
            'never reviewed, fresh creation' => [['last_reviewed_at' => null], true, 0, 1],
            'never reviewed, stale creation' => [
                ['last_reviewed_at' => null, 'created' => '2026-03-01 12:00:00'], true, 0, 1,
            ],
            'multiple reasons count once' => [
                [
                    'project_section_id' => null,
                    'last_reviewed_at' => null,
                    'next_review_at' => '2026-03-31 12:00:00',
                    'created' => '2000-01-01 00:00:00',
                ],
                false, 1, 1,
            ],
            'done excluded' => [
                ['status' => 'done', 'next_review_at' => '2000-01-01 00:00:00'], false, 0, 0,
            ],
            'archived excluded' => [
                [
                    'status' => 'archived',
                    'archived_at' => '2026-03-30 12:00:00',
                    'next_review_at' => '2000-01-01 00:00:00',
                ],
                false, 0, 0,
            ],
            'trashed excluded' => [
                [
                    'status' => 'trashed',
                    'trashed_at' => '2026-03-30 12:00:00',
                    'next_review_at' => '2000-01-01 00:00:00',
                ],
                false, 0, 0,
            ],
            'different owner excluded' => [
                ['user_id' => 2, 'next_review_at' => '2000-01-01 00:00:00'], false, 0, 0,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[DataProvider('boundaryProvider')]
    public function testReasoningBoundaries(array $overrides, bool $tagged, int $due, int $orphans): void
    {
        $this->insertTodo(1000, $overrides, $tagged);
        $this->todos->deleteAll(['Todos.id IN' => [10, 11]]);

        $counts = $this->dashboard->summarize(1)['counts'];

        $this->assertSame($due, $counts['review_due']);
        $this->assertSame($orphans, $counts['orphans']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function insertTodo(int $id, array $overrides = [], bool $tagged = true): void
    {
        $fields = array_replace([
            'id' => $id,
            'user_id' => 1,
            'title' => 'Dashboard item ' . $id,
            'status' => 'active',
            'project_section_id' => 300,
            'notebook_section_id' => null,
            'created' => '2026-03-30 12:00:00',
            'modified' => '2026-03-30 12:00:00',
            'last_reviewed_at' => '2026-03-30 12:00:00',
            'next_review_at' => null,
        ], $overrides);
        $this->todos->insertQuery()->insert(array_keys($fields))->values($fields)->execute()->closeCursor();
        if ($tagged) {
            $links = TableRegistry::getTableLocator()->get('TodosTags');
            $links->insertQuery()->insert(['todo_id', 'tag_id'])
                ->values(['todo_id' => $id, 'tag_id' => 100])
                ->values(['todo_id' => $id, 'tag_id' => 101])
                ->execute()->closeCursor();
        }
    }

    public function testSummaryBytesMatchLegacyReasoning(): void
    {
        foreach (array_values(self::boundaryProvider()) as $index => [$overrides, $tagged]) {
            $this->insertTodo(1000 + $index, $overrides, $tagged);
        }
        foreach ([1, 2, 3] as $userId) {
            foreach ([1, 5, 50, 9999, 0] as $limit) {
                $this->assertSame(
                    json_encode($this->legacySummary($userId, $limit), JSON_THROW_ON_ERROR),
                    json_encode($this->dashboard->summarize($userId, $limit), JSON_THROW_ON_ERROR),
                );
            }
        }
    }

    public function testResponseBodyBytesMatchLegacySummary(): void
    {
        foreach (array_values(self::boundaryProvider()) as $index => [$overrides, $tagged]) {
            $this->insertTodo(1000 + $index, $overrides, $tagged);
        }
        $this->signIn(1);
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $expected = json_encode(
            ['data' => $this->legacySummary(1, 5)],
            JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
        );

        $this->get('/api/dashboard');

        $this->assertResponseOk();
        $this->assertSame($expected, (string)$this->_response?->getBody());
    }

    /**
     * The pre-aggregation dashboard algorithm, including ordering and serialization.
     *
     * @return array<string, mixed>
     */
    private function legacySummary(int $userId, int $limit): array
    {
        $limit = max(1, min($limit, DashboardService::MAX_RECENT_LIMIT));
        $tables = TableRegistry::getTableLocator();
        $orphans = new OrphanDetectionService();
        $reviews = new ReviewSchedulingService($this->todos);
        $now = DateTime::now();
        $dueCount = $orphanCount = 0;
        foreach (
            $this->todos->find('withTags')->where([
                'Todos.user_id' => $userId,
                'Todos.status NOT IN' => OrphanDetectionService::EXCLUDED_STATUSES,
            ]) as $todo
        ) {
            $dueCount += (int)$reviews->isDue($todo, $now);
            $orphanCount += (int)($orphans->reasonsFor($todo, $now) !== []);
        }
        $recent = ['todos' => []];
        foreach (
            $this->todos->find()->where([
                'Todos.user_id' => $userId,
                'Todos.status NOT IN' => TodoLifecycleService::HIDDEN_BY_DEFAULT,
            ])->orderBy(['Todos.modified' => 'DESC', 'Todos.id' => 'DESC'])->limit($limit) as $todo
        ) {
            $recent['todos'][] = [
                'id' => (int)$todo->id,
                'title' => (string)$todo->title,
                'status' => (string)$todo->status,
                'modified' => $todo->modified?->format(DATE_ATOM),
            ];
        }
        foreach (['projects' => 'Projects', 'notebooks' => 'Notebooks', 'libraries' => 'Libraries'] as $key => $name) {
            $recent[$key] = [];
            foreach (
                $tables->get($name)->find()->where([$name . '.user_id' => $userId])
                    ->orderBy([$name . '.id' => 'DESC'])->limit($limit) as $row
            ) {
                $recent[$key][] = ['id' => (int)$row->get('id'), 'name' => (string)$row->get('name')];
            }
        }
        $recent['activity'] = [];
        foreach ((new ActivityRecorder($tables))->forActor($userId)->limit($limit) as $record) {
            $recent['activity'][] = [
                'id' => (int)$record->id,
                'action' => (string)$record->action,
                'subject_type' => (string)$record->subject_type,
                'subject_id' => (int)$record->subject_id,
                'created' => $record->created?->format(DATE_ATOM),
            ];
        }

        return [
            'counts' => [
                'inbox' => $this->todos->find()->where(['Todos.user_id' => $userId, 'Todos.status' => 'inbox'])->count(),
                'active' => $this->todos->find()->where(['Todos.user_id' => $userId, 'Todos.status' => 'active'])->count(),
                'review_due' => $dueCount,
                'orphans' => $orphanCount,
            ],
            'recent' => $recent,
        ];
    }

    public function testQueryCountAndTransferredRowsStayBoundedAsTodosGrow(): void
    {
        $driver = $this->todos->getConnection()->getDriver();
        $logger = new class extends AbstractLogger {
            /**
             * @var list<array<string, mixed>>
             */
            public array $queries = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                if (($context['query'] ?? null) instanceof LoggedQuery) {
                    $this->queries[] = $context['query']->getContext();
                }
            }
        };
        $originalLogger = $driver->getLogger();
        $loggingProperty = new ReflectionProperty($driver, 'logQueries');
        $wasLogging = $loggingProperty->getValue($driver);
        $previousSize = 0;
        $queryCounts = [];
        try {
            foreach ([1, 100, 1000] as $size) {
                $driver->disableQueryLogging();
                for ($index = $previousSize; $index < $size; $index++) {
                    $this->insertTodo(1000 + $index, ['user_id' => 3], false);
                }
                // Resolve schemas and associations outside the measured requests.
                $this->dashboard->summarize(3);
                $driver->setLogger($logger);
                $driver->enableQueryLogging();
                $logger->queries = [];
                $summary = $this->dashboard->summarize(3);
                $queryCounts[] = count($logger->queries);
                $this->assertSame($size, $summary['counts']['active']);
                $this->assertSame($size, $summary['counts']['orphans']);
                $this->assertSame(6, count($logger->queries));
                $this->assertSame(1, $logger->queries[0]['numRows']);
                // numRows comes from the real PDO statement's rowCount(), not SQL text.
                // PostgreSQL may scan matching records; only transferred rows are bounded.
                $this->assertLessThanOrEqual(8, array_sum(array_column($logger->queries, 'numRows')));
                $this->assertSame(
                    1 + min($size, DashboardService::DEFAULT_RECENT_LIMIT),
                    array_sum(array_column($logger->queries, 'numRows')),
                );
                $previousSize = $size;
            }
            $this->assertSame([$queryCounts[0], $queryCounts[0], $queryCounts[0]], $queryCounts);
        } finally {
            (new ReflectionProperty($driver, 'logger'))->setValue($driver, $originalLogger);
            if ($wasLogging) {
                $driver->enableQueryLogging();
            } else {
                $driver->disableQueryLogging();
            }
        }
    }
}
