<?php
declare(strict_types=1);

namespace App\Service;

use App\Http\Exception\TooManyRequestsException;
use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorInterface;
use Cake\Utility\Security;
use DomainException;

/**
 * Fixed-window counters for authentication-related operations.
 *
 * Storage is explicit: each counted attempt is one row in `rate_limit_hits`, shared by every
 * application process through PostgreSQL, rather than incidental state in a cache or session.
 * The key (an email address, client IP or user id) is never stored; only a keyed hash of the
 * scope and key is, so the table holds no personal data in readable form.
 *
 * Limits are read from `RateLimit.<scope>` as `['limit' => int, 'window' => seconds]`.
 * Counting and checking are separate, non-atomic steps, so concurrent requests may overshoot a
 * limit by the number of requests in flight; that bound is accepted for these scopes.
 */
class RateLimiter
{
    /**
     * @param \Cake\ORM\Locator\LocatorInterface $tables Table locator used to resolve models.
     */
    public function __construct(private LocatorInterface $tables)
    {
    }

    /**
     * Throw when the bucket for this scope and key is already full.
     *
     * @throws \App\Http\Exception\TooManyRequestsException
     */
    public function assertAvailable(string $scope, string $key): void
    {
        if ($this->tooManyAttempts($scope, $key)) {
            throw new TooManyRequestsException($this->policy($scope)['window']);
        }
    }

    /**
     * Whether the bucket for this scope and key has reached its limit within the window.
     */
    public function tooManyAttempts(string $scope, string $key): bool
    {
        $policy = $this->policy($scope);
        $count = $this->tables->get('RateLimitHits')->find()
            ->where([
                'scope' => $scope,
                'bucket' => $this->bucket($scope, $key),
                'created >' => DateTime::now()->subSeconds($policy['window']),
            ])
            ->count();

        return $count >= $policy['limit'];
    }

    /**
     * Count one attempt and prune this scope's expired attempts.
     */
    public function hit(string $scope, string $key): void
    {
        $policy = $this->policy($scope);
        $hits = $this->tables->get('RateLimitHits');
        $now = DateTime::now();
        $hits->deleteAll([
            'scope' => $scope,
            'created <=' => $now->subSeconds($policy['window']),
        ]);
        $hit = $hits->newEmptyEntity();
        $hit->patch([
            'scope' => $scope,
            'bucket' => $this->bucket($scope, $key),
            'created' => $now,
        ], ['guard' => false]);
        $hits->saveOrFail($hit);
    }

    /**
     * Forget every counted attempt in one bucket.
     */
    public function clear(string $scope, string $key): void
    {
        $this->tables->get('RateLimitHits')->deleteAll([
            'scope' => $scope,
            'bucket' => $this->bucket($scope, $key),
        ]);
    }

    /**
     * Keyed hash of the scope and key, so the raw key is never persisted.
     */
    public function bucket(string $scope, string $key): string
    {
        return hash_hmac('sha256', $scope . "\0" . $key, Security::getSalt());
    }

    /**
     * Configured limit and window for a scope.
     *
     * @return array{limit: int, window: int}
     * @throws \DomainException When the scope is not configured.
     */
    public function policy(string $scope): array
    {
        $policy = Configure::read('RateLimit.' . $scope);
        if (
            !is_array($policy)
            || !is_int($policy['limit'] ?? null)
            || !is_int($policy['window'] ?? null)
            || $policy['limit'] < 1
            || $policy['window'] < 1
        ) {
            throw new DomainException('Unknown rate limit scope.');
        }

        return ['limit' => $policy['limit'], 'window' => $policy['window']];
    }
}
