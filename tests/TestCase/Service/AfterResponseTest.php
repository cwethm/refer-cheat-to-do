<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AfterResponse;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use RuntimeException;

class AfterResponseTest extends TestCase
{
    protected function tearDown(): void
    {
        AfterResponse::clear();
        parent::tearDown();
    }

    public function testDeferredTasksRunWhenTheServerTerminatesNotBefore(): void
    {
        $ran = [];
        AfterResponse::defer(static function () use (&$ran): void {
            $ran[] = 'first';
        });
        AfterResponse::defer(static function () use (&$ran): void {
            $ran[] = 'second';
        });

        $this->assertSame([], $ran);
        $this->assertSame(2, AfterResponse::pending());

        EventManager::instance()->dispatch(new Event('Server.terminate'));

        $this->assertSame(['first', 'second'], $ran);
        $this->assertSame(0, AfterResponse::pending());
    }

    public function testTheListenerIsRegisteredOnce(): void
    {
        $count = 0;
        $before = $this->terminateListenerCount();
        AfterResponse::defer(static function () use (&$count): void {
            $count++;
        });
        AfterResponse::defer(static function () use (&$count): void {
            $count++;
        });

        $this->assertLessThanOrEqual($before + 1, $this->terminateListenerCount());

        EventManager::instance()->dispatch(new Event('Server.terminate'));
        $this->assertSame(2, $count);
    }

    private function terminateListenerCount(): int
    {
        $count = 0;
        foreach (EventManager::instance()->listeners('Server.terminate') as $listeners) {
            $count += count($listeners);
        }

        return $count;
    }

    public function testAFailingTaskDoesNotStopTheOthers(): void
    {
        $ran = false;
        AfterResponse::defer(static function (): void {
            throw new RuntimeException('The mail server is down.');
        });
        AfterResponse::defer(static function () use (&$ran): void {
            $ran = true;
        });

        AfterResponse::flush();

        $this->assertTrue($ran);
        $this->assertSame(0, AfterResponse::pending());
    }
}
