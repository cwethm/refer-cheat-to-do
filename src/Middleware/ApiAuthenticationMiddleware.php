<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Datasource\FactoryLocator;
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

        $userId = $request->getSession()->read('Auth.user_id');
        if (is_numeric($userId)) {
            $identity = FactoryLocator::get('Table')->get('Users')
                ->find()
                ->select(['id', 'email', 'created', 'modified'])
                ->where(['id' => (int)$userId])
                ->disableHydration()
                ->first();

            if (is_array($identity)) {
                $request = $request->withAttribute('identity', $identity);
            }
        }

        return $handler->handle($request);
    }

    protected function isApiRequest(ServerRequest $request): bool
    {
        $path = $request->getUri()->getPath();

        return str_starts_with($path, '/api/') || $path === '/api' || $path === '/';
    }
}
