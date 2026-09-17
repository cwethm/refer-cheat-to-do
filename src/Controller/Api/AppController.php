<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController as BaseController;
use App\Service\ActivityRecorder;
use App\Service\CapabilityService;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Response;
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
     */
    protected function requireUserId(): int
    {
        $userId = $this->request->getSession()->read('Auth.user_id');
        if (is_int($userId) && $userId > 0) {
            return $userId;
        }
        if (is_string($userId) && ctype_digit($userId)) {
            return (int)$userId;
        }

        throw new UnauthorizedException('Authentication is required.');
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
