<?php
declare(strict_types=1);

namespace App\Service;

use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorInterface;

/**
 * Assembles the daily-use dashboard summary from existing domain authorities.
 *
 * No business rule is redefined here: lifecycle exclusions come from TodoLifecycleService,
 * review due-ness from ReviewSchedulingService and orphan reasons from OrphanDetectionService.
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
        private OrphanDetectionService $orphans,
        private ActivityRecorder $activity,
        private ReviewSchedulingService $reviews,
    ) {
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
        $visible = $this->visibleTodos($userId);

        $reviewDue = 0;
        $orphans = 0;
        foreach ($visible as $todo) {
            if ($this->reviews->isDue($todo, $now)) {
                $reviewDue++;
            }
            if ($this->orphans->reasonsFor($todo, $now) !== []) {
                $orphans++;
            }
        }

        return [
            'counts' => [
                'inbox' => $this->countTodosWithStatus($userId, TodoLifecycleService::STATUS_INBOX),
                'active' => $this->countTodosWithStatus($userId, TodoLifecycleService::STATUS_ACTIVE),
                'review_due' => $reviewDue,
                'orphans' => $orphans,
            ],
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
     * Load the user's ToDos that participate in review and orphan reasoning.
     *
     * @return list<\App\Model\Entity\Todo>
     */
    private function visibleTodos(int $userId): array
    {
        $todos = [];
        $query = $this->tables->get('Todos')
            ->find('withTags')
            ->where([
                'Todos.user_id' => $userId,
                'Todos.status NOT IN' => OrphanDetectionService::EXCLUDED_STATUSES,
            ]);
        foreach ($query as $todo) {
            /** @var \App\Model\Entity\Todo $todo */
            $todos[] = $todo;
        }

        return $todos;
    }

    /**
     * Count the user's ToDos in one lifecycle status.
     */
    private function countTodosWithStatus(int $userId, string $status): int
    {
        return $this->tables->get('Todos')
            ->find()
            ->where(['Todos.user_id' => $userId, 'Todos.status' => $status])
            ->count();
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
