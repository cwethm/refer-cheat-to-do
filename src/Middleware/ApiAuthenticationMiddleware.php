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

        $identity = $request->getSession()->read('Auth.identity');
        if (is_array($identity) && isset($identity['id'])) {
            $request = $request->withAttribute('identity', $identity);

            return $handler->handle($request);
        }

        if ($request->getSession()->check('Auth.user_id')) {
            $request->getSession()->delete('Auth.user_id');
            $request->getSession()->delete('Auth.identity');
        }

        return $handler->handle($request);
    }

    protected function isApiRequest(ServerRequest $request): bool
    {
        $path = $request->getUri()->getPath();

        return str_starts_with($path, '/api/') || $path === '/api';
    }
}
