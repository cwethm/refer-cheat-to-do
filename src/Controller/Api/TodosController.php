<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Http\Exception\ValidationException;
use App\Model\Entity\Tag;
use App\Model\Entity\Todo;
use App\Model\Table\TodosTable;
use App\Service\OrphanDetectionService;
use App\Service\ReviewSchedulingService;
use App\Service\TodoLifecycleService;
use App\Service\TodoRelationshipService;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ConflictException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use DomainException;
use InvalidArgumentException;
use Throwable;

class TodosController extends AppController
{
    /**
     * List ToDos for the current user, optionally filtered by status, search text, or tag.
     */
    public function index(): Response
    {
        $userId = $this->requireUserId();
        $status = $this->readStatusFilter();
        $search = $this->readSearchFilter();
        $tagId = $this->readTagFilter();
        $projectId = $this->readPositiveIdFilter('project');
        $sectionId = $this->readPositiveIdFilter('section');
        $notebookId = $this->readPositiveIdFilter('notebook');
        $notebookSectionId = $this->readPositiveIdFilter('notebook_section');
        $page = $this->readPositiveQueryInt('page', 1);
        $limit = min($this->readPositiveQueryInt('limit', 20), 100);

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');
        $query = $todosTable->find('withTags')->where(['Todos.user_id' => $userId]);
        if ($status !== null) {
            $query->where(['Todos.status' => $status]);
        } else {
            $query->where(['Todos.status NOT IN' => TodoLifecycleService::HIDDEN_BY_DEFAULT]);
        }
        if ($search !== null) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
            $query->where([
                'OR' => [
                    'Todos.title LIKE' => $like,
                    'Todos.notes LIKE' => $like,
                ],
            ]);
        }
        if ($tagId !== null) {
            $query->matching('Tags', function ($q) use ($tagId, $userId) {
                return $q->where([
                    'Tags.id' => $tagId,
                    'Tags.user_id' => $userId,
                ]);
            });
        }

        if ($projectId !== null || $sectionId !== null) {
            $query->innerJoinWith('ProjectSections.Projects', function ($q) use ($projectId, $sectionId, $userId) {
                $conditions = ['Projects.user_id' => $userId];
                if ($projectId !== null) {
                    $conditions['Projects.id'] = $projectId;
                }
                if ($sectionId !== null) {
                    $conditions['ProjectSections.id'] = $sectionId;
                }

                return $q->where($conditions);
            });
        }

        if ($notebookId !== null || $notebookSectionId !== null) {
            $query->innerJoinWith(
                'NotebookSections.Notebooks',
                function ($q) use ($notebookId, $notebookSectionId, $userId) {
                    $conditions = ['Notebooks.user_id' => $userId];
                    if ($notebookId !== null) {
                        $conditions['Notebooks.id'] = $notebookId;
                    }
                    if ($notebookSectionId !== null) {
                        $conditions['NotebookSections.id'] = $notebookSectionId;
                    }

                    return $q->where($conditions);
                },
            );
        }

        $total = (clone $query)->count();
        $offset = ($page - 1) * $limit;
        $todos = $query
            ->distinct(['Todos.id'])
            ->orderBy(['Todos.id' => 'DESC'])
            ->limit($limit)
            ->offset($offset)
            ->all();

        $items = [];
        foreach ($todos as $todo) {
            /** @var \App\Model\Entity\Todo $todo */
            $items[] = $this->serializeTodo($todo);
        }

        return $this->respond(
            ['items' => $items],
            ['pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]],
        );
    }

