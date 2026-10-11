<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController as BaseController;
use App\Service\ActivityRecorder;
use App\Service\CapabilityService;
use App\Service\RateLimiter;
use Cake\Core\Configure;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use Cake\View\JsonView;

class AppController extends BaseController
{
    /**
     * Use JsonView for API responses.
     *
     * @return array<int, string>
     */
    public function viewClasses(): array
    {
        return [JsonView::class];
    }

    /**
     * Serialize a successful API response payload.
     *
     * @param array<string, mixed> $data Response payload data.
     * @param array<string, mixed> $meta Optional response metadata.
     */
    protected function respond(array $data, array $meta = [], int $status = 200): Response
    {
        $payload = ['data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        $response = $this->getResponse()
            ->withType('application/json')
            ->withStatus($status);
        $response->getBody()->write((string)json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->setResponse($response);

        return $response;
    }

    /**
     * Resolve and require the currently authenticated user id from session.
     *
     * A session is accepted only while it is within the absolute session lifetime and carries
     * the account's current credential version, so changing or resetting a password ends every
     * session issued before the change. A rejected session is destroyed.
     */
    protected function requireUserId(): int
    {
        $session = $this->request->getSession();
        $userId = $this->positiveInt($session->read('Auth.user_id'));
        $version = $this->positiveInt($session->read('Auth.credential_version'));
        $authenticatedAt = $this->positiveInt($session->read('Auth.authenticated_at'));
        $lifetime = Configure::read('Accounts.sessionLifetime');
        $lifetime = is_int($lifetime) && $lifetime > 0 ? $lifetime : 12 * 3600;
        $now = DateTime::now()->getTimestamp();

        if (
            $userId !== null
            && $version !== null
            && $authenticatedAt !== null
            && $authenticatedAt <= $now
            && $now - $authenticatedAt < $lifetime
        ) {
            /** @var \App\Model\Table\UsersTable $users */
            $users = $this->fetchTable('Users');
            if ($users->credentialVersion($userId) === $version) {
                return $userId;
            }
        }

        if ($session->check('Auth')) {
            $session->destroy();
        }

        throw new UnauthorizedException('Authentication is required.');
    }

    /**
     * Start a fresh authenticated session for a user.
     *
     * The session id is regenerated so an id fixed before sign-in, or before a credential
     * change, cannot carry the new authority.
     */
    protected function startAuthenticatedSession(int $userId, int $credentialVersion): void
    {
        $session = $this->request->getSession();
        $session->renew();
        $session->write('Auth', [
            'user_id' => $userId,
            'credential_version' => $credentialVersion,
            'authenticated_at' => DateTime::now()->getTimestamp(),
        ]);
    }

    /**
     * Rate limiter for authentication-related operations.
     */
    protected function rateLimiter(): RateLimiter
    {
        return new RateLimiter($this->getTableLocator());
    }

    /**
     * Read a positive integer stored in the session.
     */
    private function positiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (is_string($value) && ctype_digit($value) && (int)$value > 0) {
            return (int)$value;
        }

        return null;
    }

    /**
     * Resolve the single authoritative capability evaluator.
     */
    protected function capabilities(): CapabilityService
    {
        return new CapabilityService($this->getTableLocator());
    }

    /**
     * Resolve the single authoritative activity recorder.
     */
    protected function activity(): ActivityRecorder
    {
        return new ActivityRecorder($this->getTableLocator());
    }
}
