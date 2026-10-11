<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Http\Exception\TooManyRequestsException;
use App\Service\RateLimiter;
use Cake\Chronos\Chronos;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use DomainException;

class RateLimiterTest extends TestCase
{
    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.RateLimitHits',
    ];

    private RateLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('RateLimit.test_scope', ['limit' => 3, 'window' => 60]);
        Configure::write('RateLimit.other_scope', ['limit' => 3, 'window' => 60]);
        $this->limiter = new RateLimiter($this->getTableLocator());
    }

    protected function tearDown(): void
    {
        Configure::delete('RateLimit.test_scope');
        Configure::delete('RateLimit.other_scope');
        Chronos::setTestNow(Chronos::now());
        parent::tearDown();
    }

    private function hitTimes(string $scope, string $key, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->limiter->hit($scope, $key);
        }
    }

    public function testABucketFillsAtItsLimit(): void
    {
        $this->hitTimes('test_scope', 'someone@example.com', 2);
        $this->assertFalse($this->limiter->tooManyAttempts('test_scope', 'someone@example.com'));

        $this->limiter->hit('test_scope', 'someone@example.com');
        $this->assertTrue($this->limiter->tooManyAttempts('test_scope', 'someone@example.com'));
    }

    public function testAttemptsLeaveTheWindow(): void
    {
        $this->hitTimes('test_scope', 'key', 3);
        Chronos::setTestNow(Chronos::now()->addSeconds(59));
        $this->assertTrue($this->limiter->tooManyAttempts('test_scope', 'key'));

        Chronos::setTestNow(Chronos::now()->addSeconds(1));
        $this->assertFalse($this->limiter->tooManyAttempts('test_scope', 'key'));
    }

    public function testExpiredAttemptsArePrunedWhenTheScopeIsHitAgain(): void
    {
        $this->hitTimes('test_scope', 'key', 3);
        Chronos::setTestNow(Chronos::now()->addSeconds(61));
        $this->limiter->hit('test_scope', 'another key');

        $this->assertSame(1, $this->getTableLocator()->get('RateLimitHits')->find()->count());
    }

    public function testBucketsAreSeparatedByKeyAndScope(): void
    {
        $this->hitTimes('test_scope', 'key', 3);

        $this->assertFalse($this->limiter->tooManyAttempts('test_scope', 'other key'));
        $this->assertFalse($this->limiter->tooManyAttempts('other_scope', 'key'));
    }

    public function testTheRawKeyIsNeverStored(): void
    {
        $this->limiter->hit('test_scope', 'someone@example.com');

        $row = $this->getTableLocator()->get('RateLimitHits')->find()->disableHydration()->firstOrFail();
        $this->assertSame($this->limiter->bucket('test_scope', 'someone@example.com'), $row['bucket']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $row['bucket']);
        $this->assertNotSame(hash('sha256', "test_scope\0someone@example.com"), $row['bucket']);
        $this->assertStringNotContainsString('someone', implode(' ', array_map('strval', $row)));
    }

    public function testClearEmptiesOneBucket(): void
    {
        $this->hitTimes('test_scope', 'key', 3);
        $this->hitTimes('test_scope', 'other key', 3);
        $this->limiter->clear('test_scope', 'key');

        $this->assertFalse($this->limiter->tooManyAttempts('test_scope', 'key'));
        $this->assertTrue($this->limiter->tooManyAttempts('test_scope', 'other key'));
    }

    public function testAssertAvailableThrowsWithRetryAfter(): void
    {
        $this->limiter->assertAvailable('test_scope', 'key');
        $this->hitTimes('test_scope', 'key', 3);

        try {
            $this->limiter->assertAvailable('test_scope', 'key');
            $this->fail('A full bucket was accepted.');
        } catch (TooManyRequestsException $exception) {
            $this->assertSame(429, $exception->getCode());
            $this->assertSame(['Retry-After' => '60'], $exception->getHeaders());
        }
    }

    public function testAnUnconfiguredScopeIsRejected(): void
    {
        $this->expectException(DomainException::class);
        $this->limiter->hit('no_such_scope', 'key');
    }

    public function testEveryApplicationScopeIsConfigured(): void
    {
        foreach (
            [
                'login_account',
                'login_client',
                'password_reset_account',
                'password_reset_client',
                'token_redeem_client',
                'password_change_account',
                'invitation_issue_account',
            ] as $scope
        ) {
            $policy = $this->limiter->policy($scope);
            $this->assertGreaterThan(0, $policy['limit'], $scope);
            $this->assertGreaterThan(0, $policy['window'], $scope);
        }
    }
}