    /**
     * Create a new ToDo in the current user's workspace.
     */
    public function add(): Response
    {
        $userId = $this->requireUserId();
        $data = $this->request->getData();
        if (!is_array($data)) {
            throw new BadRequestException('Malformed request body.');
        }
        if (!array_key_exists('status', $data) || $data['status'] === '') {
            $data['status'] = TodoLifecycleService::STATUS_INBOX;
        }
        $this->assertDirectlyAssignableStatus($data);

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');
        /** @var \App\Model\Entity\Todo $todo */
        $todo = $todosTable->newEntity($data, ['fields' => ['title', 'notes', 'status']]);
        $todo->user_id = $userId;

        if ($todo->hasErrors()) {
            throw new ValidationException('Invalid ToDo payload.');
        }
        if (!$todosTable->save($todo)) {
            throw new InternalErrorException('Unable to save ToDo.');
        }

        return $this->respond(['todo' => $this->serializeTodo($todosTable->loadTags($todo))], [], 201);
    }

    /**
     * View a single owned ToDo by id.
     */
    public function view(string $id): Response
    {
        $userId = $this->requireUserId();
        $todo = $this->fetchOwnedTodoOrFail($id, $userId);

        return $this->respond(['todo' => $this->serializeTodo($todo)]);
    }

    /**
     * Attach a user-owned tag to a user-owned ToDo.
     */
    public function attachTag(string $id, string $tagId): Response
    {
        $userId = $this->requireUserId();
        $todo = $this->fetchOwnedTodoOrFail($id, $userId);
        $tag = $this->fetchOwnedTagOrFail($tagId, $userId);

        /** @var \App\Model\Table\TodosTagsTable $todosTags */
        $todosTags = $this->fetchTable('TodosTags');
        $todoId = (int)$todo->id;
        $resolvedTagId = (int)$tag->id;
        try {
            $attached = $todosTags->attachIfMissing($todoId, $resolvedTagId);
        } catch (Throwable $exception) {
            throw new InternalErrorException('Unable to attach tag.', null, $exception);
        }
        if (!$attached) {
            throw new ConflictException('Tag is already attached to this ToDo.');
        }

        return $this->respond([
            'todo_id' => $todoId,
            'tag_id' => $resolvedTagId,
            'message' => 'Tag attached.',
        ], [], 201);
    }

    /**
     * Detach a user-owned tag from a user-owned ToDo.
     */
    public function detachTag(string $id, string $tagId): Response
    {
        $userId = $this->requireUserId();
        $todo = $this->fetchOwnedTodoOrFail($id, $userId);
        $tag = $this->fetchOwnedTagOrFail($tagId, $userId);

        $todosTags = $this->fetchTable('TodosTags');
        $deleted = $todosTags->deleteAll([
            'todo_id' => (int)$todo->id,
            'tag_id' => (int)$tag->id,
        ]);
        if ($deleted < 1) {
            throw new NotFoundException('Tag attachment not found.');
        }

        return $this->respond([
            'todo_id' => (int)$todo->id,
            'tag_id' => (int)$tag->id,
            'message' => 'Tag detached.',
        ]);
    }

    /**
     * Update an existing owned ToDo.
     */
    public function edit(string $id): Response
    {
        $userId = $this->requireUserId();
        $todo = $this->fetchOwnedTodoOrFail($id, $userId);
        $data = $this->request->getData();
        if (!is_array($data)) {
            throw new BadRequestException('Malformed request body.');
        }

        $this->assertDirectlyAssignableStatus($data);

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');
        $todo = $todosTable->patchEntity($todo, $data, ['fields' => ['title', 'notes', 'status']]);
        if (array_key_exists('project_section_id', $data)) {
            $todo->set('project_section_id', $this->resolveOwnedSectionId(
                $data['project_section_id'],
                $userId,
                'ProjectSections',
                'Projects',
            ));
        }
        if (array_key_exists('notebook_section_id', $data)) {
            $todo->set('notebook_section_id', $this->resolveOwnedSectionId(
                $data['notebook_section_id'],
                $userId,
                'NotebookSections',
                'Notebooks',
            ));
        }
        if ($todo->project_section_id !== null && $todo->notebook_section_id !== null) {
            throw new ConflictException('A ToDo may belong to only one organizational section.');
        }
        if ($todo->hasErrors()) {
            throw new ValidationException('Invalid ToDo payload.');
        }
        if (!$todosTable->save($todo)) {
            throw new InternalErrorException('Unable to update ToDo.');
        }

        return $this->respond(['todo' => $this->serializeTodo($todosTable->loadTags($todo))]);
    }

