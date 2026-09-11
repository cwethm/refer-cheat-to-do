<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Http\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ApiAuthenticationMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$request instanceof ServerRequest || !$this->isApiRequest($request)) {
            return $handler->handle($request);
        }

        $session = $request->getSession();
        $identity = $session->read('Auth.identity');
        $userId = $session->read('Auth.user_id');
        if (
            is_array($identity)
            && isset($identity['id'])
            && is_numeric($userId)
            && (int)$identity['id'] === (int)$userId
        ) {
            $request = $request->withAttribute('identity', $identity);

            return $handler->handle($request);
        }

        if ($session->check('Auth.user_id') || $session->check('Auth.identity')) {
            $session->delete('Auth.user_id');
            $session->delete('Auth.identity');
        }

        return $handler->handle($request);
    }

    protected function isApiRequest(ServerRequest $request): bool
    {
        $path = $request->getUri()->getPath();

        return str_starts_with($path, '/api/') || $path === '/api';
    }
}
