<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Model\Entity\Todo;
use App\Model\Table\TodosTable;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Exception\UnprocessableEntityException;
use Cake\Http\Response;

class TodosController extends AppController
{
    /**
     * List ToDos for the current user, optionally filtered by status.
     */
    public function index(): Response
    {
        $userId = $this->requireUserId();
        $status = $this->readStatusFilter();
        $page = $this->readPositiveQueryInt('page', 1);
        $limit = min($this->readPositiveQueryInt('limit', 20), 100);

        /** @var \App\Model\Table\TodosTable $todosTable */
        $todosTable = $this->fetchTable('Todos');
        $query = $todosTable->find()->where(['user_id' => $userId]);
        if ($status !== null) {
            $query->where(['status' => $status]);
        }

        $total = (clone $query)->count();
        $offset = ($page - 1) * $limit;
        $todos = $query
            ->orderBy(['id' => 'DESC'])
            ->limit($limit)
            ->offset($offset)
            ->all();

        $items = [];
        foreach ($todos as $todo) {
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
            throw new UnprocessableEntityException('Invalid ToDo payload.');
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
            throw new UnprocessableEntityException('Invalid ToDo payload.');
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
            ->where(['id' => (int)$id, 'user_id' => $userId])
            ->first();
        if ($todo === null) {
            throw new NotFoundException('ToDo not found.');
        }

        return $todo;
    }

    /**
     * Serialize ToDo entity fields for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeTodo(Todo $todo): array
    {
        return [
            'id' => (int)$todo->id,
            'user_id' => (int)$todo->user_id,
            'title' => (string)$todo->title,
            'notes' => $todo->notes === null ? null : (string)$todo->notes,
            'status' => (string)$todo->status,
            'created' => $todo->created?->format(DATE_ATOM),
            'modified' => $todo->modified?->format(DATE_ATOM),
        ];
    }
}