    /**
     * Move an owned ToDo to `active`.
     */
    public function activate(string $id): Response
    {
        return $this->applyTransition($id, TodoLifecycleService::STATUS_ACTIVE);
    }

    /**
     * Move an owned ToDo to `done`.
     */
    public function complete(string $id): Response
    {
        return $this->applyTransition($id, TodoLifecycleService::STATUS_DONE);
    }

    /**
     * Move an owned ToDo to `archived`.
     */
    public function archive(string $id): Response
    {
        return $this->applyTransition($id, TodoLifecycleService::STATUS_ARCHIVED);
    }

    /**
     * Move an owned ToDo to `trashed`.
     */
    public function trash(string $id): Response
    {
        return $this->applyTransition($id, TodoLifecycleService::STATUS_TRASHED);
    }

    /**
     * Restore a trashed ToDo to the status it held before being trashed.
     */
    public function restore(string $id): Response
    {
        $userId = $this->requireUserId();
        $todo = $this->fetchOwnedTodoOrFail($id, $userId);

        try {
            $todo = $this->lifecycle()->restore($todo);
        } catch (DomainException $exception) {
            throw new ConflictException($exception->getMessage(), null, $exception);
        }

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');

        return $this->respond(['todo' => $this->serializeTodo($todosTable->loadTags($todo))]);
    }

    /**
     * Permanently delete a trashed ToDo.
     */
    public function permanentDelete(string $id): Response
    {
        $userId = $this->requireUserId();
        $todo = $this->fetchOwnedTodoOrFail($id, $userId);

        try {
            $this->lifecycle()->permanentlyDelete($todo);
        } catch (DomainException $exception) {
            throw new ConflictException($exception->getMessage(), null, $exception);
        }

        return $this->respond(['id' => (int)$id, 'message' => 'ToDo permanently deleted.']);
    }

