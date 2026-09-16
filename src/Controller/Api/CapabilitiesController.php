<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Model\Entity\CapabilityGrant;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ConflictException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use DomainException;

class CapabilitiesController extends AppController
{
    /**
     * List the active grants on a resource the caller may administer.
     */
    public function index(): Response
    {
        $userId = $this->requireUserId();
        $resourceType = (string)$this->request->getQuery('resource_type', '');
        $resourceId = $this->readPositiveQueryInt('resource_id');
        $capabilities = $this->capabilities();

        if (!$capabilities->isKnownResourceType($resourceType)) {
            throw new BadRequestException('Unknown resource type.');
        }
        if ($capabilities->ownerIdFor($resourceType, $resourceId) === null) {
            throw new NotFoundException('Resource not found.');
        }
        if (!$capabilities->canManagePermissions($userId, $resourceType, $resourceId)) {
            throw new NotFoundException('Resource not found.');
        }

        $items = [];
        foreach ($capabilities->grantsForResource($resourceType, $resourceId) as $grant) {
            $items[] = $this->serializeGrant($grant);
        }

        return $this->respond(['items' => $items]);
    }

    /**
     * Create an explicit capability grant.
     */
    public function add(): Response
    {
        $userId = $this->requireUserId();
        $data = $this->request->getData();
        if (!is_array($data)) {
            throw new BadRequestException('Malformed request body.');
        }

        $subjectUserId = $this->readPositiveInt($data, 'subject_user_id');
        $resourceId = $this->readPositiveInt($data, 'resource_id');
        $resourceType = is_string($data['resource_type'] ?? null) ? (string)$data['resource_type'] : '';
        $capability = is_string($data['capability'] ?? null) ? (string)$data['capability'] : '';

        try {
            $grant = $this->capabilities()->grant($userId, $subjectUserId, $capability, $resourceType, $resourceId);
        } catch (DomainException $exception) {
            throw $this->translate($exception);
        }

        return $this->respond(['grant' => $this->serializeGrant($grant)], [], 201);
    }

    /**
     * Revoke an existing capability grant.
     */
    public function delete(string $id): Response
    {
        $userId = $this->requireUserId();
        if (!ctype_digit($id) || (int)$id < 1) {
            throw new NotFoundException('Capability grant not found.');
        }

        try {
            $grant = $this->capabilities()->revoke($userId, (int)$id);
        } catch (DomainException $exception) {
            throw $this->translate($exception);
        }

        return $this->respond([
            'grant' => $this->serializeGrant($grant),
            'message' => 'Capability grant revoked.',
        ]);
    }

    /**
     * Map membership/permission domain failures onto deterministic API results.
     */
    private function translate(DomainException $exception): NotFoundException|ConflictException|ForbiddenException
    {
        $message = $exception->getMessage();
        if (str_contains($message, 'already granted')) {
            return new ConflictException($message, null, $exception);
        }
        if (str_contains($message, 'Not permitted')) {
            return new NotFoundException('Resource not found.', null, $exception);
        }
        if (str_contains($message, 'cannot grant capabilities to itself')) {
            return new ForbiddenException($message, null, $exception);
        }

        return new NotFoundException($message, null, $exception);
    }

    /**
     * Read a required positive integer from a request payload.
     *
     * @param array<string, mixed> $data
     */
    private function readPositiveInt(array $data, string $field): int
    {
        $value = $data[$field] ?? null;
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && (int)$value > 0) {
            return (int)$value;
        }

        throw new BadRequestException(sprintf('Invalid field `%s`.', $field));
    }

    /**
     * Read a required positive integer query value.
     */
    private function readPositiveQueryInt(string $field): int
    {
        $value = $this->request->getQuery($field);
        if (is_string($value) && ctype_digit($value) && (int)$value > 0) {
            return (int)$value;
        }

        throw new BadRequestException(sprintf('Invalid query parameter `%s`.', $field));
    }

    /**
     * Serialize a capability grant for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeGrant(CapabilityGrant $grant): array
    {
        return [
            'id' => (int)$grant->id,
            'subject_user_id' => (int)$grant->subject_user_id,
            'grantor_user_id' => (int)$grant->grantor_user_id,
            'resource_type' => (string)$grant->resource_type,
            'resource_id' => (int)$grant->resource_id,
            'capability' => (string)$grant->capability,
            'revoked_at' => $grant->revoked_at?->format(DATE_ATOM),
            'created' => $grant->created?->format(DATE_ATOM),
        ];
    }
}
