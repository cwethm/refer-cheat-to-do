<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

class DatabaseDoctorCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
    ];

    public function testReportsAHealthyConnection(): void
    {
        $this->exec('database_doctor --connection test');

        $this->assertExitSuccess();
        $this->assertOutputContains('users');
        $this->assertOutputContains('The schema is fully visible to the connecting role.');
    }

    public function testShowsTheConnectionIdentity(): void
    {
        $this->exec('database_doctor --connection test');

        $this->assertOutputContains('configured database');
        $this->assertOutputContains('current_database()');
        $this->assertOutputContains('current_user');
    }

    public function testFailsForAnUnknownConnection(): void
    {
        $this->exec('database_doctor --connection missing_connection');

        $this->assertExitError();
        $this->assertErrorContains('missing_connection');
    }
}
