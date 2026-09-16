<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Model\Table\TodosTable;
use App\Service\ReviewSchedulingService;
use Cake\Database\Exception\QueryException;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use DomainException;

class ReviewSchedulingServiceTest extends TestCase
{
    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Todos',
        'app.Tags',
        'app.TodosTags',
    ];

    protected TodosTable $Todos;

    protected ReviewSchedulingService $scheduling;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var \App\Model\Table\TodosTable $todos */
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $this->Todos = $todos;
        $this->scheduling = new ReviewSchedulingService($todos);
    }

    protected function tearDown(): void
    {
        DateTime::setTestNow(null);
        unset($this->Todos, $this->scheduling);

        parent::tearDown();
    }

    public function testNextReviewAtAddsTheInterval(): void
    {
        $from = new DateTime('2026-01-01 08:00:00');

        $this->assertSame(
            '2026-01-08 08:00:00',
            $this->scheduling->nextReviewAt($from, 7)->format('Y-m-d H:i:s'),
        );
    }

    public function testNextReviewAtCrossesMidnightBoundary(): void
    {
        $from = new DateTime('2026-01-01 23:59:59');

        $this->assertSame(
            '2026-01-02 23:59:59',
            $this->scheduling->nextReviewAt($from, 1)->format('Y-m-d H:i:s'),
        );
    }

    public function testNextReviewAtRejectsIntervalBelowMinimum(): void
    {
        $this->expectException(DomainException::class);
        $this->scheduling->nextReviewAt(DateTime::now(), 0);
    }

    public function testNextReviewAtRejectsIntervalAboveMaximum(): void
    {
        $this->expectException(DomainException::class);
        $this->scheduling->nextReviewAt(DateTime::now(), ReviewSchedulingService::MAX_INTERVAL_DAYS + 1);
    }

    public function testMarkReviewedUsesStoredIntervalByDefault(): void
    {
        DateTime::setTestNow(new DateTime('2026-02-01 12:00:00'));

        $todo = $this->scheduling->markReviewed($this->Todos->get(10));

        $this->assertSame('2026-02-01 12:00:00', $todo->last_reviewed_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-08 12:00:00', $todo->next_review_at?->format('Y-m-d H:i:s'));
        $this->assertSame(ReviewSchedulingService::DEFAULT_INTERVAL_DAYS, $todo->review_interval_days);
    }

    public function testMarkReviewedStoresNewInterval(): void
    {
        DateTime::setTestNow(new DateTime('2026-02-01 12:00:00'));

        $todo = $this->scheduling->markReviewed($this->Todos->get(10), 3);

        $this->assertSame(3, $todo->review_interval_days);
        $this->assertSame('2026-02-04 12:00:00', $todo->next_review_at?->format('Y-m-d H:i:s'));
        $this->assertSame(3, $this->Todos->get(10)->review_interval_days);
    }

    public function testExplicitDateOverridesInterval(): void
    {
        DateTime::setTestNow(new DateTime('2026-02-01 12:00:00'));

        $todo = $this->scheduling->markReviewed($this->Todos->get(10), 3, new DateTime('2026-03-15 09:30:00'));

        $this->assertSame('2026-03-15 09:30:00', $todo->next_review_at?->format('Y-m-d H:i:s'));
        $this->assertSame(3, $todo->review_interval_days);
    }

    public function testMarkReviewedRejectsPastExplicitDate(): void
    {
        DateTime::setTestNow(new DateTime('2026-02-01 12:00:00'));

        $this->expectException(DomainException::class);
        $this->scheduling->markReviewed($this->Todos->get(10), null, new DateTime('2026-01-01 12:00:00'));
    }

    public function testMarkReviewedRejectsInvalidInterval(): void
    {
        $this->expectException(DomainException::class);
        $this->scheduling->markReviewed($this->Todos->get(10), 0);
    }

    public function testSnoozeByDays(): void
    {
        DateTime::setTestNow(new DateTime('2026-02-01 12:00:00'));

        $todo = $this->scheduling->snooze($this->Todos->get(10), 5);

        $this->assertSame('2026-02-06 12:00:00', $todo->next_review_at?->format('Y-m-d H:i:s'));
        $this->assertNull($todo->last_reviewed_at);
    }

    public function testSnoozeUntilExplicitDate(): void
    {
        DateTime::setTestNow(new DateTime('2026-02-01 12:00:00'));

        $todo = $this->scheduling->snooze($this->Todos->get(10), null, new DateTime('2026-02-20 08:00:00'));

        $this->assertSame('2026-02-20 08:00:00', $todo->next_review_at?->format('Y-m-d H:i:s'));
    }

    public function testSnoozeRequiresExactlyOneArgument(): void
    {
        $this->expectException(DomainException::class);
        $this->scheduling->snooze($this->Todos->get(10));
    }

    public function testSnoozeRejectsBothArguments(): void
    {
        $this->expectException(DomainException::class);
        $this->scheduling->snooze($this->Todos->get(10), 3, DateTime::now()->addDays(3));
    }

    public function testSnoozeRejectsPastDate(): void
    {
        DateTime::setTestNow(new DateTime('2026-02-01 12:00:00'));

        $this->expectException(DomainException::class);
        $this->scheduling->snooze($this->Todos->get(10), null, new DateTime('2026-01-31 12:00:00'));
    }

    public function testSnoozeRejectsOutOfRangeDuration(): void
    {
        $this->expectException(DomainException::class);
        $this->scheduling->snooze($this->Todos->get(10), ReviewSchedulingService::MAX_SNOOZE_DAYS + 1);
    }

    public function testExactlyDueCountsAsDueAndOneSecondBeforeDoesNot(): void
    {
        DateTime::setTestNow(new DateTime('2026-02-01 12:00:00'));
        $todo = $this->scheduling->snooze($this->Todos->get(10), null, new DateTime('2026-02-10 00:00:00'));

        $this->assertTrue($this->scheduling->isDue($todo, new DateTime('2026-02-10 00:00:00')));
        $this->assertFalse($this->scheduling->isDue($todo, new DateTime('2026-02-09 23:59:59')));
    }

    public function testTodoWithoutScheduleIsNeverDue(): void
    {
        $this->assertFalse($this->scheduling->isDue($this->Todos->get(10), DateTime::now()));
    }

    public function testDatabaseRejectsOutOfRangeInterval(): void
    {
        $this->expectException(QueryException::class);
        $this->Todos->getConnection()->update('todos', ['review_interval_days' => 0], ['id' => 10]);
    }
}
