<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController as BaseController;
use Cake\Http\Response;
use Cake\View\JsonView;

class AppController extends BaseController
{
    protected array $viewClasses = [JsonView::class];

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

        $this->set($payload);
        $this->viewBuilder()->setOption('serialize', array_keys($payload));

        $response = $this->getResponse()->withStatus($status);
        $this->setResponse($response);

        return $response;
    }
}
