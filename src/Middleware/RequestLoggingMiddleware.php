<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Log\RequestLogContext;
use App\Log\SafeLogEvent;
use Cake\Log\Log;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final class RequestLoggingMiddleware implements MiddlewareInterface
{
    /**
     * Wrap error handling so exception and completion events share one scope.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $previous = RequestLogContext::current();
        $scope = RequestLogContext::create($request->getMethod());
        RequestLogContext::set($scope);
        $started = hrtime(true);
        $status = 500;
        try {
            $response = $handler->handle($request->withAttribute('requestId', $scope->requestId));
            $status = $response->getStatusCode();

            return $response;
        } finally {
            try {
                Log::write('info', '', [
                    'safeEvent' => SafeLogEvent::request($status, (hrtime(true) - $started) / 1_000_000),
                ]);
            } catch (Throwable) {
                // Reporting is best-effort and must not mask a response or an escaping exception.
            } finally {
                RequestLogContext::set($previous);
            }
        }
    }
}
