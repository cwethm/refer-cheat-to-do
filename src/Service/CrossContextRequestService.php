<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\CrossContextRequest;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorInterface;
use DomainException;

/**
 * Single authority for cross-context request mechanics.
 *
 * A request is a proposal, never a remote mutation. Sending requires `send_task` on the target,
 * resolving requires target-context authority, and a callback requires `callback` on the source.
 * Nothing here ever grants ongoing authority over another context.
 */
class CrossContextRequestService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ACCEPTED, self::STATUS_REJECTED];

    /**
     * @var list<string>
     */
    public const CONTEXT_TYPES = [CapabilityService::RESOURCE_PROJECT, CapabilityService::RESOURCE_NOTEBOOK];

    /**
     * @var list<string>
     */
    public const REQUEST_TYPES = ['task', 'question', 'suggestion'];

    /**
     * @param \Cake\ORM\Locator\LocatorInterface $tables Table locator used to resolve models.
     * @param \App\Service\CapabilityService $capabilities Authoritative capability evaluator.
     */
    public function __construct(
        private LocatorInterface $tables,
        private CapabilityService $capabilities,
    ) {
    }

    /**
     * Send a request from a context the actor may read into a context the actor may send tasks to.
     *
     * @param array<string, mixed> $payload
     * @throws \DomainException When the payload, contexts or capabilities are invalid.
     */
    public function send(int $actorUserId, array $payload): CrossContextRequest
    {
        $sourceType = $this->readContextType($payload, 'source_type');
        $sourceId = $this->readPositiveInt($payload, 'source_id');
        $targetType = $this->readContextType($payload, 'target_type');
        $targetId = $this->readPositiveInt($payload, 'target_id');
        $requestType = is_string($payload['request_type'] ?? null) ? (string)$payload['request_type'] : '';
        if (!in_array($requestType, self::REQUEST_TYPES, true)) {
            throw new DomainException('Unknown request type.');
        }
        if ($sourceType === $targetType && $sourceId === $targetId) {
            throw new DomainException('A context cannot send a request to itself.');
        }
        if ($this->capabilities->ownerIdFor($sourceType, $sourceId) === null) {
            throw new DomainException('Unknown source context.');
        }
        if ($this->capabilities->ownerIdFor($targetType, $targetId) === null) {
            throw new DomainException('Unknown target context.');
        }
        if (!$this->capabilities->allows($actorUserId, CapabilityService::CAP_READ, $sourceType, $sourceId)) {
            throw new DomainException('Not permitted to act for the source context.');
        }
        if (!$this->capabilities->allows($actorUserId, CapabilityService::CAP_SEND_TASK, $targetType, $targetId)) {
            throw new DomainException('Not permitted to send requests to the target context.');
        }

        $requests = $this->tables->get('CrossContextRequests');
        /** @var \App\Model\Entity\CrossContextRequest $request */
        $request = $requests->newEntity([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'request_type' => $requestType,
            'title' => is_string($payload['title'] ?? null) ? (string)$payload['title'] : '',
            'body' => is_string($payload['body'] ?? null) ? (string)$payload['body'] : null,
        ]);
        $request->set('status', self::STATUS_PENDING);
        $request->set('created_by_user_id', $actorUserId);
        if ($request->hasErrors() || $requests->save($request) === false) {
            throw new DomainException('Invalid cross-context request.');
        }

        return $request;
    }

    /**
     * Pending requests addressed to contexts the actor may resolve.
     *
     * @return list<\App\Model\Entity\CrossContextRequest>
     */
    public function inbox(int $actorUserId): array
    {
        $items = [];
        $rows = $this->tables->get('CrossContextRequests')->find()
            ->where(['status' => self::STATUS_PENDING])
            ->orderBy(['id' => 'ASC'])
            ->all();
        foreach ($rows as $row) {
            /** @var \App\Model\Entity\CrossContextRequest $row */
            if ($this->canResolve($actorUserId, $row)) {
                $items[] = $row;
            }
        }

        return $items;
    }

    /**
     * Requests the actor sent.
     *
     * @return list<\App\Model\Entity\CrossContextRequest>
     */
    public function outbox(int $actorUserId): array
    {
        $items = [];
        $rows = $this->tables->get('CrossContextRequests')->find()
            ->where(['created_by_user_id' => $actorUserId])
            ->orderBy(['id' => 'ASC'])
            ->all();
        foreach ($rows as $row) {
            /** @var \App\Model\Entity\CrossContextRequest $row */
            $items[] = $row;
        }

        return $items;
    }

    /**
     * Load a request the actor may see, either as sender or as a resolver of the target context.
     *
     * @throws \DomainException When the request does not exist or the actor may not see it.
     */
    public function viewable(int $actorUserId, int $requestId): CrossContextRequest
    {
        $request = $this->find($requestId);
        if ((int)$request->created_by_user_id === $actorUserId || $this->canResolve($actorUserId, $request)) {
            return $request;
        }

        throw new DomainException('Unknown cross-context request.');
    }

    /**
     * Recipient adjusts the wording of a pending request before deciding on it.
     *
     * @param array<string, mixed> $changes
     * @throws \DomainException When the actor may not resolve the request or it is already resolved.
     */
    public function modify(int $actorUserId, int $requestId, array $changes): CrossContextRequest
    {
        $request = $this->requireResolvable($actorUserId, $requestId);

        $requests = $this->tables->get('CrossContextRequests');
        $patch = [];
        if (is_string($changes['title'] ?? null)) {
            $patch['title'] = (string)$changes['title'];
        }
        if (array_key_exists('body', $changes) && (is_string($changes['body']) || $changes['body'] === null)) {
            $patch['body'] = $changes['body'];
        }
        if ($patch === []) {
            throw new DomainException('Nothing to modify.');
        }

        /** @var \App\Model\Entity\CrossContextRequest $request */
        $request = $requests->patchEntity($request, $patch, ['fields' => ['title', 'body']]);
        if ($request->hasErrors() || $requests->save($request) === false) {
            throw new DomainException('Invalid cross-context request.');
        }

        return $request;
    }

    /**
     * Accept a request, optionally linking an existing ToDo or creating one in the target context.
     *
     * The whole resolution is transactional: if ToDo creation fails, the request stays pending.
     *
     * @param array<string, mixed> $options
     * @throws \DomainException When the actor may not resolve the request or the options are invalid.
     */
    public function accept(int $actorUserId, int $requestId, array $options = []): CrossContextRequest
    {
        $request = $this->requireResolvable($actorUserId, $requestId);
        $requests = $this->tables->get('CrossContextRequests');
        $targetOwnerId = (int)$this->capabilities->ownerIdFor($request->target_type, (int)$request->target_id);

        return $requests->getConnection()->transactional(
            function () use ($request, $requests, $options, $actorUserId, $targetOwnerId): CrossContextRequest {
                $todoId = null;
                if (array_key_exists('link_todo_id', $options)) {
                    $todoId = $this->resolveLinkedTodoId($options, $targetOwnerId);
                } elseif (array_key_exists('create_todo', $options) && $options['create_todo'] === true) {
                    $todoId = $this->createTodo($request, $targetOwnerId);
                }

                $request->set('status', self::STATUS_ACCEPTED);
                $request->set('resolved_by_user_id', $actorUserId);
                $request->set('resolved_at', new DateTime());
                $request->set('resulting_todo_id', $todoId);
                if (is_string($options['resolution_note'] ?? null)) {
                    $request->set('resolution_note', (string)$options['resolution_note']);
                }
                $requests->saveOrFail($request);

                return $request;
            },
        );
    }

    /**
     * Reject a pending request.
     *
     * @throws \DomainException When the actor may not resolve the request or it is already resolved.
     */
    public function reject(int $actorUserId, int $requestId, ?string $note = null): CrossContextRequest
    {
        $request = $this->requireResolvable($actorUserId, $requestId);

        $request->set('status', self::STATUS_REJECTED);
        $request->set('resolved_by_user_id', $actorUserId);
        $request->set('resolved_at', new DateTime());
        $request->set('resolution_note', $note);
        $this->tables->get('CrossContextRequests')->saveOrFail($request);

        return $request;
    }

    /**
     * Record a callback to the source context describing the outcome.
     *
     * @throws \DomainException When the request is unresolved, already answered, or the actor lacks
     *   the `callback` capability on the source context.
     */
    public function callback(int $actorUserId, int $requestId, string $summary): CrossContextRequest
    {
        $request = $this->find($requestId);
        if ($request->isPending()) {
            throw new DomainException('A request must be resolved before a callback.');
        }
        if ($request->callback_at !== null) {
            throw new DomainException('This request already has a callback.');
        }
        if ($summary === '' || mb_strlen($summary) > 10000) {
            throw new DomainException('Invalid callback summary.');
        }
        if (!$this->canResolve($actorUserId, $request)) {
            throw new DomainException('Unknown cross-context request.');
        }
        $allowed = $this->capabilities->allows(
            $actorUserId,
            CapabilityService::CAP_CALLBACK,
            $request->source_type,
            (int)$request->source_id,
        );
        if (!$allowed) {
            throw new DomainException('Not permitted to call back to the source context.');
        }

        $request->set('callback_summary', $summary);
        $request->set('callback_at', new DateTime());
        $this->tables->get('CrossContextRequests')->saveOrFail($request);

        return $request;
    }

    /**
     * Whether the actor currently holds target-context authority over a request.
     *
     * Authority is re-evaluated on every call, so a revoked capability takes effect immediately.
     */
    public function canResolve(int $actorUserId, CrossContextRequest $request): bool
    {
        return $this->capabilities->allows(
            $actorUserId,
            CapabilityService::CAP_CONTRIBUTE,
            $request->target_type,
            (int)$request->target_id,
        );
    }

    /**
     * Load a pending request the actor may resolve.
     *
     * @throws \DomainException When the request is missing, already resolved, or not resolvable.
     */
    private function requireResolvable(int $actorUserId, int $requestId): CrossContextRequest
    {
        $request = $this->find($requestId);
        if (!$this->canResolve($actorUserId, $request)) {
            throw new DomainException('Unknown cross-context request.');
        }
        if (!$request->isPending()) {
            throw new DomainException('This request is already resolved.');
        }

        return $request;
    }

    /**
     * Load a request by id.
     *
     * @throws \DomainException When it does not exist.
     */
    private function find(int $requestId): CrossContextRequest
    {
        /** @var \App\Model\Entity\CrossContextRequest|null $request */
        $request = $this->tables->get('CrossContextRequests')->find()
            ->where(['id' => $requestId])
            ->first();
        if ($request === null) {
            throw new DomainException('Unknown cross-context request.');
        }

        return $request;
    }

    /**
     * Validate a ToDo the recipient wants to link, which must belong to the target owner.
     *
     * @param array<string, mixed> $options
     */
    private function resolveLinkedTodoId(array $options, int $targetOwnerId): int
    {
        $todoId = $options['link_todo_id'];
        if (!is_int($todoId) || $todoId < 1) {
            throw new DomainException('Invalid linked ToDo.');
        }
        if (!$this->tables->get('Todos')->exists(['id' => $todoId, 'user_id' => $targetOwnerId])) {
            throw new DomainException('Invalid linked ToDo.');
        }

        return $todoId;
    }

    /**
     * Create a ToDo in the target context owner's workspace from the request.
     */
    private function createTodo(CrossContextRequest $request, int $targetOwnerId): int
    {
        $todos = $this->tables->get('Todos');
        $todo = $todos->newEntity([
            'user_id' => $targetOwnerId,
            'title' => (string)$request->title,
            'notes' => $request->body,
            'status' => TodoLifecycleService::STATUS_INBOX,
        ]);
        if ($todo->hasErrors()) {
            throw new DomainException('Unable to create a ToDo from this request.');
        }
        $todos->saveOrFail($todo);

        return (int)$todo->get('id');
    }

    /**
     * Read a known context type from a payload.
     *
     * @param array<string, mixed> $payload
     */
    private function readContextType(array $payload, string $field): string
    {
        $value = $payload[$field] ?? null;
        if (!is_string($value) || !in_array($value, self::CONTEXT_TYPES, true)) {
            throw new DomainException('Unknown context type.');
        }

        return $value;
    }

    /**
     * Read a required positive integer from a payload.
     *
     * @param array<string, mixed> $payload
     */
    private function readPositiveInt(array $payload, string $field): int
    {
        $value = $payload[$field] ?? null;
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && (int)$value > 0) {
            return (int)$value;
        }

        throw new DomainException(sprintf('Invalid field `%s`.', $field));
    }
}
