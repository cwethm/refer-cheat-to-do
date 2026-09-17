<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Model\Entity\Tag;
use App\Model\Entity\Todo;
use App\Service\OrphanDetectionService;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class OrphanDetectionServiceTest extends TestCase
{
    protected OrphanDetectionService $orphans;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orphans = new OrphanDetectionService();
    }

    protected function tearDown(): void
    {
        unset($this->orphans);

        parent::tearDown();
    }

    /**
     * Build an in-memory ToDo without touching the database.
     *
     * @param array<string, mixed> $overrides
     */
    private function todo(array $overrides = []): Todo
    {
        $todo = new Todo([
            'id' => 1,
            'user_id' => 1,
            'title' => 'Example',
            'status' => 'inbox',
            'created' => new DateTime('2026-02-01 12:00:00'),
        ], ['markNew' => false, 'guard' => false]);
        foreach ($overrides as $field => $value) {
            $todo->set($field, $value);
        }

        return $todo;
    }

    private function now(): DateTime
    {
        return new DateTime('2026-02-10 12:00:00');
    }

    public function testNoSectionRuleIsTrueWhenUnassigned(): void
    {
        $this->assertTrue($this->orphans->hasNoSection($this->todo()));
    }

    public function testNoSectionRuleIsFalseWithProjectSection(): void
    {
        $this->assertFalse($this->orphans->hasNoSection($this->todo(['project_section_id' => 300])));
    }

    public function testNoSectionRuleIsFalseWithNotebookSection(): void
    {
        $this->assertFalse($this->orphans->hasNoSection($this->todo(['notebook_section_id' => 500])));
    }

    public function testNoTagsRuleIsTrueWithoutTags(): void
    {
        $this->assertTrue($this->orphans->hasNoTags($this->todo(['tags' => []])));
    }

    public function testNoTagsRuleIsFalseWithTags(): void
    {
        $todo = $this->todo(['tags' => [new Tag(['id' => 100, 'name' => 'Important'])]]);

        $this->assertFalse($this->orphans->hasNoTags($todo));
    }

    public function testReviewOverdueRuleBoundaries(): void
    {
        $due = $this->todo(['next_review_at' => new DateTime('2026-02-10 12:00:00')]);
        $notDue = $this->todo(['next_review_at' => new DateTime('2026-02-10 12:00:01')]);

        $this->assertTrue($this->orphans->isReviewOverdue($due, $this->now()));
        $this->assertFalse($this->orphans->isReviewOverdue($notDue, $this->now()));
    }

    public function testReviewOverdueRuleIsFalseWithoutSchedule(): void
    {
        $this->assertFalse($this->orphans->isReviewOverdue($this->todo(), $this->now()));
    }

    public function testNeverReviewedRule(): void
    {
        $this->assertTrue($this->orphans->isNeverReviewed($this->todo()));
        $reviewed = $this->todo(['last_reviewed_at' => new DateTime('2026-02-05 12:00:00')]);
        $this->assertFalse($this->orphans->isNeverReviewed($reviewed));
    }

    public function testStaleRuleUsesLastReviewOrCreation(): void
    {
        $fresh = $this->todo(['created' => new DateTime('2026-02-01 12:00:00')]);
        $this->assertFalse($this->orphans->isStale($fresh, $this->now()));

        $stale = $this->todo(['created' => new DateTime('2025-12-01 12:00:00')]);
        $this->assertTrue($this->orphans->isStale($stale, $this->now()));

        $recentlyReviewed = $this->todo([
            'created' => new DateTime('2025-12-01 12:00:00'),
            'last_reviewed_at' => new DateTime('2026-02-09 12:00:00'),
        ]);
        $this->assertFalse($this->orphans->isStale($recentlyReviewed, $this->now()));
    }

    public function testStaleRuleBoundaryIsInclusive(): void
    {
        $exactly = $this->todo(['created' => new DateTime('2026-01-11 12:00:00')]);

        $this->assertTrue($this->orphans->isStale($exactly, $this->now()));
    }

    /**
     * @return list<array{0: string, 1: bool}>
     */
    public static function statusEligibilityProvider(): array
    {
        return [
            ['inbox', true],
            ['active', true],
            ['done', false],
            ['archived', false],
            ['trashed', false],
        ];
    }

    #[DataProvider('statusEligibilityProvider')]
    public function testEligibilityByStatus(string $status, bool $eligible): void
    {
        $todo = $this->todo(['status' => $status]);

        $this->assertSame($eligible, $this->orphans->isEligible($todo));
        if (!$eligible) {
            $this->assertSame([], $this->orphans->reasonsFor($todo, $this->now()));
        }
    }

    public function testReasonsAccumulateWithoutDuplicates(): void
    {
        $todo = $this->todo([
            'created' => new DateTime('2025-12-01 12:00:00'),
            'next_review_at' => new DateTime('2026-02-01 12:00:00'),
            'tags' => [],
        ]);

        $reasons = $this->orphans->reasonsFor($todo, $this->now());

        $this->assertSame([
            OrphanDetectionService::REASON_REVIEW_OVERDUE,
            OrphanDetectionService::REASON_NEVER_REVIEWED,
            OrphanDetectionService::REASON_STALE,
            OrphanDetectionService::REASON_NO_SECTION,
            OrphanDetectionService::REASON_NO_TAGS,
        ], $reasons);
        $this->assertSame($reasons, array_values(array_unique($reasons)));
    }

    public function testFullyOrganizedAndRecentlyReviewedTodoHasNoReasons(): void
    {
        $todo = $this->todo([
            'project_section_id' => 300,
            'tags' => [new Tag(['id' => 100, 'name' => 'Important'])],
            'last_reviewed_at' => new DateTime('2026-02-09 12:00:00'),
            'next_review_at' => new DateTime('2026-02-16 12:00:00'),
        ]);

        $this->assertSame([], $this->orphans->reasonsFor($todo, $this->now()));
    }

    public function testSuggestedActionsFollowReasons(): void
    {
        $suggestions = $this->orphans->suggestedActions([
            OrphanDetectionService::REASON_NO_SECTION,
            OrphanDetectionService::REASON_NO_TAGS,
            OrphanDetectionService::REASON_REVIEW_OVERDUE,
            OrphanDetectionService::REASON_STALE,
        ]);

        $this->assertSame(['assign_section', 'add_tag', 'mark_reviewed', 'archive_or_trash'], $suggestions);
    }

    public function testSuggestedActionsAreEmptyWithoutReasons(): void
    {
        $this->assertSame([], $this->orphans->suggestedActions([]));
    }

    public function testRulesNeverMutateTheTodo(): void
    {
        $todo = $this->todo();
        $todo->clean();
        $before = $todo->toArray();

        $this->orphans->reasonsFor($todo, $this->now());

        $this->assertSame($before, $todo->toArray());
        $this->assertFalse($todo->isDirty());
    }
}
