<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController as BaseController;
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
}
