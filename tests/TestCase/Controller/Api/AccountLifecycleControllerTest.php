<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use App\Controller\Api\AuthController;
use App\Service\AccountTokenService;
use App\Test\TestCase\SignedInSessionTrait;
use Cake\Chronos\Chronos;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\ServerRequest;
use Cake\Http\Session;
use Cake\I18n\DateTime;
use Cake\Mailer\Message;
use Cake\Mailer\Transport\DebugTransport;
use Cake\Mailer\TransportFactory;
use Cake\TestSuite\EmailTrait;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\TestSuite\TestEmailTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * Slice 19: invitations, password change and reset, session hardening and rate limiting.
 */
class AccountLifecycleControllerTest extends TestCase
{
    use EmailTrait;
    use IntegrationTestTrait;
    use SignedInSessionTrait;

    private string $csrfToken;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.AccountTokens',
        'app.RateLimitHits',
        'app.ActivityRecords',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->csrfToken = (new CsrfProtectionMiddleware())->createToken();
        $this->cookie('csrfToken', $this->csrfToken);
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
     * @param array<string, mixed> $body
     */
    private function postJson(string $url, array $body = [], bool $withCsrf = true): void
    {
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($withCsrf) {
            $headers['X-CSRF-Token'] = $this->csrfToken;
        }
        $this->configRequest(['headers' => $headers]);
        $this->post($url, (string)json_encode($body));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $payload = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($payload);

        return $payload;
    }

    private function errorCode(): string
    {
        return (string)($this->payload()['error']['code'] ?? '');
    }

    private function credentialVersion(int $userId): ?int
    {
        return $this->getTableLocator()->get('Users')->credentialVersion($userId);
    }

    private function storedHash(int $userId): string
    {
        return (string)$this->getTableLocator()->get('Users')->get($userId)->password;
    }

    /**
     * The plain token carried by the link in the only message sent so far.
     */
    private function tokenFromMail(string $kind): string
    {
        $messages = TestEmailTransport::getMessages();
        $this->assertCount(1, $messages);
        $body = $messages[0]->getBodyText();
        $prefix = preg_quote(rtrim((string)Configure::read('App.fullBaseUrl'), '/') . '/app#' . $kind . '=', '/');
        $this->assertMatchesRegularExpression('/' . $prefix . '[A-Za-z0-9_-]{43}\b/', $body);
        preg_match('/#' . $kind . '=([A-Za-z0-9_-]{43})/', $body, $match);

        return $match[1];
    }

    /**
     * Invoke an AuthController action directly with a session that records renewals.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $auth
     * @return array{0: \Cake\Http\Session, 1: \Cake\Http\Response}
     */
    private function invokeWithRecordingSession(string $action, array $data, ?array $auth = null): array
    {
        $session = new class extends Session {
            public int $renewals = 0;

            public function renew(): void
            {
                $this->renewals++;
                parent::renew();
            }
        };
        if ($auth !== null) {
            $session->write('Auth', $auth);
        }
        $request = new ServerRequest([
            'url' => '/api/auth/' . $action,
            'session' => $session,
            'post' => $data,
            'environment' => ['REQUEST_METHOD' => 'POST'],
        ]);
        $response = (new AuthController($request))->{$action}();

        return [$session, $response];
    }

    public function testSignInRegeneratesTheSessionAndRecordsTheCredentialVersion(): void
    {
        [$session] = $this->invokeWithRecordingSession(
            'login',
            ['email' => 'owner@example.com', 'password' => 'owner-password'],
        );

        $this->assertSame(1, $session->renewals);
        $this->assertSame(1, $session->read('Auth.user_id'));
        $this->assertSame(1, $session->read('Auth.credential_version'));
        $this->assertSame(DateTime::now()->getTimestamp(), $session->read('Auth.authenticated_at'));
    }

    public function testChangingThePasswordRegeneratesTheSession(): void
    {
        [$session] = $this->invokeWithRecordingSession(
            'changePassword',
            ['current_password' => 'owner-password', 'new_password' => 'a-better-password'],
            ['user_id' => 1, 'credential_version' => 1, 'authenticated_at' => DateTime::now()->getTimestamp()],
        );

        $this->assertSame(1, $session->renewals);
        $this->assertSame(2, $session->read('Auth.credential_version'));
        $this->assertSame(2, $this->credentialVersion(1));
    }

