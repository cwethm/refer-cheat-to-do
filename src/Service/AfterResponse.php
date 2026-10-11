<?php
declare(strict_types=1);

namespace App\Service;

use App\Log\SafeLogEvent;
use Cake\Event\EventManager;
use Cake\Log\Log;
use Throwable;
use WeakReference;

/**
 * Work that must not delay, or show in the timing of, the response that triggered it.
 *
 * Tasks run on CakePHP's `Server.terminate` event, which fires after the response has been
 * emitted (and, under PHP-FPM, after `fastcgi_finish_request()` has closed the connection). Slice
 * 19 uses it for account mail, so whether an address has an account cannot be read from how long
 * a reset request or invitation takes. A failing task is logged without detail and never affects
 * the response or the other tasks.
 */
final class AfterResponse
{
    /**
     * @var list<callable(): void>
     */
    private static array $tasks = [];

    /**
     * The global event manager the listener was attached to, so it is attached once per manager.
     *
     * @var \WeakReference<\Cake\Event\EventManager>|null
     */
    private static ?WeakReference $registeredOn = null;

    /**
     * Queue a task to run once the response has been sent.
     *
     * @param callable(): void $task Work to run later.
     */
    public static function defer(callable $task): void
    {
        self::$tasks[] = $task;
        $events = EventManager::instance();
        if (self::$registeredOn?->get() !== $events) {
            $events->on('Server.terminate', [self::class, 'flush']);
            self::$registeredOn = WeakReference::create($events);
        }
    }

    /**
     * Run every queued task. Called by `Server.terminate`; tests call it to stand in for the
     * end of a request.
     */
    public static function flush(): void
    {
        while (self::$tasks !== []) {
            $task = array_shift(self::$tasks);
            try {
                $task();
            } catch (Throwable $exception) {
                Log::write('error', '', ['safeEvent' => SafeLogEvent::exception($exception)]);
            }
        }
    }

    /**
     * Drop queued tasks without running them.
     */
    public static function clear(): void
    {
        self::$tasks = [];
    }

    /**
     * Number of tasks waiting to run.
     */
    public static function pending(): int
    {
        return count(self::$tasks);
    }
}
