<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController as BaseController;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Response;
use Cake\View\JsonView;

class AppController extends BaseController
{
    protected array $viewClasses = [JsonView::class];

    protected function respond(array $data, array $meta = [], int $status = 200): Response
    {
        $payload = ['data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        $this->set($payload);
        $this->viewBuilder()->setOption('serialize', array_keys($payload));

        $response = $this->getResponse()->withStatus($status);
        $this->setResponse($response);

        return $response;
    }

    protected function respondError(string $code, string $message, array $details = [], int $status = 400): Response
    {
        $payload = [
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ];

        $this->set($payload);
        $this->viewBuilder()->setOption('serialize', array_keys($payload));
        $response = $this->getResponse()->withStatus($status);
        $this->setResponse($response);

        return $response;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getIdentity(): ?array
    {
        $identity = $this->getRequest()->getAttribute('identity');

        return is_array($identity) ? $identity : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function requireAuthenticatedIdentity(): array
    {
        $identity = $this->getIdentity();
        if ($identity === null) {
            throw new UnauthorizedException('Authentication required.');
        }

        return $identity;
    }
}
