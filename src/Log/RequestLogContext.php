<?php
declare(strict_types=1);

namespace App\Log;

final class RequestLogContext
{
    private static ?self $current = null;

    /**
     * @param string $requestId Server-generated identifier.
     * @param string|null $method Allowlisted HTTP method.
     */
    private function __construct(
        public readonly string $requestId,
        public readonly ?string $method,
    ) {
    }

    /**
     * Create a server-owned correlation scope.
     */
    public static function create(?string $method = null): self
    {
        $methods = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'CONNECT', 'TRACE'];

        return new self(
            bin2hex(random_bytes(16)),
            $method === null ? null : (in_array($method, $methods, true) ? $method : 'OTHER'),
        );
    }

    /**
     * Return the active HTTP scope, if any.
     */
    public static function current(): ?self
    {
        return self::$current;
    }

    /**
     * Replace the active scope, including restoring an enclosing request.
     */
    public static function set(?self $context): void
    {
        self::$current = $context;
    }
}
