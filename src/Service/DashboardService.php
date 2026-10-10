<?php
declare(strict_types=1);

namespace App\Service;

use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorInterface;

/**
 * Assembles the daily-use dashboard summary from existing domain authorities.
 *
 * Aggregate predicates mirror ReviewSchedulingService and OrphanDetectionService;
 * lifecycle exclusions and the stale interval come from their domain constants.
 */
class DashboardService
{
    /**
     * Default number of entries in each `recent` list.
     */
    public const DEFAULT_RECENT_LIMIT = 5;

    /**
     * Maximum number of entries in each `recent` list.
     */
    public const MAX_RECENT_LIMIT = 50;

    /**
     * @param \Cake\ORM\Locator\LocatorInterface $tables Table locator used to resolve models.
     * @param \App\Service\OrphanDetectionService $orphans Authoritative orphan reasoning.
     * @param \App\Service\ActivityRecorder $activity Authoritative activity reader.
     * @param \App\Service\ReviewSchedulingService $reviews Authoritative review due-ness.
     */
    public function __construct(
        private LocatorInterface $tables,
        OrphanDetectionService $orphans,
        private ActivityRecorder $activity,
        ReviewSchedulingService $reviews,
    ) {
        // Retain the existing constructor contract; aggregation no longer hydrates domain entities.
        unset($orphans, $reviews);
    }

    /**
     * Build the dashboard summary for one user.
     *
     * @return array<string, mixed>
     */
    public function summarize(int $userId, int $recentLimit = self::DEFAULT_RECENT_LIMIT): array
    {
        $recentLimit = max(1, min($recentLimit, self::MAX_RECENT_LIMIT));
        $now = DateTime::now();
        $counts = $this->aggregateCounts($userId, $now);

        return [
            'counts' => $counts,
            'recent' => [
                'todos' => $this->recentTodos($userId, $recentLimit),
                'projects' => $this->recentOwned('Projects', $userId, $recentLimit),
                'notebooks' => $this->recentOwned('Notebooks', $userId, $recentLimit),
                'libraries' => $this->recentOwned('Libraries', $userId, $recentLimit),
                'activity' => $this->recentActivity($userId, $recentLimit),
            ],
        ];
    }

    /**
     * Return one row, regardless of how many ToDos participate in reasoning.
     *
     * @return array{inbox: int, active: int, review_due: int, orphans: int}
     */
    private function aggregateCounts(int $userId, DateTime $now): array
    {
        $todos = $this->tables->get('Todos');
        $connection = $todos->getConnection();
        $driver = $connection->getDriver();
        $todoTable = $driver->quoteIdentifier($todos->getTable());
        $tagTable = $driver->quoteIdentifier($this->tables->get('Tags')->getTable());
        $linkTable = $driver->quoteIdentifier($this->tables->get('TodosTags')->getTable());
        $parameters = [
            'user_id' => $userId,
            'inbox' => TodoLifecycleService::STATUS_INBOX,
            'active' => TodoLifecycleService::STATUS_ACTIVE,
            'due_at' => $now,
            'orphan_due_at' => $now,
            'stale_at' => $now->subDays(OrphanDetectionService::STALE_AFTER_DAYS),
        ];
        $excluded = [];
        foreach (OrphanDetectionService::EXCLUDED_STATUSES as $index => $status) {
            $name = 'excluded_' . $index;
            $excluded[] = ':' . $name;
            $parameters[$name] = $status;
        }
        $eligible = 't.status NOT IN (' . implode(', ', $excluded) . ')';
        // EXISTS preserves withTags semantics without multiplying ToDos with multiple Tags.
        $sql = <<<SQL
            SELECT
                COUNT(CASE WHEN t.status = :inbox THEN 1 END) AS inbox,
                COUNT(CASE WHEN t.status = :active THEN 1 END) AS active,
                COUNT(CASE WHEN $eligible AND t.next_review_at <= :due_at THEN 1 END) AS review_due,
                COUNT(CASE WHEN $eligible AND (
                    t.next_review_at <= :orphan_due_at
                    OR t.last_reviewed_at IS NULL
                    OR COALESCE(t.last_reviewed_at, t.created) <= :stale_at
                    OR (t.project_section_id IS NULL AND t.notebook_section_id IS NULL)
                    OR NOT EXISTS (
                        SELECT 1 FROM $linkTable tt
                        INNER JOIN $tagTable tags ON tags.id = tt.tag_id
                        WHERE tt.todo_id = t.id
                    )
                ) THEN 1 END) AS orphans
            FROM $todoTable t
            WHERE t.user_id = :user_id
            SQL;
        $statement = $connection->execute($sql, $parameters, [
            'user_id' => 'integer',
            'due_at' => 'datetime',
            'orphan_due_at' => 'datetime',
            'stale_at' => 'datetime',
        ]);
        try {
            $row = $statement->fetch('assoc');

            return [
                'inbox' => (int)$row['inbox'],
                'active' => (int)$row['active'],
                'review_due' => (int)$row['review_due'],
                'orphans' => (int)$row['orphans'],
            ];
        } finally {
            $statement->closeCursor();
        }
    }

    /**
     * The user's most recently touched non-hidden ToDos.
     *
     * @return list<array<string, mixed>>
     */
    private function recentTodos(int $userId, int $limit): array
    {
        $items = [];
        $query = $this->tables->get('Todos')
            ->find()
            ->where([
                'Todos.user_id' => $userId,
                'Todos.status NOT IN' => TodoLifecycleService::HIDDEN_BY_DEFAULT,
            ])
            ->orderBy(['Todos.modified' => 'DESC', 'Todos.id' => 'DESC'])
            ->limit($limit);
        foreach ($query as $todo) {
            /** @var \App\Model\Entity\Todo $todo */
            $items[] = [
                'id' => (int)$todo->id,
                'title' => (string)$todo->title,
                'status' => (string)$todo->status,
                'modified' => $todo->modified?->format(DATE_ATOM),
            ];
        }

        return $items;
    }

    /**
     * The user's most recently created rows from a simple owned table.
     *
     * @return list<array<string, mixed>>
     */
    private function recentOwned(string $table, int $userId, int $limit): array
    {
        $items = [];
        $query = $this->tables->get($table)
            ->find()
            ->where([$table . '.user_id' => $userId])
            ->orderBy([$table . '.id' => 'DESC'])
            ->limit($limit);
        foreach ($query as $row) {
            $items[] = [
                'id' => (int)$row->get('id'),
                'name' => (string)$row->get('name'),
            ];
        }

        return $items;
    }

    /**
     * The user's most recent activity entries.
     *
     * @return list<array<string, mixed>>
     */
    private function recentActivity(int $userId, int $limit): array
    {
        $items = [];
        foreach ($this->activity->forActor($userId)->limit($limit) as $record) {
            $items[] = [
                'id' => (int)$record->id,
                'action' => (string)$record->action,
                'subject_type' => (string)$record->subject_type,
                'subject_id' => (int)$record->subject_id,
                'created' => $record->created?->format(DATE_ATOM),
            ];
        }

        return $items;
    }
}
