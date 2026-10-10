<?php
declare(strict_types=1);

namespace App\Error;

use App\Log\SafeLogEvent;
use Cake\Error\ErrorLoggerInterface;
use Cake\Error\PhpError;
use Cake\Log\Log;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class SafeErrorLogger implements ErrorLoggerInterface
{
    /**
     * @inheritDoc
     */
    public function logException(
        Throwable $exception,
        ?ServerRequestInterface $request = null,
        bool $includeTrace = false,
    ): void {
        try {
            Log::write('error', '', ['safeEvent' => SafeLogEvent::exception($exception)]);
        } catch (Throwable) {
            // Even logger initialization failures must not interfere with exception rendering.
        }
    }

    /**
     * @inheritDoc
     */
    public function logError(PhpError $error, ?ServerRequestInterface $request = null, bool $includeTrace = false): void
    {
        try {
            Log::write($error->getLogLevel(), '', ['safeEvent' => SafeLogEvent::phpError($error)]);
        } catch (Throwable) {
            // Do not report a reporting failure through an unsafe fallback.
        }
    }
}
