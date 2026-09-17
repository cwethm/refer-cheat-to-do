<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\DatabaseDiagnosticsService;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use RuntimeException;

class DatabaseDiagnosticsServiceTest extends TestCase
{
    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
    ];

    protected DatabaseDiagnosticsService $diagnostics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->diagnostics = new DatabaseDiagnosticsService();
    }

    protected function tearDown(): void
    {
        unset($this->diagnostics);

        parent::tearDown();
    }

    /**
     * Build facts for a healthy schema that the individual cases can modify.
     *
     * @return array<string, mixed>
     */
    private function facts(): array
    {
        return [
            'connection' => 'default',
            'configured_database' => 'refer_cheat_to_do',
            'configured_schema' => 'public',
            'current_database' => 'refer_cheat_to_do',
            'current_schema' => 'public',
            'current_user' => 'refer_cheat_to_do',
            'catalog_tables' => [
                'users' => 'refer_cheat_to_do',
                'todos' => 'refer_cheat_to_do',
            ],
            'catalog_columns' => [
                'users' => ['id', 'email', 'password'],
                'todos' => ['id', 'user_id', 'title'],
            ],
            'reflected_tables' => ['users', 'todos'],
            'reflected_columns' => [
                'users' => ['id', 'email', 'password'],
                'todos' => ['id', 'user_id', 'title'],
            ],
        ];
    }

    public function testEvaluateReportsHealthySchema(): void
    {
        $report = $this->diagnostics->evaluate($this->facts());

        $this->assertSame(DatabaseDiagnosticsService::STATUS_OK, $report['status']);
        $this->assertTrue($report['healthy']);
        $this->assertSame([], $report['problems']);
        $this->assertSame([], $report['remedies']);
        $this->assertSame([], $report['repair_sql']);
    }

    public function testEvaluateDetectsCatalogMismatchBeforeOtherCauses(): void
    {
        $facts = $this->facts();
        $facts['current_database'] = 'defaultdb';
        $facts['reflected_columns'] = [];

        $report = $this->diagnostics->evaluate($facts);

        $this->assertSame(DatabaseDiagnosticsService::STATUS_CATALOG_MISMATCH, $report['status']);
        $this->assertFalse($report['healthy']);
        $this->assertStringContainsString('refer_cheat_to_do', $report['problems'][0]);
        $this->assertStringContainsString('defaultdb', $report['problems'][0]);
        $this->assertStringContainsString('defaultdb', $report['remedies'][0]);
        $this->assertSame([], $report['repair_sql']);
    }

    public function testEvaluateDetectsHiddenColumnsAsPrivilegeProblem(): void
    {
        $facts = $this->facts();
        $facts['current_user'] = 'app_role';
        $facts['catalog_tables']['users'] = 'owner_role';
        $facts['reflected_columns']['users'] = [];

        $report = $this->diagnostics->evaluate($facts);

        $this->assertSame(DatabaseDiagnosticsService::STATUS_MISSING_PRIVILEGES, $report['status']);
        $this->assertStringContainsString('`users`', $report['problems'][0]);
        $this->assertContains('GRANT USAGE ON SCHEMA "public" TO "app_role";', $report['repair_sql']);
        $this->assertContains(
            'GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA "public" TO "app_role";',
            $report['repair_sql'],
        );
        $this->assertContains(
            'ALTER DEFAULT PRIVILEGES FOR ROLE "owner_role" IN SCHEMA "public" '
                . 'GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO "app_role";',
            $report['repair_sql'],
        );
        $this->assertStringContainsString('cache clear_all', end($report['remedies']));

        $users = null;
        foreach ($report['tables'] as $table) {
            if ($table['name'] === 'users') {
                $users = $table;
            }
        }
        $this->assertNotNull($users);
        $this->assertSame(['id', 'email', 'password'], $users['missing_columns']);
        $this->assertSame(3, $users['catalog_columns']);
        $this->assertSame(0, $users['reflected_columns']);
    }

    public function testEvaluateDetectsUnlistedTableAsPrivilegeProblem(): void
    {
        $facts = $this->facts();
        $facts['reflected_tables'] = ['todos'];

        $report = $this->diagnostics->evaluate($facts);

        $this->assertSame(DatabaseDiagnosticsService::STATUS_MISSING_PRIVILEGES, $report['status']);
        $this->assertStringContainsString('information_schema.tables', $report['problems'][0]);
    }

    public function testEvaluateDetectsMissingSchema(): void
    {
        $facts = $this->facts();
        $facts['catalog_tables'] = [];
        $facts['catalog_columns'] = [];
        $facts['reflected_tables'] = [];
        $facts['reflected_columns'] = [];

        $report = $this->diagnostics->evaluate($facts);

        $this->assertSame(DatabaseDiagnosticsService::STATUS_MISSING_SCHEMA, $report['status']);
        $this->assertStringContainsString('`users`', $report['problems'][0]);
        $this->assertStringContainsString('migrations migrate -c default', $report['remedies'][0]);
    }

    public function testEvaluateDoesNotGrantToTheOwnerItself(): void
    {
        $facts = $this->facts();
        $facts['reflected_columns']['users'] = [];

        $report = $this->diagnostics->evaluate($facts);

        foreach ($report['repair_sql'] as $statement) {
            $this->assertStringNotContainsString('ALTER DEFAULT PRIVILEGES', $statement);
        }
    }

    public function testCollectFactsReadsTheLiveSchema(): void
    {
        $facts = $this->diagnostics->collectFacts('test');
        $config = ConnectionManager::get('test')->config();

        $this->assertSame('test', $facts['connection']);
        $this->assertSame((string)$config['database'], $facts['configured_database']);
        $this->assertSame($facts['configured_database'], $facts['current_database']);
        $this->assertArrayHasKey('users', $facts['catalog_tables']);
        $this->assertContains('id', $facts['catalog_columns']['users']);
        $this->assertContains('users', $facts['reflected_tables']);
        $this->assertContains('id', $facts['reflected_columns']['users']);
    }

    public function testDiagnoseReportsTheTestConnectionAsHealthy(): void
    {
        $report = $this->diagnostics->diagnose('test');

        $this->assertSame(DatabaseDiagnosticsService::STATUS_OK, $report['status'], implode(
            ' ',
            $report['problems'],
        ));
        $this->assertTrue($report['healthy']);
    }

    public function testDiagnoseRejectsNonPostgresConnections(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not available.');
        }

        ConnectionManager::setConfig('diagnostics_sqlite', [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Sqlite',
            'database' => ':memory:',
        ]);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('does not use the PostgreSQL driver');
            $this->diagnostics->collectFacts('diagnostics_sqlite');
        } finally {
            ConnectionManager::drop('diagnostics_sqlite');
        }
    }
}