    public function testASessionFromBeforeACredentialChangeIsRejectedAndDestroyed(): void
    {
        $this->signIn(1, 1);
        $users = $this->getTableLocator()->get('Users');
        $owner = $users->get(1);
        $users->patchEntity($owner, ['password' => 'operator-reset-password']);
        $users->saveOrFail($owner);

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/auth/me');

        $this->assertResponseCode(401);
        $this->assertSession(null, 'Auth');
    }

    public function testASessionWithoutACredentialVersionIsRejected(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/todos');

        $this->assertResponseCode(401);
        $this->assertSession(null, 'Auth');
    }

    public function testASessionPastItsAbsoluteLifetimeIsRejected(): void
    {
        $lifetime = (int)Configure::read('Accounts.sessionLifetime');
        $this->signIn(1, 1, DateTime::now()->getTimestamp() - $lifetime);
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/auth/me');

        $this->assertResponseCode(401);
    }

    public function testASessionJustInsideItsAbsoluteLifetimeIsAccepted(): void
    {
        $lifetime = (int)Configure::read('Accounts.sessionLifetime');
        $this->signIn(1, 1, DateTime::now()->getTimestamp() - $lifetime + 1);
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/auth/me');

        $this->assertResponseOk();
    }

    public function testASessionIssuedInTheFutureIsRejected(): void
    {
        $this->signIn(1, 1, DateTime::now()->getTimestamp() + 60);
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/auth/me');

        $this->assertResponseCode(401);
    }

    public function testSessionsHaveAnExplicitIdleExpiryAndAHardenedCookie(): void
    {
        $this->assertSame(120, Configure::read('Session.timeout'));
        $ini = (array)Configure::read('Session.ini');
        $this->assertSame('Strict', $ini['session.cookie_samesite']);
        $this->assertTrue($ini['session.cookie_httponly']);
        $this->assertTrue($ini['session.use_strict_mode']);
        $this->assertGreaterThan(0, Configure::read('Accounts.sessionLifetime'));
    }

    public function testSignOutDestroysTheWholeSession(): void
    {
        $this->signIn(1);
        $this->session(['Other' => 'value']);
        $this->postJson('/api/auth/logout', [], false);

        $this->assertResponseOk();
        $this->assertSession(null, 'Auth');
        $this->assertSession(null, 'Other');
    }

