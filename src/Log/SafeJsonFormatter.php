<?php
declare(strict_types=1);

namespace App\Log;

use Cake\Log\Formatter\AbstractFormatter;

final class SafeJsonFormatter extends AbstractFormatter
{
    /**
     * @param mixed $level Log level.
     * @param string $message Ignored, including interpolated content.
     * @param array<string, mixed> $context Untrusted application context.
     */
    public function format(mixed $level, string $message, array $context = []): string
    {
        $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
        $scope = RequestLogContext::current() ?? RequestLogContext::create();
        $record = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => in_array($level, $levels, true) ? $level : 'error',
            'requestId' => $scope->requestId,
        ];
        if ($scope->method !== null) {
            $record['method'] = $scope->method;
        }
        $event = $context['safeEvent'] ?? null;
        $record += $event instanceof SafeLogEvent ? $event->metadata() : ['event' => 'application.log'];

        return json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
