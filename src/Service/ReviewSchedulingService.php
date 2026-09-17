<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\Todo;
use App\Model\Table\TodosTable;
use Cake\I18n\DateTime;
use DomainException;

/**
 * Single authority for ToDo review scheduling.
 *
 * Controllers must not compute review dates themselves; they translate the exceptions raised here
 * into API responses.
 */
class ReviewSchedulingService
{
    public const MIN_INTERVAL_DAYS = 1;
    public const MAX_INTERVAL_DAYS = 365;
    public const DEFAULT_INTERVAL_DAYS = 7;
    public const MAX_SNOOZE_DAYS = 365;

    /**
     * @param \App\Model\Table\TodosTable $todos ToDo table used for persistence.
     */
    public function __construct(private TodosTable $todos)
    {
    }

    /**
     * Compute the next review moment from a base moment and an interval in days.
     *
     * @throws \DomainException When the interval is outside the supported range.
     */
    public function nextReviewAt(DateTime $from, int $intervalDays): DateTime
    {
        $this->assertInterval($intervalDays);

        return $from->addDays($intervalDays);
    }

    /**
     * Record that a ToDo has been reviewed and schedule the following review.
     *
     * An explicit `$nextReviewAt` overrides the interval based calculation.
     *
     * @throws \DomainException When the interval is invalid or the explicit date is in the past.
     */
    public function markReviewed(Todo $todo, ?int $intervalDays = null, ?DateTime $nextReviewAt = null): Todo
    {
        $now = DateTime::now();
        $interval = $intervalDays ?? (int)($todo->review_interval_days ?: self::DEFAULT_INTERVAL_DAYS);
        $this->assertInterval($interval);

        if ($nextReviewAt !== null) {
            $this->assertFuture($nextReviewAt, $now);
            $next = $nextReviewAt;
        } else {
            $next = $this->nextReviewAt($now, $interval);
        }

        $todo->set('review_interval_days', $interval);
        $todo->set('last_reviewed_at', $now);
        $todo->set('next_review_at', $next);

        return $this->todos->saveOrFail($todo);
    }

    /**
     * Push a ToDo's next review into the future without recording a review.
     *
     * Exactly one of `$days` or `$until` must be supplied.
     *
     * @throws \DomainException When the snooze request is invalid.
     */
    public function snooze(Todo $todo, ?int $days = null, ?DateTime $until = null): Todo
    {
        if (($days === null) === ($until === null)) {
            throw new DomainException('Provide either a snooze duration in days or an explicit date.');
        }

        $now = DateTime::now();
        if ($days !== null) {
            if ($days < 1 || $days > self::MAX_SNOOZE_DAYS) {
                throw new DomainException(
                    sprintf('Snooze duration must be between 1 and %d days.', self::MAX_SNOOZE_DAYS),
                );
            }
            $next = $now->addDays($days);
        } else {
            /** @var \Cake\I18n\DateTime $until */
            $this->assertFuture($until, $now);
            $next = $until;
        }

        $todo->set('next_review_at', $next);

        return $this->todos->saveOrFail($todo);
    }

    /**
     * Whether a ToDo's review is due at the given moment.
     */
    public function isDue(Todo $todo, DateTime $now): bool
    {
        $next = $todo->next_review_at;
        if ($next === null) {
            return false;
        }

        return $next->lessThanOrEquals($now);
    }

    /**
     * Validate an interval in days.
     *
     * @throws \DomainException When the interval is outside the supported range.
     */
    private function assertInterval(int $intervalDays): void
    {
        if ($intervalDays < self::MIN_INTERVAL_DAYS || $intervalDays > self::MAX_INTERVAL_DAYS) {
            throw new DomainException(sprintf(
                'Review interval must be between %d and %d days.',
                self::MIN_INTERVAL_DAYS,
                self::MAX_INTERVAL_DAYS,
            ));
        }
    }

    /**
     * Require that an explicit review date lies in the future.
     *
     * @throws \DomainException When the date is not in the future.
     */
    private function assertFuture(DateTime $candidate, DateTime $now): void
    {
        if ($candidate->lessThanOrEquals($now)) {
            throw new DomainException('Review date must be in the future.');
        }
    }
}
