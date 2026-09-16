<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\Todo;
use App\Model\Table\RelatedTodosTable;
use App\Model\Table\TodosTable;
use Cake\I18n\DateTime;
use DomainException;
use Throwable;

/**
 * Single authority for ToDo relationship integrity: related links, parent/child hierarchy,
 * terminal objectives, and the child result callback.
 *
 * Relationships are only ever created between ToDos owned by the same user; ownership is resolved
 * by the caller and re-checked here.
 */
class TodoRelationshipService
{
    /**
     * Maximum ancestry depth walked while checking for cycles.
     */
    public const MAX_HIERARCHY_DEPTH = 50;

    public const MAX_OBJECTIVE_LENGTH = 2000;

    /**
     * @param \App\Model\Table\TodosTable $todos ToDo table used for persistence and lookups.
     * @param \App\Model\Table\RelatedTodosTable $relatedTodos Related ToDo join table.
     */
    public function __construct(
        private TodosTable $todos,
        private RelatedTodosTable $relatedTodos,
    ) {
    }

    /**
     * Link two ToDos owned by the same user.
     *
     * Pairs are stored canonically (lower id first) so the database unique index rejects duplicates
     * in either direction.
     *
     * @throws \DomainException When the link is invalid or already present.
     */
    public function relate(Todo $todo, Todo $other): void
    {
        $this->assertSameOwner($todo, $other);
        $todoId = (int)$todo->id;
        $otherId = (int)$other->id;
        if ($todoId === $otherId) {
            throw new DomainException('A ToDo cannot be related to itself.');
        }

        $link = $this->relatedTodos->newEntity([
            'todo_id' => min($todoId, $otherId),
            'related_todo_id' => max($todoId, $otherId),
        ]);
        if ($link->hasErrors()) {
            throw new DomainException('Invalid relationship payload.');
        }

        try {
            $saved = $this->relatedTodos->save($link, ['checkExisting' => false]);
        } catch (Throwable $exception) {
            if ($this->isUniqueViolation($exception)) {
                throw new DomainException('These ToDos are already related.');
            }

            throw $exception;
        }
        if ($saved === false) {
            throw new DomainException('These ToDos are already related.');
        }
    }

    /**
     * Remove the link between two ToDos.
     *
     * @throws \DomainException When no link exists.
     */
    public function unrelate(Todo $todo, Todo $other): void
    {
        $this->assertSameOwner($todo, $other);
        $todoId = (int)$todo->id;
        $otherId = (int)$other->id;

        $deleted = $this->relatedTodos->deleteAll([
            'todo_id' => min($todoId, $otherId),
            'related_todo_id' => max($todoId, $otherId),
        ]);
        if ($deleted < 1) {
            throw new DomainException('These ToDos are not related.');
        }
    }

    /**
     * Ids of all ToDos related to the given ToDo, in either stored direction.
     *
     * @return list<int>
     */
    public function relatedIds(Todo $todo): array
    {
        $todoId = (int)$todo->id;
        $ids = [];
        $rows = $this->relatedTodos->find()
            ->where(['OR' => [['todo_id' => $todoId], ['related_todo_id' => $todoId]]])
            ->orderBy(['todo_id' => 'ASC', 'related_todo_id' => 'ASC'])
            ->all();
        foreach ($rows as $row) {
            $left = (int)$row->get('todo_id');
            $right = (int)$row->get('related_todo_id');
            $ids[] = $left === $todoId ? $right : $left;
        }
        sort($ids);

        return $ids;
    }

    /**
     * Attach or detach a parent ToDo.
     *
     * @throws \DomainException When the parent is invalid or would create a cycle.
     */
    public function setParent(Todo $todo, ?Todo $parent): Todo
    {
        if ($parent === null) {
            $todo->set('parent_todo_id', null);

            return $this->todos->saveOrFail($todo);
        }

        $this->assertSameOwner($todo, $parent);
        if ((int)$parent->id === (int)$todo->id) {
            throw new DomainException('A ToDo cannot be its own parent.');
        }
        if ($this->wouldCreateCycle((int)$todo->id, (int)$parent->id)) {
            throw new DomainException('This parent would create a cycle in the hierarchy.');
        }

        $todo->set('parent_todo_id', (int)$parent->id);

        return $this->todos->saveOrFail($todo);
    }

