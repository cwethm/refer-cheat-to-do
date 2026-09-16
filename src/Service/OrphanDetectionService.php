<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\Todo;
use Cake\I18n\DateTime;

/**
 * Evaluates the individually testable rules that place a ToDo in the review queue.
 *
 * Each rule is a pure predicate over an already loaded ToDo; the service never mutates data.
 */
class OrphanDetectionService
{
    public const REASON_NO_SECTION = 'no_section';
    public const REASON_NO_TAGS = 'no_tags';
    public const REASON_REVIEW_OVERDUE = 'review_overdue';
    public const REASON_NEVER_REVIEWED = 'never_reviewed';
    public const REASON_STALE = 'stale';

    /**
     * Days after which an unreviewed ToDo is considered stale.
     */
    public const STALE_AFTER_DAYS = 30;

    /**
     * Statuses that are never eligible for review.
     *
     * @var list<string>
     */
    public const EXCLUDED_STATUSES = [
        TodoLifecycleService::STATUS_ARCHIVED,
        TodoLifecycleService::STATUS_TRASHED,
        TodoLifecycleService::STATUS_DONE,
    ];

    /**
     * Whether a ToDo's status makes it eligible for review at all.
     */
    public function isEligible(Todo $todo): bool
    {
        return !in_array((string)$todo->status, self::EXCLUDED_STATUSES, true);
    }

    /**
     * A ToDo is unorganized when it belongs to no Project or Notebook section.
     */
    public function hasNoSection(Todo $todo): bool
    {
        return $todo->project_section_id === null && $todo->notebook_section_id === null;
    }

    /**
     * A ToDo is untagged when no Tag is attached.
     */
    public function hasNoTags(Todo $todo): bool
    {
        return count($todo->tags ?? []) === 0;
    }

    /**
     * A ToDo is overdue when its scheduled review moment has passed.
     */
    public function isReviewOverdue(Todo $todo, DateTime $now): bool
    {
        $next = $todo->next_review_at;

        return $next !== null && $next->lessThanOrEquals($now);
    }

    /**
     * A ToDo has never been reviewed when no review has been recorded.
     */
    public function isNeverReviewed(Todo $todo): bool
    {
        return $todo->last_reviewed_at === null;
    }

    /**
     * A ToDo is stale when it was created long ago and never reviewed since.
     */
    public function isStale(Todo $todo, DateTime $now): bool
    {
        $reference = $todo->last_reviewed_at ?? $todo->created;
        if ($reference === null) {
            return false;
        }

        return $reference->addDays(self::STALE_AFTER_DAYS)->lessThanOrEquals($now);
    }

    /**
     * Accumulate every reason the ToDo belongs in the review queue.
     *
     * Reasons are returned in a stable order and never duplicated.
     *
     * @return list<string>
     */
    public function reasonsFor(Todo $todo, DateTime $now): array
    {
        if (!$this->isEligible($todo)) {
            return [];
        }

        $reasons = [];
        if ($this->isReviewOverdue($todo, $now)) {
            $reasons[] = self::REASON_REVIEW_OVERDUE;
        }
        if ($this->isNeverReviewed($todo)) {
            $reasons[] = self::REASON_NEVER_REVIEWED;
        }
        if ($this->isStale($todo, $now)) {
            $reasons[] = self::REASON_STALE;
        }
        if ($this->hasNoSection($todo)) {
            $reasons[] = self::REASON_NO_SECTION;
        }
        if ($this->hasNoTags($todo)) {
            $reasons[] = self::REASON_NO_TAGS;
        }

        return $reasons;
    }

    /**
     * Rule-based suggested actions derived from the reasons, without mutating anything.
     *
     * @param list<string> $reasons
     * @return list<string>
     */
    public function suggestedActions(array $reasons): array
    {
        $suggestions = [];
        if (in_array(self::REASON_NO_SECTION, $reasons, true)) {
            $suggestions[] = 'assign_section';
        }
        if (in_array(self::REASON_NO_TAGS, $reasons, true)) {
            $suggestions[] = 'add_tag';
        }
        if (
            in_array(self::REASON_REVIEW_OVERDUE, $reasons, true)
            || in_array(self::REASON_NEVER_REVIEWED, $reasons, true)
        ) {
            $suggestions[] = 'mark_reviewed';
        }
        if (in_array(self::REASON_STALE, $reasons, true)) {
            $suggestions[] = 'archive_or_trash';
        }

        return $suggestions;
    }
}
