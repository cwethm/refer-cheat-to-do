<?php
declare(strict_types=1);

namespace App\Log\Engine;

use App\Log\SafeJsonFormatter;
use Cake\Log\Engine\FileLog;
use Stringable;
use Throwable;

final class SafeFileLog extends FileLog
{
    private bool $writing = false;

    /**
     * @param array<string, mixed> $config File logging configuration.
     */
    public function __construct(array $config = [])
    {
        $config['formatter'] = SafeJsonFormatter::class;
        set_error_handler(static fn(): bool => true);
        try {
            parent::__construct($config);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Logging is best-effort, with no unsafe fallback or recursive error logging.
     *
     * @param mixed $level Log level.
     * @param \Stringable|string $message Untrusted message.
     * @param array<string, mixed> $context Untrusted context.
     */
    public function log(mixed $level, Stringable|string $message, array $context = []): void
    {
        if ($this->writing) {
            return;
        }
        $this->writing = true;
        set_error_handler(static fn(): bool => true);
        try {
            parent::log($level, $message, $context);
        } catch (Throwable) {
            // A failed sink must not change application responses or expose a fallback message.
        } finally {
            restore_error_handler();
            $this->writing = false;
        }
    }

    /**
     * Never interpolate or stringify arbitrary messages or context.
     *
     * @param \Stringable|string $message Untrusted message.
     * @param array<string, mixed> $context Untrusted context.
     */
    protected function interpolate(Stringable|string $message, array $context = []): string
    {
        return '';
    }
}