    /**
     * Whether making `$parentId` the parent of `$todoId` would create a cycle.
     */
    public function wouldCreateCycle(int $todoId, int $parentId): bool
    {
        if ($todoId === $parentId) {
            return true;
        }

        $seen = [];
        $currentId = $parentId;
        for ($depth = 0; $depth < self::MAX_HIERARCHY_DEPTH; $depth++) {
            if ($currentId === $todoId) {
                return true;
            }
            if (isset($seen[$currentId])) {
                return true;
            }
            $seen[$currentId] = true;

            $parent = $this->todos->find()
                ->select(['parent_todo_id'])
                ->where(['id' => $currentId])
                ->first();
            $next = $parent?->get('parent_todo_id');
            if ($next === null) {
                return false;
            }
            $currentId = (int)$next;
        }

        return true;
    }

    /**
     * Set or clear the terminal objective describing what completion means.
     *
     * @throws \DomainException When the objective text is invalid.
     */
    public function setTerminalObjective(Todo $todo, ?string $objective): Todo
    {
        if ($objective !== null) {
            $objective = trim($objective);
            if ($objective === '') {
                $objective = null;
            } elseif (mb_strlen($objective) > self::MAX_OBJECTIVE_LENGTH) {
                throw new DomainException(sprintf(
                    'Terminal objective must be at most %d characters.',
                    self::MAX_OBJECTIVE_LENGTH,
                ));
            }
        }

        $todo->set('terminal_objective', $objective);

        return $this->todos->saveOrFail($todo);
    }

    /**
     * Record that a ToDo satisfied its terminal objective and report its result.
     *
     * The parent is never mutated; the result is recorded on the reporting ToDo so a parent can
     * read it through the hierarchy view.
     *
     * @throws \DomainException When the ToDo cannot report a result.
     */
    public function reportResult(Todo $todo, string $result): Todo
    {
        $result = trim($result);
        if ($result === '') {
            throw new DomainException('A result summary is required.');
        }
        if (mb_strlen($result) > self::MAX_OBJECTIVE_LENGTH) {
            throw new DomainException(sprintf(
                'Result summary must be at most %d characters.',
                self::MAX_OBJECTIVE_LENGTH,
            ));
        }
        if ($todo->parent_todo_id === null) {
            throw new DomainException('Only a child ToDo can report a result to its parent.');
        }
        if ($todo->objective_satisfied_at !== null) {
            throw new DomainException('This ToDo has already reported its result.');
        }
        if ((string)$todo->status === TodoLifecycleService::STATUS_TRASHED) {
            throw new DomainException('A trashed ToDo cannot report a result.');
        }
        if (!$this->todos->exists(['id' => (int)$todo->parent_todo_id])) {
            throw new DomainException('The parent ToDo no longer exists.');
        }

        $todo->set('objective_satisfied_at', DateTime::now());
        $todo->set('result_summary', $result);

        return $this->todos->saveOrFail($todo);
    }

    /**
     * Ids of the direct children of a ToDo.
     *
     * @return list<int>
     */
    public function childIds(Todo $todo): array
    {
        $ids = [];
        $rows = $this->todos->find()
            ->select(['id'])
            ->where(['parent_todo_id' => (int)$todo->id])
            ->orderBy(['id' => 'ASC'])
            ->all();
        foreach ($rows as $row) {
            $ids[] = (int)$row->get('id');
        }

        return $ids;
    }

    /**
     * Whether a ToDo still has children, which blocks permanent deletion.
     */
    public function hasChildren(Todo $todo): bool
    {
        return $this->todos->exists(['parent_todo_id' => (int)$todo->id]);
    }

    /**
     * Require that both ToDos belong to the same owner.
     *
     * @throws \DomainException When ownership differs.
     */
    private function assertSameOwner(Todo $todo, Todo $other): void
    {
        if ((int)$todo->user_id !== (int)$other->user_id) {
            throw new DomainException('ToDos owned by different users cannot be linked.');
        }
    }

    /**
     * Detect unique-constraint violations raised by concurrent inserts.
     */
    private function isUniqueViolation(Throwable $exception): bool
    {
        if ((string)$exception->getCode() === '23505') {
            return true;
        }
        $previous = $exception->getPrevious();

        return $previous instanceof Throwable && $this->isUniqueViolation($previous);
    }
}
