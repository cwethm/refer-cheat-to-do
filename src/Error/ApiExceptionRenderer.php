<?php
declare(strict_types=1);

namespace App\Error;

use Cake\Core\Configure;
use Cake\Error\Renderer\WebExceptionRenderer;
use Cake\Http\Exception\HttpException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class ApiExceptionRenderer extends WebExceptionRenderer
{
    /**
     * Render exceptions as JSON for API requests.
     */
    public function render(): ResponseInterface
    {
        if (!$this->isApiRequest()) {
            return parent::render();
        }

        $exception = $this->error;
        $status = $this->getHttpCode($exception);
        $response = $this->controller->getResponse()
            ->withType('application/json')
            ->withStatus($status);

        if ($exception instanceof HttpException) {
            foreach ($exception->getHeaders() as $name => $value) {
                $response = $response->withHeader($name, $value);
            }
        }

        $payload = [
            'error' => [
                'code' => $this->errorCode($exception, $status),
                'message' => $this->_message($exception, $status),
                'details' => $this->errorDetails($exception),
            ],
        ];

        $response->getBody()->write((string)json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $response;
    }

    /**
     * Determine whether the current request targets the API.
     */
    protected function isApiRequest(): bool
    {
        $request = $this->controller->getRequest();
        $prefix = (string)$request->getParam('prefix');
        $path = $request->getUri()->getPath();

        return $prefix === 'Api'
            || str_starts_with($path, '/api/')
            || $path === '/api'
            || $request->accepts('application/json');
    }

    /**
     * Map HTTP status codes to stable API error codes.
     */
    protected function errorCode(Throwable $exception, int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            default => $status >= 500 ? 'INTERNAL_SERVER_ERROR' : 'REQUEST_ERROR',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function errorDetails(Throwable $exception): array
    {
        if (!Configure::read('debug')) {
            return [];
        }

        return [
            'exception' => $exception::class,
        ];
    }
}
