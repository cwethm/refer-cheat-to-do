<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Datasource\ConnectionManager;
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

    public function testExplainsARejectedPassword(): void
    {
        $config = ConnectionManager::getConfig('test');
        $this->assertIsArray($config);
        unset($config['url']);
        ConnectionManager::setConfig('doctor_wrong_password', ['password' => 'definitely-not-the-password'] + $config);

        try {
            $this->exec('database_doctor --connection doctor_wrong_password');
        } finally {
            ConnectionManager::drop('doctor_wrong_password');
        }

        $this->assertExitError();
        $this->assertErrorContains('password authentication failed');
        $this->assertOutputContains('Connection `doctor_wrong_password` was configured as');
        $this->assertOutputContains('username       : ' . (string)$config['username']);
        $this->assertOutputContains('password       : set');
        $this->assertOutputNotContains('definitely-not-the-password');
        $this->assertOutputContains('does not exist on this server');
        $this->assertOutputContains('ALTER ROLE');
    }
}
