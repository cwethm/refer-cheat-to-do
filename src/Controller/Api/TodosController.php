<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Http\Exception\ValidationException;
use App\Model\Entity\Tag;
use App\Model\Entity\Todo;
use App\Model\Table\TodosTable;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ConflictException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
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
        $page = $this->readPositiveQueryInt('page', 1);
        $limit = min($this->readPositiveQueryInt('limit', 20), 100);

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');
        $query = $todosTable->find()->where(['Todos.user_id' => $userId]);
        if ($status !== null) {
            $query->where(['Todos.status' => $status]);
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

        $total = (clone $query)->count();
        $offset = ($page - 1) * $limit;
        $todos = $query
            ->distinct(['Todos.id'])
            ->orderBy(['Todos.id' => 'DESC'])
            ->contain(['Tags'])
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
            $data['status'] = 'inbox';
        }

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

        return $this->respond(['todo' => $this->serializeTodo($todo)], [], 201);
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
            $attached = $todosTags->attach($todoId, $resolvedTagId);
        } catch (Throwable $exception) {
            throw new InternalErrorException('Unable to attach tag.');
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

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');
        $todo = $todosTable->patchEntity($todo, $data, ['fields' => ['title', 'notes', 'status']]);
        if ($todo->hasErrors()) {
            throw new ValidationException('Invalid ToDo payload.');
        }
        if (!$todosTable->save($todo)) {
            throw new InternalErrorException('Unable to update ToDo.');
        }

        return $this->respond(['todo' => $this->serializeTodo($todo)]);
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
        $todo = $todosTable->find()
            ->contain(['Tags'])
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
            'tags' => $tags,
            'created' => $todo->created?->format(DATE_ATOM),
            'modified' => $todo->modified?->format(DATE_ATOM),
        ];
    }
}
