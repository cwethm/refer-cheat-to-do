<?php
declare(strict_types=1);

namespace App\Test\TestCase\Migrations;

use Cake\TestSuite\TestCase;

class CreateUsersMigrationDefinitionTest extends TestCase
{
    public function testCreateUsersMigrationIncludesCaseInsensitiveUniqueIndex(): void
    {
        $path = dirname(__DIR__, 3) . '/config/Migrations/20260911070000_CreateUsers.php';
        $contents = (string)file_get_contents($path);

        $this->assertStringContainsString('LOWER(email)', $contents);
        $this->assertStringContainsString('users_email_lower_unique', $contents);
    }

    public function testCreateUsersMigrationFailsFastOutsidePostgres(): void
    {
        $path = dirname(__DIR__, 3) . '/config/Migrations/20260911070000_CreateUsers.php';
        $contents = (string)file_get_contents($path);

        $this->assertStringContainsString('requires PostgreSQL', $contents);
    }
}
