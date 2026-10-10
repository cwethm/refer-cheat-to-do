<?php
declare(strict_types=1);

namespace App\Log;

use Cake\Error\PhpError;
use ReflectionClass;
use Throwable;

/**
 * Only factory-generated metadata can cross the logging boundary.
 */
final class SafeLogEvent
{
    /**
     * @param array<string, int|float|string> $metadata Safe operational metadata.
     */
    private function __construct(private readonly array $metadata)
    {
    }

    /**
     * Describe a completed request without its URL or content.
     */
    public static function request(int $status, float $durationMs): self
    {
        return new self([
            'event' => 'request.completed',
            'status' => max(100, min(599, $status)),
            'durationMs' => round(max(0, is_finite($durationMs) ? $durationMs : 0), 3),
        ]);
    }

    /**
     * Describe an exception without its message, trace or previous exceptions.
     */
    public static function exception(Throwable $exception): self
    {
        $class = $exception::class;
        if ((new ReflectionClass($exception))->isAnonymous() || !preg_match('/^[A-Za-z0-9_\\\\]+$/D', $class)) {
            $class = 'anonymous';
        }

        return new self([
            'event' => 'exception.unhandled',
            'exceptionClass' => $class,
            'source' => self::source($exception->getFile()),
            'line' => max(0, $exception->getLine()),
        ]);
    }

    /**
     * Describe a PHP error without its description or trace.
     */
    public static function phpError(PhpError $error): self
    {
        return new self([
            'event' => 'php.error',
            'errorLevel' => $error->getCode(),
            'source' => self::source($error->getFile()),
            'line' => max(0, $error->getLine() ?? 0),
        ]);
    }

    /**
     * Only existing PHP code inside the repository has a loggable location.
     */
    private static function source(?string $file): string
    {
        $path = $file === null ? false : realpath($file);
        $root = ROOT . DIRECTORY_SEPARATOR;
        if ($path === false || !str_starts_with($path, $root)) {
            return 'unknown';
        }
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)));
        if (!preg_match('#^(src|config|vendor|tests|webroot|bin)/[A-Za-z0-9_./-]+\.php$#D', $relative)) {
            return 'unknown';
        }

        return $relative;
    }

    /**
     * @return array<string, int|float|string>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }
}