    /**
     * Return the current user's review queue with explainable reasons.
     */
    public function reviewQueue(): Response
    {
        $userId = $this->requireUserId();
        $page = $this->readPositiveQueryInt('page', 1);
        $limit = min($this->readPositiveQueryInt('limit', 20), 100);
        $now = DateTime::now();

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');
        $candidates = $todosTable->find('withTags')
            ->where([
                'Todos.user_id' => $userId,
                'Todos.status NOT IN' => OrphanDetectionService::EXCLUDED_STATUSES,
            ])
            ->orderBy(['Todos.next_review_at ASC NULLS FIRST', 'Todos.id' => 'ASC'])
            ->all();

        $orphans = new OrphanDetectionService();
        $queue = [];
        foreach ($candidates as $todo) {
            /** @var \App\Model\Entity\Todo $todo */
            $reasons = $orphans->reasonsFor($todo, $now);
            if ($reasons === []) {
                continue;
            }
            $queue[] = [
                'todo' => $this->serializeTodo($todo),
                'reasons' => $reasons,
                'suggested_actions' => $orphans->suggestedActions($reasons),
            ];
        }

        $total = count($queue);
        $items = array_slice($queue, ($page - 1) * $limit, $limit);

        return $this->respond(
            ['items' => array_values($items)],
            ['pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]],
        );
    }

    /**
     * Record a review of an owned ToDo and schedule the next one.
     */
    public function markReviewed(string $id): Response
    {
        $userId = $this->requireUserId();
        $todo = $this->fetchOwnedTodoOrFail($id, $userId);
        $data = $this->readJsonObject();

        $interval = $this->readOptionalPositiveInt($data, 'review_interval_days');
        $nextReviewAt = $this->readOptionalDateTime($data, 'next_review_at');

        try {
            $todo = $this->reviewScheduling()->markReviewed($todo, $interval, $nextReviewAt);
        } catch (DomainException $exception) {
            throw new ValidationException($exception->getMessage());
        }

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');

        return $this->respond(['todo' => $this->serializeTodo($todosTable->loadTags($todo))]);
    }

    /**
     * Push the next review of an owned ToDo into the future.
     */
    public function snooze(string $id): Response
    {
        $userId = $this->requireUserId();
        $todo = $this->fetchOwnedTodoOrFail($id, $userId);
        $data = $this->readJsonObject();

        $days = $this->readOptionalPositiveInt($data, 'days');
        $until = $this->readOptionalDateTime($data, 'until');

        try {
            $todo = $this->reviewScheduling()->snooze($todo, $days, $until);
        } catch (DomainException $exception) {
            throw new ValidationException($exception->getMessage());
        }

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');

        return $this->respond(['todo' => $this->serializeTodo($todosTable->loadTags($todo))]);
    }

    /**
     * Link two owned ToDos as related work.
     */
    public function relate(string $id, string $relatedId): Response
    {
        $userId = $this->requireUserId();
        $todo = $this->fetchOwnedTodoOrFail($id, $userId);
        $other = $this->fetchOwnedTodoOrFail($relatedId, $userId);

        try {
            $this->relationships()->relate($todo, $other);
        } catch (DomainException $exception) {
            throw new ConflictException($exception->getMessage(), null, $exception);
        }

        return $this->respond(
            ['todo_id' => (int)$todo->id, 'related_todo_id' => (int)$other->id],
            [],
            201,
        );
    }

    /**
     * Remove the link between two owned ToDos.
     */
    public function unrelate(string $id, string $relatedId): Response
    {
        $userId = $this->requireUserId();
        $todo = $this->fetchOwnedTodoOrFail($id, $userId);
        $other = $this->fetchOwnedTodoOrFail($relatedId, $userId);

        try {
            $this->relationships()->unrelate($todo, $other);
        } catch (DomainException $exception) {
            throw new NotFoundException($exception->getMessage(), null, $exception);
        }

        return $this->respond([
            'todo_id' => (int)$todo->id,
            'related_todo_id' => (int)$other->id,
            'message' => 'Relationship removed.',
        ]);
    }

    /**
     * Set or clear the parent of an owned ToDo.
     */
    public function setParent(string $id): Response
    {
        $userId = $this->requireUserId();
        $todo = $this->fetchOwnedTodoOrFail($id, $userId);
        $data = $this->readJsonObject();

        $parent = null;
        $parentId = $data['parent_todo_id'] ?? null;
        if ($parentId !== null && $parentId !== '') {
            if (!is_int($parentId) && !(is_string($parentId) && ctype_digit($parentId))) {
                throw new ValidationException('Invalid `parent_todo_id` value.');
            }
            $parent = $this->fetchOwnedTodoOrFail((string)$parentId, $userId);
        }

        try {
            $todo = $this->relationships()->setParent($todo, $parent);
        } catch (DomainException $exception) {
            throw new ConflictException($exception->getMessage(), null, $exception);
        }

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');

        return $this->respond(['todo' => $this->serializeTodo($todosTable->loadTags($todo))]);
    }

    /**
     * Set or clear the terminal objective of an owned ToDo.
     */
    public function setObjective(string $id): Response
    {
        $userId = $this->requireUserId();
        $todo = $this->fetchOwnedTodoOrFail($id, $userId);
        $data = $this->readJsonObject();

        $objective = $data['terminal_objective'] ?? null;
        if ($objective !== null && !is_string($objective)) {
            throw new ValidationException('Invalid `terminal_objective` value.');
        }

        try {
            $todo = $this->relationships()->setTerminalObjective($todo, $objective);
        } catch (DomainException $exception) {
            throw new ValidationException($exception->getMessage());
        }

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');

        return $this->respond(['todo' => $this->serializeTodo($todosTable->loadTags($todo))]);
    }

    /**
     * Report a child ToDo's result back to its parent.
     */
    public function reportResult(string $id): Response
    {
        $userId = $this->requireUserId();
        $todo = $this->fetchOwnedTodoOrFail($id, $userId);
        $data = $this->readJsonObject();

        $result = $data['result'] ?? null;
        if (!is_string($result)) {
            throw new ValidationException('A `result` summary is required.');
        }

        try {
            $todo = $this->relationships()->reportResult($todo, $result);
        } catch (DomainException $exception) {
            throw new ConflictException($exception->getMessage(), null, $exception);
        }

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');

        return $this->respond(['todo' => $this->serializeTodo($todosTable->loadTags($todo))]);
    }

    /**
     * Return the parent, children and related ToDos of an owned ToDo.
     */
    public function hierarchy(string $id): Response
    {
        $userId = $this->requireUserId();
        $todo = $this->fetchOwnedTodoOrFail($id, $userId);
        $relationships = $this->relationships();

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');
        $parent = null;
        if ($todo->parent_todo_id !== null) {
            /** @var \App\Model\Entity\Todo|null $parentEntity */
            $parentEntity = $todosTable->find('withTags')
                ->where(['Todos.id' => (int)$todo->parent_todo_id, 'Todos.user_id' => $userId])
                ->first();
            $parent = $parentEntity === null ? null : $this->serializeTodo($parentEntity);
        }

        return $this->respond([
            'todo' => $this->serializeTodo($todo),
            'parent' => $parent,
            'children' => $this->serializeTodoIds($relationships->childIds($todo), $userId),
            'related' => $this->serializeTodoIds($relationships->relatedIds($todo), $userId),
        ]);
    }

    /**
     * Serialize owned ToDos referenced by id, skipping anything not owned by the caller.
     *
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    private function serializeTodoIds(array $ids, int $userId): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');
        $items = [];
        $rows = $todosTable->find('withTags')
            ->where(['Todos.id IN' => $ids, 'Todos.user_id' => $userId])
            ->orderBy(['Todos.id' => 'ASC'])
            ->all();
        foreach ($rows as $row) {
            /** @var \App\Model\Entity\Todo $row */
            $items[] = $this->serializeTodo($row);
        }

