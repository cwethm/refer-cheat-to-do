<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

class SetUserPasswordCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
    ];

    /**
     * Read the stored hash for an email address.
     */
    private function storedHash(string $email): string
    {
        /** @var \App\Model\Entity\User $user */
        $user = $this->getTableLocator()->get('Users')->find()->where(['email' => $email])->firstOrFail();

        return (string)$user->password;
    }

    /**
     * Move the users sequence past the fixture rows, which are inserted with explicit ids.
     */
    private function alignUsersSequence(): void
    {
        ConnectionManager::get('test')->execute(
            "SELECT setval(pg_get_serial_sequence('users', 'id'), COALESCE((SELECT MAX(id) FROM users), 1))",
        );
    }

    public function testResetsAnExistingPassword(): void
    {
        $this->exec('set_user_password owner@example.com --password new-owner-password');

        $this->assertExitSuccess();
        $this->assertOutputContains('Updated the password of `owner@example.com`');

        $hash = $this->storedHash('owner@example.com');
        $this->assertTrue(password_verify('new-owner-password', $hash));
        $this->assertFalse(password_verify('owner-password', $hash));
    }

    public function testStoresAHashInsteadOfThePlainPassword(): void
    {
        $this->exec('set_user_password owner@example.com --password new-owner-password');

        $this->assertNotSame('new-owner-password', $this->storedHash('owner@example.com'));
    }

    public function testCreatesAnAccountWithNormalizedEmail(): void
    {
        $this->alignUsersSequence();

        $this->exec('set_user_password "  New.Owner@Example.com " --password created-password --create');

        $this->assertExitSuccess();
        $this->assertOutputContains('Created `new.owner@example.com`');
        $this->assertTrue(password_verify('created-password', $this->storedHash('new.owner@example.com')));
    }

    public function testReadsThePasswordFromAPrompt(): void
    {
        $this->exec('set_user_password owner@example.com', ['prompted-password', 'prompted-password']);

        $this->assertExitSuccess();
        $this->assertTrue(password_verify('prompted-password', $this->storedHash('owner@example.com')));
    }

    public function testRejectsMismatchedPrompts(): void
    {
        $this->exec('set_user_password owner@example.com', ['prompted-password', 'different-password']);

        $this->assertExitError();
        $this->assertErrorContains('do not match');
        $this->assertTrue(password_verify('owner-password', $this->storedHash('owner@example.com')));
    }

    public function testRefusesUnknownAccountsWithoutCreate(): void
    {
        $this->exec('set_user_password nobody@example.com --password some-password');

        $this->assertExitError();
        $this->assertErrorContains('Pass --create');
        $this->assertSame(
            0,
            $this->getTableLocator()->get('Users')->find()->where(['email' => 'nobody@example.com'])->count(),
        );
    }

    public function testReportsValidationErrors(): void
    {
        $this->exec('set_user_password owner@example.com --password short');

        $this->assertExitError();
        $this->assertErrorContains('Could not save');
        $this->assertOutputContains('password.minLength');
        $this->assertTrue(password_verify('owner-password', $this->storedHash('owner@example.com')));
    }
}