    public function testUnknownAddressAndWrongPasswordFailIdentically(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'whatever-password'], false);
        $unknown = (string)$this->_response->getBody();
        $this->assertResponseCode(401);

        $this->postJson('/api/auth/login', ['email' => 'owner@example.com', 'password' => 'whatever-password'], false);
        $this->assertResponseCode(401);
        $this->assertSame($unknown, (string)$this->_response->getBody());
    }

    public function testFailedSignInsForOneAddressAreRateLimitedEvenWithTheRightPassword(): void
    {
        $limit = (int)Configure::read('RateLimit.login_account.limit');
        for ($i = 0; $i < $limit; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'owner@example.com', 'password' => 'wrong-password'], false);
            $this->assertResponseCode(401);
        }

        $this->postJson('/api/auth/login', ['email' => 'OWNER@example.com', 'password' => 'owner-password'], false);
        $this->assertResponseCode(429);
        $this->assertSame('TOO_MANY_REQUESTS', $this->errorCode());
        $this->assertHeader('Retry-After', (string)Configure::read('RateLimit.login_account.window'));
        $this->assertSession(null, 'Auth');

        $this->postJson('/api/auth/login', ['email' => 'other@example.com', 'password' => 'other-password'], false);
        $this->assertResponseOk();
    }

    public function testUnknownAddressesFillTheSameAccountBucket(): void
    {
        $limit = (int)Configure::read('RateLimit.login_account.limit');
        for ($i = 0; $i < $limit; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'], false);
        }

        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'], false);
        $this->assertResponseCode(429);
    }

    public function testFailedSignInsFromOneClientAreRateLimitedAcrossAddresses(): void
    {
        $limit = (int)Configure::read('RateLimit.login_client.limit');
        for ($i = 0; $i < $limit; $i++) {
            $this->postJson('/api/auth/login', ['email' => "guess{$i}@example.com", 'password' => 'wrong'], false);
            $this->assertResponseCode(401);
        }

        $this->postJson('/api/auth/login', ['email' => 'owner@example.com', 'password' => 'owner-password'], false);
        $this->assertResponseCode(429);
    }

    public function testSuccessfulSignInsAreNotCounted(): void
    {
        $limit = (int)Configure::read('RateLimit.login_account.limit');
        for ($i = 0; $i <= $limit; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'owner@example.com', 'password' => 'owner-password'], false);
            $this->assertResponseOk();
        }
    }

    public function testChangingThePasswordRequiresAuthentication(): void
    {
        $this->postJson('/api/auth/password', ['current_password' => 'owner-password', 'new_password' => 'new-password']);

        $this->assertResponseCode(401);
    }

    public function testChangingThePasswordRequiresACsrfToken(): void
    {
        $this->signIn(1);
        $this->postJson(
            '/api/auth/password',
            ['current_password' => 'owner-password', 'new_password' => 'new-password'],
            false,
        );

        $this->assertResponseCode(403);
        $this->assertTrue(password_verify('owner-password', $this->storedHash(1)));
    }

    public function testChangingThePasswordKeepsThisSessionAndEndsOthers(): void
    {
        $this->signIn(1);
        $this->postJson('/api/auth/password', ['current_password' => 'owner-password', 'new_password' => 'new-password']);

        $this->assertResponseOk();
        $this->assertTrue(password_verify('new-password', $this->storedHash(1)));
        $this->assertSame(2, $this->credentialVersion(1));
        $this->assertSession(2, 'Auth.credential_version');

        // Another session, issued before the change, is no longer accepted.
        $this->signIn(1, 1);
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/auth/me');
        $this->assertResponseCode(401);
    }

    public function testChangingThePasswordWithTheWrongCurrentPasswordIsRefusedAndRateLimited(): void
    {
        $limit = (int)Configure::read('RateLimit.password_change_account.limit');
        for ($i = 0; $i < $limit; $i++) {
            $this->signIn(1);
            $this->postJson('/api/auth/password', ['current_password' => 'wrong', 'new_password' => 'new-password']);
            $this->assertResponseCode(422);
        }

        $this->signIn(1);
        $this->postJson('/api/auth/password', ['current_password' => 'owner-password', 'new_password' => 'new-password']);
        $this->assertResponseCode(429);
        $this->assertTrue(password_verify('owner-password', $this->storedHash(1)));
        $this->assertSame(1, $this->credentialVersion(1));
    }

    /**
     * @return array<string, array{array<string, string>, int}>
     */
    public static function rejectedPasswordChanges(): array
    {
        return [
            'missing new password' => [['current_password' => 'owner-password'], 400],
            'missing current password' => [['new_password' => 'new-password'], 400],
            'same password' => [['current_password' => 'owner-password', 'new_password' => 'owner-password'], 422],
            'too short' => [['current_password' => 'owner-password', 'new_password' => 'short'], 422],
        ];
    }

    /**
     * @param array<string, string> $body
     */
    #[DataProvider('rejectedPasswordChanges')]
    public function testRejectedPasswordChangesChangeNothing(array $body, int $status): void
    {
        $this->signIn(1);
        $this->postJson('/api/auth/password', $body);

        $this->assertResponseCode($status);
        $this->assertTrue(password_verify('owner-password', $this->storedHash(1)));
        $this->assertSame(1, $this->credentialVersion(1));
    }

    public function testResetRequestsForKnownAndUnknownAddressesLookIdentical(): void
    {
        $this->postJson('/api/auth/password-reset', ['email' => 'nobody@example.com']);
        $this->assertResponseCode(202);
        $unknown = (string)$this->_response->getBody();
        $this->assertNoMailSent();

        $this->postJson('/api/auth/password-reset', ['email' => 'owner@example.com']);
        $this->assertResponseCode(202);
        $this->assertSame($unknown, (string)$this->_response->getBody());
        $this->assertMailSentTo('owner@example.com');
        $this->assertMailCount(1);
        $this->assertResponseNotContains($this->tokenFromMail('reset'));
    }

    public function testAResetEmailCarriesTheOnlyCopyOfTheToken(): void
    {
        $this->postJson('/api/auth/password-reset', ['email' => ' Owner@Example.com ']);
        $token = $this->tokenFromMail('reset');

        $row = $this->getTableLocator()->get('AccountTokens')->find()->disableHydration()->firstOrFail();
        $this->assertSame(hash('sha256', $token), $row['token_hash']);
        $this->assertSame(1, $row['user_id']);
        $this->assertMailContainsText('expires in 60 minutes');
    }

    public function testAResetRequestRequiresAnAddressAndACsrfToken(): void
    {
        $this->postJson('/api/auth/password-reset', ['email' => '  ']);
        $this->assertResponseCode(400);

        $this->postJson('/api/auth/password-reset', ['email' => 'owner@example.com'], false);
        $this->assertResponseCode(403);
        $this->assertNoMailSent();
    }

    public function testResetRequestsAreRateLimitedPerAddressWhetherKnownOrNot(): void
    {
        foreach (['owner@example.com', 'nobody@example.com'] as $email) {
            $limit = (int)Configure::read('RateLimit.password_reset_account.limit');
            for ($i = 0; $i < $limit; $i++) {
                $this->postJson('/api/auth/password-reset', ['email' => $email]);
                $this->assertResponseCode(202);
            }
            $this->postJson('/api/auth/password-reset', ['email' => $email]);
            $this->assertResponseCode(429);
        }
    }

    public function testResetRequestsAreRateLimitedPerClient(): void
    {
        $limit = (int)Configure::read('RateLimit.password_reset_client.limit');
        for ($i = 0; $i < $limit; $i++) {
            $this->postJson('/api/auth/password-reset', ['email' => "someone{$i}@example.com"]);
            $this->assertResponseCode(202);
        }

        $this->postJson('/api/auth/password-reset', ['email' => 'owner@example.com']);
        $this->assertResponseCode(429);
        $this->assertNoMailSent();
    }

    public function testAMailDeliveryFailureDoesNotRevealTheAccount(): void
    {
        TransportFactory::drop('default');
        TransportFactory::setConfig('default', ['className' => new class extends DebugTransport {
            public function send(Message $message): array
            {
                throw new RuntimeException('SMTP is down');
            }
        }]);

        $this->postJson('/api/auth/password-reset', ['email' => 'nobody@example.com']);
        $unknown = (string)$this->_response->getBody();
        $this->postJson('/api/auth/password-reset', ['email' => 'owner@example.com']);

        $this->assertResponseCode(202);
        $this->assertSame($unknown, (string)$this->_response->getBody());
    }

    public function testCompletingAResetChangesThePasswordAndEndsEverySession(): void
    {
        $token = (string)(new AccountTokenService($this->getTableLocator()))->issuePasswordReset('owner@example.com');

        $this->postJson('/api/auth/password-reset/complete', ['token' => $token, 'password' => 'recovered-password']);

        $this->assertResponseOk();
        $this->assertSame('owner@example.com', $this->payload()['data']['user']['email']);
        $this->assertTrue(password_verify('recovered-password', $this->storedHash(1)));
        $this->assertSame(2, $this->credentialVersion(1));
        $this->assertSession(null, 'Auth');

        $this->signIn(1, 1);
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/auth/me');
        $this->assertResponseCode(401);

        $this->postJson('/api/auth/login', ['email' => 'owner@example.com', 'password' => 'recovered-password'], false);
        $this->assertResponseOk();
    }

    public function testAResetTokenCannotBeReused(): void
    {
        $token = (string)(new AccountTokenService($this->getTableLocator()))->issuePasswordReset('owner@example.com');
        $this->postJson('/api/auth/password-reset/complete', ['token' => $token, 'password' => 'recovered-password']);
        $this->assertResponseOk();

        $this->postJson('/api/auth/password-reset/complete', ['token' => $token, 'password' => 'attacker-password']);

        $this->assertResponseCode(400);
        $this->assertResponseContains('This link is invalid or has expired.');
        $this->assertTrue(password_verify('recovered-password', $this->storedHash(1)));
    }

    public function testAnExpiredResetTokenIsRefused(): void
    {
        $token = (string)(new AccountTokenService($this->getTableLocator()))->issuePasswordReset('owner@example.com');
        Chronos::setTestNow(Chronos::now()->addHours(2));

        $this->postJson('/api/auth/password-reset/complete', ['token' => $token, 'password' => 'recovered-password']);

        $this->assertResponseCode(400);
        $this->assertTrue(password_verify('owner-password', $this->storedHash(1)));
    }

    public function testAWeakPasswordLeavesTheResetTokenUsable(): void
    {
        $token = (string)(new AccountTokenService($this->getTableLocator()))->issuePasswordReset('owner@example.com');

        $this->postJson('/api/auth/password-reset/complete', ['token' => $token, 'password' => 'short']);
        $this->assertResponseCode(422);
        $this->assertSame('VALIDATION_ERROR', $this->errorCode());

        $this->postJson('/api/auth/password-reset/complete', ['token' => $token, 'password' => 'long-enough']);
        $this->assertResponseOk();
    }

    public function testCompletingAResetRequiresACsrfToken(): void
    {
        $token = (string)(new AccountTokenService($this->getTableLocator()))->issuePasswordReset('owner@example.com');

        $this->postJson(
            '/api/auth/password-reset/complete',
            ['token' => $token, 'password' => 'recovered-password'],
            false,
        );

        $this->assertResponseCode(403);
        $this->assertTrue(password_verify('owner-password', $this->storedHash(1)));
    }

    public function testFailedRedemptionsAreRateLimitedPerClient(): void
    {
        $limit = (int)Configure::read('RateLimit.token_redeem_client.limit');
        for ($i = 0; $i < $limit; $i++) {
            $this->postJson('/api/invitations/accept', ['token' => str_repeat('x', 43), 'password' => 'a-password']);
            $this->assertResponseCode(400);
        }
        $token = (string)(new AccountTokenService($this->getTableLocator()))->issuePasswordReset('owner@example.com');

        $this->postJson('/api/auth/password-reset/complete', ['token' => $token, 'password' => 'recovered-password']);

        $this->assertResponseCode(429);
        $this->assertTrue(password_verify('owner-password', $this->storedHash(1)));
    }

    public function testInvitingRequiresAuthenticationAndACsrfToken(): void
    {
        $this->postJson('/api/invitations', ['email' => 'new@example.com']);
        $this->assertResponseCode(401);

        $this->signIn(1);
        $this->postJson('/api/invitations', ['email' => 'new@example.com'], false);
        $this->assertResponseCode(403);
        $this->assertNoMailSent();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidInvitationAddresses(): array
    {
        return [
            'empty' => [''],
            'not an address' => ['not-an-address'],
            'too long' => [str_repeat('a', 180) . '@example.com'],
        ];
    }

    #[DataProvider('invalidInvitationAddresses')]
    public function testInvitingAnInvalidAddressIsRefused(string $email): void
    {
        $this->signIn(1);
        $this->postJson('/api/invitations', ['email' => $email]);

        $this->assertResponseCode(422);
        $this->assertNoMailSent();
    }

    public function testAnInvitationIsEmailedToTheInviteeAndNeverReturnedToTheIssuer(): void
    {
        $this->signIn(1);
        $this->postJson('/api/invitations', ['email' => 'New@Example.com']);

        $this->assertResponseCode(202);
        $this->assertMailSentTo('new@example.com');
        $this->assertMailCount(1);
        $token = $this->tokenFromMail('invite');
        $this->assertResponseNotContains($token);
        $this->assertMailContainsText('expires in 168 hours');

        $row = $this->getTableLocator()->get('AccountTokens')->find()->disableHydration()->firstOrFail();
        $this->assertSame(1, $row['issued_by_user_id']);
        $this->assertSame(hash('sha256', $token), $row['token_hash']);
    }

    public function testInvitingAnExistingAccountLooksTheSameAndSendsNothing(): void
    {
        $this->signIn(1);
        $this->postJson('/api/invitations', ['email' => 'nobody@example.com']);
        $newAddress = (string)$this->_response->getBody();
        TestEmailTransport::clearMessages();

        $this->signIn(1);
        $this->postJson('/api/invitations', ['email' => 'other@example.com']);

        $this->assertResponseCode(202);
        $this->assertSame($newAddress, (string)$this->_response->getBody());
        $this->assertNoMailSent();
    }

    public function testInvitationsAreRateLimitedPerIssuer(): void
    {
        $limit = (int)Configure::read('RateLimit.invitation_issue_account.limit');
        for ($i = 0; $i < $limit; $i++) {
            $this->signIn(1);
            $this->postJson('/api/invitations', ['email' => "new{$i}@example.com"]);
            $this->assertResponseCode(202);
        }

        $this->signIn(1);
        $this->postJson('/api/invitations', ['email' => 'one-more@example.com']);
        $this->assertResponseCode(429);

        $this->signIn(2);
        $this->postJson('/api/invitations', ['email' => 'one-more@example.com']);
        $this->assertResponseCode(202);
    }

    public function testAnInvitationThatCannotBeSentIsReported(): void
    {
        TransportFactory::drop('default');
        TransportFactory::setConfig('default', ['className' => new class extends DebugTransport {
            public function send(Message $message): array
            {
                throw new RuntimeException('SMTP is down');
            }
        }]);

        $this->signIn(1);
        $this->postJson('/api/invitations', ['email' => 'new@example.com']);

        $this->assertResponseCode(503);
        $this->assertResponseContains('The invitation could not be sent.');
    }

    public function testAcceptingAnInvitationCreatesAnAccountThatCanSignIn(): void
    {
        $token = (string)(new AccountTokenService($this->getTableLocator()))->issueInvitation(1, 'new@example.com');

        $this->postJson('/api/invitations/accept', ['token' => $token, 'password' => 'my-first-password']);

        $this->assertResponseCode(201);
        $this->assertSame('new@example.com', $this->payload()['data']['user']['email']);
        $this->assertSession(null, 'Auth');

        $this->postJson('/api/auth/login', ['email' => 'new@example.com', 'password' => 'my-first-password'], false);
        $this->assertResponseOk();
    }

    public function testAnInvitationCannotBeReused(): void
    {
        $token = (string)(new AccountTokenService($this->getTableLocator()))->issueInvitation(1, 'new@example.com');
        $this->postJson('/api/invitations/accept', ['token' => $token, 'password' => 'my-first-password']);
        $this->assertResponseCode(201);

        $this->postJson('/api/invitations/accept', ['token' => $token, 'password' => 'attacker-password']);

        $this->assertResponseCode(400);
        $this->assertResponseContains('This link is invalid or has expired.');
    }

    public function testAnExpiredInvitationIsRefused(): void
    {
        $token = (string)(new AccountTokenService($this->getTableLocator()))->issueInvitation(1, 'new@example.com');
        Chronos::setTestNow(Chronos::now()->addDays(8));

        $this->postJson('/api/invitations/accept', ['token' => $token, 'password' => 'my-first-password']);

        $this->assertResponseCode(400);
        $this->assertFalse($this->getTableLocator()->get('Users')->exists(['email' => 'new@example.com']));
    }

    public function testAResetTokenCannotCreateAnAccount(): void
    {
        $token = (string)(new AccountTokenService($this->getTableLocator()))->issuePasswordReset('owner@example.com');

        $this->postJson('/api/invitations/accept', ['token' => $token, 'password' => 'my-first-password']);

        $this->assertResponseCode(400);
    }

    public function testAcceptingAnInvitationRequiresACsrfToken(): void
    {
        $token = (string)(new AccountTokenService($this->getTableLocator()))->issueInvitation(1, 'new@example.com');

        $this->postJson('/api/invitations/accept', ['token' => $token, 'password' => 'my-first-password'], false);

        $this->assertResponseCode(403);
        $this->assertFalse($this->getTableLocator()->get('Users')->exists(['email' => 'new@example.com']));
    }
}
