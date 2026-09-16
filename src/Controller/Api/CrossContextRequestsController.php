<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Model\Entity\CrossContextRequest;
use App\Service\CrossContextRequestService;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ConflictException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use DomainException;

class CrossContextRequestsController extends AppController
{
    /**
     * Create a cross-context request.
     */
    public function add(): Response
    {
        $userId = $this->requireUserId();

        try {
            $request = $this->requests()->send($userId, $this->readBody());
        } catch (DomainException $exception) {
            throw $this->translate($exception);
        }

        $this->activity()->record($userId, 'request.sent', 'cross_context_request', (int)$request->id, [
            'target_type' => (string)$request->target_type,
            'target_id' => (int)$request->target_id,
        ]);

        return $this->respond(['request' => $this->serialize($request)], [], 201);
    }

    /**
     * Pending requests addressed to contexts the caller may resolve.
     */
    public function inbox(): Response
    {
        $userId = $this->requireUserId();

        $items = [];
        foreach ($this->requests()->inbox($userId) as $request) {
            $items[] = $this->serialize($request);
        }

        return $this->respond(['items' => $items]);
    }

    /**
     * Requests the caller has sent.
     */
    public function outbox(): Response
    {
        $userId = $this->requireUserId();

        $items = [];
        foreach ($this->requests()->outbox($userId) as $request) {
            $items[] = $this->serialize($request);
        }

        return $this->respond(['items' => $items]);
    }

    /**
     * View a single request the caller sent or may resolve.
     */
    public function view(string $id): Response
    {
        $userId = $this->requireUserId();

        try {
            $request = $this->requests()->viewable($userId, $this->readId($id));
        } catch (DomainException $exception) {
            throw $this->translate($exception);
        }

        return $this->respond(['request' => $this->serialize($request)]);
    }

    /**
     * Recipient edits the wording of a pending request.
     */
    public function edit(string $id): Response
    {
        $userId = $this->requireUserId();

        try {
            $request = $this->requests()->modify($userId, $this->readId($id), $this->readBody());
        } catch (DomainException $exception) {
            throw $this->translate($exception);
        }

        $this->activity()->record($userId, 'request.modified', 'cross_context_request', (int)$request->id, [
            'target_type' => (string)$request->target_type,
            'target_id' => (int)$request->target_id,
        ]);

        return $this->respond(['request' => $this->serialize($request)]);
    }

    /**
     * Accept a pending request.
     */
    public function accept(string $id): Response
    {
        $userId = $this->requireUserId();
        $body = $this->readBody();
        $options = [];
        if (array_key_exists('link_todo_id', $body)) {
            $options['link_todo_id'] = is_string($body['link_todo_id']) && ctype_digit($body['link_todo_id'])
                ? (int)$body['link_todo_id']
                : $body['link_todo_id'];
        }
        if (($body['create_todo'] ?? null) === true || ($body['create_todo'] ?? null) === '1') {
            $options['create_todo'] = true;
        }
        if (is_string($body['resolution_note'] ?? null)) {
            $options['resolution_note'] = (string)$body['resolution_note'];
        }

        try {
            $request = $this->requests()->accept($userId, $this->readId($id), $options);
        } catch (DomainException $exception) {
            throw $this->translate($exception);
        }

        $this->activity()->record($userId, 'request.accepted', 'cross_context_request', (int)$request->id, [
            'target_type' => (string)$request->target_type,
            'target_id' => (int)$request->target_id,
        ]);

        return $this->respond(['request' => $this->serialize($request)]);
    }

    /**
     * Reject a pending request.
     */
    public function reject(string $id): Response
    {
        $userId = $this->requireUserId();
        $body = $this->readBody();
        $note = is_string($body['resolution_note'] ?? null) ? (string)$body['resolution_note'] : null;

        try {
            $request = $this->requests()->reject($userId, $this->readId($id), $note);
        } catch (DomainException $exception) {
            throw $this->translate($exception);
        }

        $this->activity()->record($userId, 'request.rejected', 'cross_context_request', (int)$request->id, [
            'target_type' => (string)$request->target_type,
            'target_id' => (int)$request->target_id,
        ]);

        return $this->respond(['request' => $this->serialize($request)]);
    }

    /**
     * Record the callback describing the outcome to the source context.
     */
    public function callback(string $id): Response
    {
        $userId = $this->requireUserId();
        $body = $this->readBody();
        $summary = is_string($body['summary'] ?? null) ? (string)$body['summary'] : '';

        try {
            $request = $this->requests()->callback($userId, $this->readId($id), $summary);
        } catch (DomainException $exception) {
            throw $this->translate($exception);
        }

        $this->activity()->record($userId, 'request.callback', 'cross_context_request', (int)$request->id, [
            'target_type' => (string)$request->target_type,
            'target_id' => (int)$request->target_id,
        ]);

        return $this->respond(['request' => $this->serialize($request)]);
    }

    /**
     * Map domain failures onto deterministic API results.
     */
    private function translate(DomainException $exception): NotFoundException|ConflictException|BadRequestException
    {
        $message = $exception->getMessage();
        if (str_contains($message, 'already')) {
            return new ConflictException($message, null, $exception);
        }
        if (str_contains($message, 'Not permitted') || str_contains($message, 'Unknown cross-context request')) {
            return new NotFoundException('Cross-context request not found.', null, $exception);
        }
        if (str_contains($message, 'Unknown')) {
            return new NotFoundException($message, null, $exception);
        }

        return new BadRequestException($message, null, $exception);
    }

    /**
     * Resolve the cross-context request authority.
     */
    private function requests(): CrossContextRequestService
    {
        return new CrossContextRequestService($this->getTableLocator(), $this->capabilities());
    }

    /**
     * Read and validate a positive integer route id.
     */
    private function readId(string $id): int
    {
        if (!ctype_digit($id) || (int)$id < 1) {
            throw new NotFoundException('Cross-context request not found.');
        }

        return (int)$id;
    }

    /**
     * Read a JSON request body.
     *
     * @return array<string, mixed>
     */
    private function readBody(): array
    {
        $data = $this->request->getData();
        if (!is_array($data)) {
            throw new BadRequestException('Malformed request body.');
        }

        return $data;
    }

    /**
     * Serialize a cross-context request for API responses.
     *
     * @return array<string, mixed>
     */
    private function serialize(CrossContextRequest $request): array
    {
        return [
            'id' => (int)$request->id,
            'source_type' => (string)$request->source_type,
            'source_id' => (int)$request->source_id,
            'target_type' => (string)$request->target_type,
            'target_id' => (int)$request->target_id,
            'request_type' => (string)$request->request_type,
            'title' => (string)$request->title,
            'body' => $request->body === null ? null : (string)$request->body,
            'status' => (string)$request->status,
            'created_by_user_id' => (int)$request->created_by_user_id,
            'resolved_by_user_id' => $request->resolved_by_user_id === null
                ? null
                : (int)$request->resolved_by_user_id,
            'resolved_at' => $request->resolved_at?->format(DATE_ATOM),
            'resolution_note' => $request->resolution_note,
            'resulting_todo_id' => $request->resulting_todo_id === null ? null : (int)$request->resulting_todo_id,
            'callback_summary' => $request->callback_summary,
            'callback_at' => $request->callback_at?->format(DATE_ATOM),
            'created' => $request->created?->format(DATE_ATOM),
        ];
    }
}
