<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AccountTokenService;
use Cake\Chronos\Chronos;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

class AccountTokenServiceTest extends TestCase
{
    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.AccountTokens',
    ];

    private AccountTokenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AccountTokenService($this->getTableLocator());
        // Fixture users are inserted with explicit ids; keep the sequence ahead of them.
        ConnectionManager::get('test')->execute(
            "SELECT setval(pg_get_serial_sequence('users', 'id'), COALESCE((SELECT MAX(id) FROM users), 1))",
        );
    }

    protected function tearDown(): void
    {
        Chronos::setTestNow(Chronos::now());
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function tokenRow(string $plainToken): array
    {
        $row = $this->getTableLocator()->get('AccountTokens')->find()
            ->where(['token_hash' => hash('sha256', $plainToken)])
            ->disableHydration()
            ->first();
        $this->assertIsArray($row);

        return $row;
    }

    private function credentialVersion(int $userId): int
    {
        return (int)$this->getTableLocator()->get('Users')->credentialVersion($userId);
    }

    public function testInvitationTokenIsRandomAndStoredOnlyAsItsHash(): void
    {
        $first = $this->service->issueInvitation(1, '  New.Person@Example.com ');
        $second = $this->service->issueInvitation(1, 'another@example.com');

        $this->assertIsString($first);
        $this->assertIsString($second);
        $this->assertMatchesRegularExpression(AccountTokenService::TOKEN_PATTERN, $first);
        $this->assertNotSame($first, $second);

        $row = $this->tokenRow($first);
        $this->assertSame('invitation', $row['purpose']);
        $this->assertSame('new.person@example.com', $row['email']);
        $this->assertSame(1, $row['issued_by_user_id']);
        $this->assertNull($row['user_id']);
        $this->assertSame(hash('sha256', $first), $row['token_hash']);
        foreach ($row as $value) {
            $this->assertStringNotContainsString($first, (string)$value);
        }
    }

    public function testInvitationExpiresAfterTheConfiguredLifetime(): void
    {
        $token = (string)$this->service->issueInvitation(1, 'new@example.com');
        $expires = $this->tokenRow($token)['expires'];

        $this->assertSame(
            DateTime::now()->addSeconds(AccountTokenService::lifetime('invitation'))->getTimestamp(),
            $expires->getTimestamp(),
        );
    }

    public function testInvitationForAnExistingAccountIssuesNothing(): void
    {
        $this->assertNull($this->service->issueInvitation(1, 'OTHER@example.com'));
        $this->assertSame(0, $this->getTableLocator()->get('AccountTokens')->find()->count());
    }

    public function testRedeemingAnInvitationCreatesTheAccountOnce(): void
    {
        $token = (string)$this->service->issueInvitation(1, 'new@example.com');

        $user = $this->service->redeemInvitation($token, 'a-new-password');

        $this->assertSame('new@example.com', $user->email);
        $this->assertTrue(password_verify('a-new-password', $user->password));
        $this->assertNotNull($this->tokenRow($token)['used']);

        $this->expectException(DomainException::class);
        $this->service->redeemInvitation($token, 'another-password');
    }

    public function testReissuingAnInvitationRetiresTheEarlierLink(): void
    {
        $earlier = (string)$this->service->issueInvitation(1, 'new@example.com');
        $later = (string)$this->service->issueInvitation(2, 'new@example.com');

        try {
            $this->service->redeemInvitation($earlier, 'a-new-password');
            $this->fail('The earlier invitation was still usable.');
        } catch (DomainException) {
        }
        $this->assertSame('new@example.com', $this->service->redeemInvitation($later, 'a-new-password')->email);
    }

    public function testAnExpiredInvitationIsRejected(): void
    {
        $token = (string)$this->service->issueInvitation(1, 'new@example.com');
        Chronos::setTestNow(Chronos::now()->addSeconds(AccountTokenService::lifetime('invitation') + 1));

        $this->expectException(DomainException::class);
        $this->service->redeemInvitation($token, 'a-new-password');
    }

    public function testARejectedPasswordLeavesTheInvitationUsable(): void
    {
        $token = (string)$this->service->issueInvitation(1, 'new@example.com');

        try {
            $this->service->redeemInvitation($token, 'short');
            $this->fail('A short password was accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('not accepted', $exception->getMessage());
        }
        $this->assertNull($this->tokenRow($token)['used']);
        $this->assertFalse($this->getTableLocator()->get('Users')->exists(['email' => 'new@example.com']));

        $this->assertSame('new@example.com', $this->service->redeemInvitation($token, 'long-enough')->email);
    }

    public function testAnInvitationIsRejectedOnceTheAddressHasAnAccount(): void
    {
        $token = (string)$this->service->issueInvitation(1, 'new@example.com');
        $users = $this->getTableLocator()->get('Users');
        $users->saveOrFail($users->newEntity(['email' => 'new@example.com', 'password' => 'operator-made']));

        $this->expectException(DomainException::class);
        $this->service->redeemInvitation($token, 'a-new-password');
    }

    public function testATokenOnlyWorksForItsOwnPurpose(): void
    {
        $reset = (string)$this->service->issuePasswordReset('owner@example.com');
        $invitation = (string)$this->service->issueInvitation(1, 'new@example.com');

        try {
            $this->service->redeemInvitation($reset, 'a-new-password');
            $this->fail('A reset token created an account.');
        } catch (DomainException) {
        }
        $this->expectException(DomainException::class);
        $this->service->redeemPasswordReset($invitation, 'a-new-password');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedTokens(): array
    {
        return [
            'empty' => [''],
            'too short' => ['abc'],
            'padding' => [str_repeat('a', 42) . '='],
            'not base64url' => [str_repeat('a', 42) . '+'],
            'too long' => [str_repeat('a', 44)],
        ];
    }

    #[DataProvider('malformedTokens')]
    public function testMalformedTokensAreRejected(string $token): void
    {
        $this->expectException(DomainException::class);
        $this->service->redeemPasswordReset($token, 'a-new-password');
    }

    public function testAWellFormedUnknownTokenIsRejected(): void
    {
        $this->expectException(DomainException::class);
        $this->service->redeemPasswordReset(str_repeat('A', 43), 'a-new-password');
    }

    public function testResetForAnUnknownAddressIssuesNothing(): void
    {
        $this->assertNull($this->service->issuePasswordReset('nobody@example.com'));
        $this->assertSame(0, $this->getTableLocator()->get('AccountTokens')->find()->count());
    }

    public function testRedeemingAResetChangesThePasswordOnceAndAdvancesTheCredentialVersion(): void
    {
        $token = (string)$this->service->issuePasswordReset(' Owner@Example.com ');
        $row = $this->tokenRow($token);
        $this->assertSame(1, $row['user_id']);
        $this->assertSame(
            DateTime::now()->addSeconds(AccountTokenService::lifetime('password_reset'))->getTimestamp(),
            $row['expires']->getTimestamp(),
        );

        $user = $this->service->redeemPasswordReset($token, 'replacement-password');

        $this->assertSame(1, $user->id);
        $this->assertTrue(password_verify('replacement-password', $user->password));
        $this->assertSame(2, $this->credentialVersion(1));

        $this->expectException(DomainException::class);
        $this->service->redeemPasswordReset($token, 'yet-another-password');
    }

    public function testAnExpiredResetIsRejected(): void
    {
        $token = (string)$this->service->issuePasswordReset('owner@example.com');
        Chronos::setTestNow(Chronos::now()->addSeconds(AccountTokenService::lifetime('password_reset')));

        $this->expectException(DomainException::class);
        $this->service->redeemPasswordReset($token, 'replacement-password');
    }

    public function testChangingAPasswordAnyOtherWayRetiresOutstandingResets(): void
    {
        $token = (string)$this->service->issuePasswordReset('owner@example.com');
        $users = $this->getTableLocator()->get('Users');
        $owner = $users->get(1);
        $users->patchEntity($owner, ['password' => 'operator-chosen-password']);
        $users->saveOrFail($owner);

        $this->assertNotNull($this->tokenRow($token)['used']);
        $this->assertSame(2, $this->credentialVersion(1));
        $this->expectException(DomainException::class);
        $this->service->redeemPasswordReset($token, 'replacement-password');
    }

    public function testSavingAnAccountWithoutChangingThePasswordKeepsTheCredentialVersion(): void
    {
        $users = $this->getTableLocator()->get('Users');
        $owner = $users->get(1);
        $users->patchEntity($owner, ['email' => 'renamed@example.com']);
        $users->saveOrFail($owner);

        $this->assertSame(1, $this->credentialVersion(1));
    }

    public function testComparisonIsByHashAndConstantTime(): void
    {
        $token = str_repeat('Q', 43);
        $hash = AccountTokenService::hashToken($token);

        $this->assertSame(hash('sha256', $token), $hash);
        $this->assertTrue(AccountTokenService::matches($token, $hash));
        $this->assertFalse(AccountTokenService::matches($token, strtoupper($hash)));
        $this->assertFalse(AccountTokenService::matches(str_repeat('R', 43), $hash));
        $this->assertFalse(AccountTokenService::matches($token, ''));
        $source = (string)file_get_contents(ROOT . '/src/Service/AccountTokenService.php');
        $this->assertStringContainsString('hash_equals($storedHash, self::hashToken($plainToken))', $source);
    }
}