        return $items;
    }

    /**
     * Resolve the relationship authority for ToDo links and hierarchy.
     */
    private function relationships(): TodoRelationshipService
    {
        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');
        /** @var \App\Model\Table\RelatedTodosTable $relatedTodos */
        $relatedTodos = $this->fetchTable('RelatedTodos');

        return new TodoRelationshipService($todosTable, $relatedTodos);
    }

    /**
     * Resolve the review scheduling authority.
     */
    private function reviewScheduling(): ReviewSchedulingService
    {
        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');

        return new ReviewSchedulingService($todosTable);
    }

    /**
     * Read the request body as a JSON object.
     *
     * @return array<string, mixed>
     */
    private function readJsonObject(): array
    {
        $data = $this->request->getData();
        if ($data === null || $data === '') {
            return [];
        }
        if (!is_array($data)) {
            throw new BadRequestException('Malformed request body.');
        }

        return $data;
    }

    /**
     * Read an optional positive integer field from a request body.
     *
     * @param array<string, mixed> $data
     */
    private function readOptionalPositiveInt(array $data, string $field): ?int
    {
        if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
            return null;
        }
        $value = $data[$field];
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && (int)$value > 0) {
            return (int)$value;
        }

        throw new ValidationException(sprintf('Invalid `%s` value.', $field));
    }

    /**
     * Read an optional ISO-8601 date-time field from a request body.
     *
     * @param array<string, mixed> $data
     */
    private function readOptionalDateTime(array $data, string $field): ?DateTime
    {
        if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
            return null;
        }
        $value = $data[$field];
        if (!is_string($value)) {
            throw new ValidationException(sprintf('Invalid `%s` value.', $field));
        }
        foreach ([DATE_ATOM, 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            try {
                return DateTime::createFromFormat($format, $value);
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        throw new ValidationException(sprintf('Invalid `%s` value.', $field));
    }

    /**
     * Resolve the lifecycle authority for ToDo transitions.
     */
    private function lifecycle(): TodoLifecycleService
    {
        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');

        return new TodoLifecycleService($todosTable);
    }

    /**
     * Run one lifecycle transition for an owned ToDo.
     */
    private function applyTransition(string $id, string $status): Response
    {
        $userId = $this->requireUserId();
        $todo = $this->fetchOwnedTodoOrFail($id, $userId);

        try {
            $todo = $this->lifecycle()->transition($todo, $status);
        } catch (DomainException $exception) {
            throw new ConflictException($exception->getMessage(), null, $exception);
        }

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');

        return $this->respond(['todo' => $this->serializeTodo($todosTable->loadTags($todo))]);
    }

    /**
     * Reject statuses that may only be reached through explicit lifecycle actions.
     *
     * @param array<string, mixed> $data
     */
    private function assertDirectlyAssignableStatus(array $data): void
    {
        if (!array_key_exists('status', $data)) {
            return;
        }
        $status = $data['status'];
        if (is_string($status) && in_array($status, TodosTable::DIRECTLY_ASSIGNABLE_STATUSES, true)) {
            return;
        }
        if (is_string($status) && in_array($status, TodosTable::ALLOWED_STATUSES, true)) {
            throw new ConflictException('Use the explicit lifecycle action for this status.');
        }

        throw new ValidationException('Invalid ToDo payload.');
    }

    /**
     * Resolve optional status filter and validate it.
     */
    private function readStatusFilter(): ?string
    {
        $status = $this->request->getQuery('status');
        if ($status === null || $status === '') {
            return null;
        }
        if (!is_string($status) || !in_array($status, TodosTable::ALLOWED_STATUSES, true)) {
            throw new BadRequestException('Invalid status filter.');
        }

        return $status;
    }

    /**
     * Resolve optional text query filter.
     *
     * Documented policy: an empty or whitespace-only `q` value applies no text filter
     * instead of degrading into an unbounded `LIKE '%%'` scan.
     */
    private function readSearchFilter(): ?string
    {
        $query = $this->request->getQuery('q');
        if ($query === null || $query === '') {
            return null;
        }
        if (!is_string($query)) {
            throw new BadRequestException('Invalid search query.');
        }

        $query = trim($query);
        if ($query === '') {
            return null;
        }

        return $query;
    }

    /**
     * Resolve a positive integer id filter from the query string.
     */
    private function readPositiveIdFilter(string $field): ?int
    {
        $value = $this->request->getQuery($field);
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && (int)$value > 0) {
            return (int)$value;
        }

        throw new BadRequestException(sprintf('Invalid %s filter.', $field));
    }

    /**
     * Resolve a client-supplied section reference against the authenticated owner.
     *
     * Ownership is always resolved server-side; `null` unassigns the ToDo.
     */
    private function resolveOwnedSectionId(mixed $value, int $userId, string $table, string $owner): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value) && ctype_digit($value)) {
            $value = (int)$value;
        }
        if (!is_int($value) || $value < 1) {
            throw new BadRequestException('Invalid project section.');
        }

        $sections = $this->fetchTable($table);
        $exists = $sections->find()
            ->innerJoinWith($owner, fn($q) => $q->where([$owner . '.user_id' => $userId]))
            ->where([$table . '.id' => $value])
            ->count();
        if ($exists < 1) {
            throw new NotFoundException('Section not found.');
        }

        return $value;
    }

    /**
     * Resolve optional tag filter and validate it.
     */
    private function readTagFilter(): ?int
    {
        $tag = $this->request->getQuery('tag');
        if ($tag === null || $tag === '') {
            return null;
        }
        if (is_int($tag) && $tag > 0) {
            return $tag;
        }
        if (is_string($tag) && ctype_digit($tag) && (int)$tag > 0) {
            return (int)$tag;
        }

        throw new BadRequestException('Invalid tag filter.');
    }

    /**
     * Read a positive integer query value.
     */
    private function readPositiveQueryInt(string $field, int $default): int
    {
        $value = $this->request->getQuery($field, $default);
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && (int)$value > 0) {
            return (int)$value;
        }

        throw new BadRequestException(sprintf('Invalid query parameter `%s`.', $field));
    }

    /**
     * Find an owned ToDo by id or fail with 404.
     */
    private function fetchOwnedTodoOrFail(string $id, int $userId): Todo
    {
        if (!ctype_digit($id) || (int)$id < 1) {
            throw new NotFoundException('ToDo not found.');
        }

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');
        /** @var \App\Model\Entity\Todo|null $todo */
        $todo = $todosTable->find('withTags')
            ->where(['id' => (int)$id, 'user_id' => $userId])
            ->first();
        if ($todo === null) {
            throw new NotFoundException('ToDo not found.');
        }

        return $todo;
    }

    /**
     * Find an owned tag by id or fail with 404.
     */
    private function fetchOwnedTagOrFail(string $id, int $userId): Tag
    {
        if (!ctype_digit($id) || (int)$id < 1) {
            throw new NotFoundException('Tag not found.');
        }

        /** @var \App\Model\Table\TagsTable $tagsTable */
        $tagsTable = $this->fetchTable('Tags');
        /** @var \App\Model\Entity\Tag|null $tag */
        $tag = $tagsTable->find()
            ->where(['id' => (int)$id, 'user_id' => $userId])
            ->first();
        if ($tag === null) {
            throw new NotFoundException('Tag not found.');
        }

        return $tag;
    }

    /**
     * Serialize ToDo entity fields for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeTodo(Todo $todo): array
    {
        $tags = [];
        foreach ($todo->tags ?? [] as $tag) {
            if (!$tag instanceof Tag) {
                continue;
            }
            $tags[] = [
                'id' => (int)$tag->id,
                'name' => (string)$tag->name,
            ];
        }

        return [
            'id' => (int)$todo->id,
            'user_id' => (int)$todo->user_id,
            'title' => (string)$todo->title,
            'notes' => $todo->notes === null ? null : (string)$todo->notes,
            'status' => (string)$todo->status,
            'archived_at' => $todo->archived_at?->format(DATE_ATOM),
            'trashed_at' => $todo->trashed_at?->format(DATE_ATOM),
            'last_reviewed_at' => $todo->last_reviewed_at?->format(DATE_ATOM),
            'next_review_at' => $todo->next_review_at?->format(DATE_ATOM),
            'review_interval_days' => (int)$todo->review_interval_days,
            'parent_todo_id' => $todo->parent_todo_id === null ? null : (int)$todo->parent_todo_id,
            'terminal_objective' => $todo->terminal_objective === null ? null : (string)$todo->terminal_objective,
            'objective_satisfied_at' => $todo->objective_satisfied_at?->format(DATE_ATOM),
            'result_summary' => $todo->result_summary === null ? null : (string)$todo->result_summary,
            'project_section_id' => $todo->project_section_id === null ? null : (int)$todo->project_section_id,
            'notebook_section_id' => $todo->notebook_section_id === null ? null : (int)$todo->notebook_section_id,
            'tags' => $tags,
            'created' => $todo->created?->format(DATE_ATOM),
            'modified' => $todo->modified?->format(DATE_ATOM),
        ];
    }
}
