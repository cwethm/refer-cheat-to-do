<?php
declare(strict_types=1);

namespace App\Http\Exception;

use Cake\Http\Exception\HttpException;

/**
 * Raised when a rate-limited operation has been attempted too often.
 */
class TooManyRequestsException extends HttpException
{
    /**
     * Default HTTP status code for rate-limited requests.
     *
     * @var int
     */
    protected int $_defaultCode = 429;

    /**
     * @param int $retryAfter Seconds after which the oldest counted attempt leaves the window.
     */
    public function __construct(int $retryAfter, ?string $message = null)
    {
        parent::__construct($message ?? 'Too many attempts. Please wait and try again.');
        $this->setHeader('Retry-After', (string)max(1, $retryAfter));
    }
}
