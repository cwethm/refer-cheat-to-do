<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Http\Exception\ValidationException;
use App\Model\Entity\ResearchCollection;
use App\Model\Entity\ResearchCollectionItem;
use App\Service\CapabilityService;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

class ResearchCollectionsController extends AppController
{
    private const MAX_CONTENT_BYTES = 65536;

    /**
     * List collections visible to the authenticated user.
     */
    public function index(): Response
    {
        $userId = $this->requireUserId();
        $page = $this->readPositiveQueryInt('page', 1);
        $limit = min($this->readPositiveQueryInt('limit', 20), 100);

        $collections = $this->fetchTable('ResearchCollections');
        $grants = $this->fetchTable('CapabilityGrants')->find()
            ->select(['resource_id'])
            ->where([
                'subject_user_id' => $userId,
                'resource_type' => CapabilityService::RESOURCE_RESEARCH_COLLECTION,
                'capability' => CapabilityService::CAP_READ,
                'revoked_at IS' => null,
            ]);
        $query = $collections->find()
            ->where([
                'OR' => [
                    ['ResearchCollections.user_id' => $userId],
                    ['ResearchCollections.id IN' => $grants],
                ],
            ]);
        $total = (clone $query)->count();
        $items = [];
        foreach (
            $query->orderBy(['ResearchCollections.id' => 'DESC'])
            ->limit($limit)
            ->offset(($page - 1) * $limit) as $collection
        ) {
            /** @var \App\Model\Entity\ResearchCollection $collection */
            $items[] = $this->serializeCollection($collection);
        }

        return $this->respond(
            ['items' => $items],
            ['pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]],
        );
    }

    /**
     * Create a private collection owned by the authenticated user.
     */
    public function add(): Response
    {
        $userId = $this->requireUserId();
        $data = $this->readBody();
        /** @var \App\Model\Table\ResearchCollectionsTable $collections */
        $collections = $this->fetchTable('ResearchCollections');
        /** @var \App\Model\Entity\ResearchCollection $collection */
        $collection = $collections->newEntity($data, ['fields' => ['name', 'description']]);
        $collection->set('user_id', $userId);
        $collection->set('created_by_user_id', $userId);
        if ($collection->hasErrors() || !$collections->save($collection)) {
            throw new ValidationException('Invalid research collection payload.');
        }

        $this->activity()->record(
            $userId,
            'research_collection.created',
            'research_collection',
            (int)$collection->id,
        );

        return $this->respond(['collection' => $this->serializeCollection($collection)], [], 201);
    }

    /**
     * Read a collection the caller owns or may read.
     */
    public function view(string $id): Response
    {
        $userId = $this->requireUserId();
        $collection = $this->fetchCollectionOrFail($id, $userId, CapabilityService::CAP_READ);

        return $this->respond(['collection' => $this->serializeCollection($collection)]);
    }

    /**
     * Update collection details with edit capability.
     */
    public function edit(string $id): Response
    {
        $userId = $this->requireUserId();
        $collection = $this->fetchCollectionOrFail($id, $userId, CapabilityService::CAP_EDIT);
        $data = $this->readBody();
        /** @var \App\Model\Table\ResearchCollectionsTable $collections */
        $collections = $this->fetchTable('ResearchCollections');
        $collection = $collections->patchEntity($collection, $data, ['fields' => ['name', 'description']]);
        if ($collection->hasErrors() || !$collections->save($collection)) {
            throw new ValidationException('Invalid research collection payload.');
        }

        $this->activity()->record(
            $userId,
            'research_collection.updated',
            'research_collection',
            (int)$collection->id,
            ['fields' => array_values(array_intersect(array_keys($data), ['name', 'description']))],
        );

        return $this->respond(['collection' => $this->serializeCollection($collection)]);
    }

    /**
     * Delete a collection with delete capability.
     */
    public function delete(string $id): Response
    {
        $userId = $this->requireUserId();
        $collection = $this->fetchCollectionOrFail($id, $userId, CapabilityService::CAP_DELETE);
        $collectionId = (int)$collection->id;

        /** @var \App\Model\Table\CapabilityGrantsTable $grants */
        $grants = $this->fetchTable('CapabilityGrants');
        $collections = $this->fetchTable('ResearchCollections');
        $collections->getConnection()->transactional(
            function () use ($grants, $collections, $collection, $collectionId): void {
                $grants->deleteAll([
                    'resource_type' => CapabilityService::RESOURCE_RESEARCH_COLLECTION,
                    'resource_id' => $collectionId,
                ]);
                if (!$collections->delete($collection)) {
                    throw new InternalErrorException('Unable to delete research collection.');
                }
            },
        );

        $this->activity()->record($userId, 'research_collection.deleted', 'research_collection', $collectionId);

        return $this->respond(['message' => 'Research collection deleted.']);
    }

    /**
     * List items in a collection the caller may read.
     */
    public function items(string $id): Response
    {
        $userId = $this->requireUserId();
        $collection = $this->fetchCollectionOrFail($id, $userId, CapabilityService::CAP_READ);
        $page = $this->readPositiveQueryInt('page', 1);
        $limit = min($this->readPositiveQueryInt('limit', 20), 100);
        $itemsTable = $this->fetchTable('ResearchCollectionItems');
        $query = $itemsTable->find()->where(['research_collection_id' => (int)$collection->id]);
        $total = (clone $query)->count();

        $items = [];
        foreach (
            $query->orderBy(['ResearchCollectionItems.id' => 'ASC'])
            ->limit($limit)
            ->offset(($page - 1) * $limit) as $item
        ) {
            /** @var \App\Model\Entity\ResearchCollectionItem $item */
            $items[] = $this->serializeItem($item, $userId);
        }

        return $this->respond(
            ['items' => $items],
            ['pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]],
        );
    }

    /**
     * Add a plain-text source item to a collection the caller may edit.
     */
    public function addItem(string $id): Response
    {
        $userId = $this->requireUserId();
        $collection = $this->fetchCollectionOrFail($id, $userId, CapabilityService::CAP_EDIT);
        $data = $this->readBody();
        $this->validateContentSize($data);
        $this->validateReferencePayload($data, $userId);

        /** @var \App\Model\Table\ResearchCollectionItemsTable $items */
        $items = $this->fetchTable('ResearchCollectionItems');
        /** @var \App\Model\Entity\ResearchCollectionItem $item */
        $item = $items->newEntity(
            $data,
            ['fields' => ['title', 'content', 'source_uri', 'related_resource_type', 'related_resource_id']],
        );
        $item->set('research_collection_id', (int)$collection->id);
        if ($item->hasErrors() || !$items->save($item)) {
            throw new ValidationException('Invalid research collection item payload.');
        }

        $this->activity()->record(
            $userId,
            'research_collection_item.created',
            'research_collection_item',
            (int)$item->id,
            [],
            'research_collection',
            (int)$collection->id,
        );

        return $this->respond(['item' => $this->serializeItem($item, $userId)], [], 201);
    }

    /**
     * Read one item in a collection the caller may read.
     */
    public function viewItem(string $id, string $itemId): Response
    {
        $userId = $this->requireUserId();
        $collection = $this->fetchCollectionOrFail($id, $userId, CapabilityService::CAP_READ);
        $item = $this->fetchItemOrFail($itemId, (int)$collection->id);

        return $this->respond(['item' => $this->serializeItem($item, $userId)]);
    }

    /**
     * Update an item in a collection the caller may edit.
     */
    public function editItem(string $id, string $itemId): Response
    {
        $userId = $this->requireUserId();
        $collection = $this->fetchCollectionOrFail($id, $userId, CapabilityService::CAP_EDIT);
        $item = $this->fetchItemOrFail($itemId, (int)$collection->id);
        $data = $this->readBody();
        $this->validateContentSize($data);
        $this->validateReferencePayload($data, $userId);

        /** @var \App\Model\Table\ResearchCollectionItemsTable $items */
        $items = $this->fetchTable('ResearchCollectionItems');
        $allowed = ['title', 'content', 'source_uri', 'related_resource_type', 'related_resource_id'];
        $item = $items->patchEntity($item, $data, ['fields' => $allowed]);
        if ($item->hasErrors() || !$items->save($item)) {
            throw new ValidationException('Invalid research collection item payload.');
        }

        $this->activity()->record(
            $userId,
            'research_collection_item.updated',
            'research_collection_item',
            (int)$item->id,
            ['fields' => array_values(array_intersect(array_keys($data), $allowed))],
            'research_collection',
            (int)$collection->id,
        );

        return $this->respond(['item' => $this->serializeItem($item, $userId)]);
    }

    /**
     * Delete an item in a collection the caller may delete.
     */
    public function deleteItem(string $id, string $itemId): Response
    {
        $userId = $this->requireUserId();
        $collection = $this->fetchCollectionOrFail($id, $userId, CapabilityService::CAP_DELETE);
        $item = $this->fetchItemOrFail($itemId, (int)$collection->id);
        if (!$this->fetchTable('ResearchCollectionItems')->delete($item)) {
            throw new InternalErrorException('Unable to delete research collection item.');
        }

        $this->activity()->record(
            $userId,
            'research_collection_item.deleted',
            'research_collection_item',
            (int)$item->id,
            [],
            'research_collection',
            (int)$collection->id,
        );

        return $this->respond(['message' => 'Research collection item deleted.']);
    }

    /**
     * Resolve a collection and enforce its required capability.
     */
    private function fetchCollectionOrFail(string $id, int $userId, string $capability): ResearchCollection
    {
        if (!ctype_digit($id) || (int)$id < 1) {
            throw new NotFoundException('Research collection not found.');
        }
        /** @var \App\Model\Entity\ResearchCollection|null $collection */
        $collection = $this->fetchTable('ResearchCollections')->find()
            ->where(['id' => (int)$id])
            ->first();
        if (
            $collection === null || !$this->capabilities()->allows(
                $userId,
                $capability,
                CapabilityService::RESOURCE_RESEARCH_COLLECTION,
                (int)$id,
            )
        ) {
            throw new NotFoundException('Research collection not found.');
        }

        return $collection;
    }

    /**
     * Resolve an item scoped to its parent collection.
     */
    private function fetchItemOrFail(string $itemId, int $collectionId): ResearchCollectionItem
    {
        if (!ctype_digit($itemId) || (int)$itemId < 1) {
            throw new NotFoundException('Research collection item not found.');
        }
        /** @var \App\Model\Entity\ResearchCollectionItem|null $item */
        $item = $this->fetchTable('ResearchCollectionItems')->find()
            ->where([
                'id' => (int)$itemId,
                'research_collection_id' => $collectionId,
            ])
            ->first();
        if ($item === null) {
            throw new NotFoundException('Research collection item not found.');
        }

        return $item;
    }

    /**
     * Validate paired reference fields and require authority over the referenced resource.
     *
     * @param array<string, mixed> $data
     */
    private function validateReferencePayload(array $data, int $userId): void
    {
        $hasType = array_key_exists('related_resource_type', $data);
        $hasId = array_key_exists('related_resource_id', $data);
        if (!$hasType && !$hasId) {
            return;
        }
        if ($hasType !== $hasId) {
            throw new BadRequestException('Both related_resource_type and related_resource_id are required.');
        }
        $type = $data['related_resource_type'];
        $id = $data['related_resource_id'];
        if ($type === null && $id === null) {
            return;
        }
        if (!is_string($type) || !in_array($type, ['todo', 'project', 'notebook'], true)) {
            throw new BadRequestException('Invalid related_resource_type.');
        }
        $resourceId = $this->parsePositiveInt($id, 'related_resource_id');
        if (!$this->mayReference($userId, $type, $resourceId)) {
            throw new NotFoundException('Related resource not found.');
        }
    }

    /**
     * Check whether the caller may reference a ToDo, Project, or Notebook.
     */
    private function mayReference(int $userId, string $type, int $resourceId): bool
    {
        if ($type === 'todo') {
            return $this->fetchTable('Todos')->exists(['id' => $resourceId, 'user_id' => $userId]);
        }
        $resourceType = $type === 'project'
            ? CapabilityService::RESOURCE_PROJECT
            : CapabilityService::RESOURCE_NOTEBOOK;

        return $this->capabilities()->allows(
            $userId,
            CapabilityService::CAP_REFERENCE,
            $resourceType,
            $resourceId,
        );
    }

    /**
     * Enforce the UTF-8 byte limit for source content.
     *
     * @param array<string, mixed> $data
     */
    private function validateContentSize(array $data): void
    {
        if (!array_key_exists('content', $data) || $data['content'] === null) {
            return;
        }
        if (!is_string($data['content']) || strlen($data['content']) > self::MAX_CONTENT_BYTES) {
            throw new BadRequestException('Research collection item content exceeds the 65536-byte limit.');
        }
    }

    /**
     * Parse a required positive integer payload value.
     */
    private function parsePositiveInt(mixed $value, string $field): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && (int)$value > 0) {
            return (int)$value;
        }

        throw new BadRequestException(sprintf('Invalid field `%s`.', $field));
    }

    /**
     * Parse a positive pagination query parameter.
     */
    private function readPositiveQueryInt(string $field, int $default): int
    {
        $max = intdiv(PHP_INT_MAX, 100);
        $value = $this->request->getQuery($field, $default);
        if (is_int($value) && $value > 0 && $value <= $max) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value) && strlen($value) <= strlen((string)$max)) {
            $parsed = (int)$value;
            if ($parsed > 0 && $parsed <= $max) {
                return $parsed;
            }
        }

        throw new BadRequestException(sprintf('Invalid query parameter `%s`.', $field));
    }

    /**
     * Read a JSON object request body.
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
     * Serialize safe collection fields for the API.
     *
     * @return array<string, mixed>
     */
    private function serializeCollection(ResearchCollection $collection): array
    {
        return [
            'id' => (int)$collection->id,
            'user_id' => (int)$collection->user_id,
            'created_by_user_id' => (int)$collection->created_by_user_id,
            'name' => (string)$collection->name,
            'description' => $collection->description === null ? null : (string)$collection->description,
            'created' => $collection->created?->format(DATE_ATOM),
            'modified' => $collection->modified?->format(DATE_ATOM),
        ];
    }

    /**
     * Serialize item data while hiding references the caller cannot access.
     *
     * @return array<string, mixed>
     */
    private function serializeItem(ResearchCollectionItem $item, int $userId): array
    {
        $relatedType = $item->related_resource_type === null ? null : (string)$item->related_resource_type;
        $relatedId = $item->related_resource_id === null ? null : (int)$item->related_resource_id;
        if ($relatedType !== null && $relatedId !== null && !$this->mayReference($userId, $relatedType, $relatedId)) {
            $relatedType = null;
            $relatedId = null;
        }

        return [
            'id' => (int)$item->id,
            'research_collection_id' => (int)$item->research_collection_id,
            'title' => (string)$item->title,
            'content' => $item->content === null ? null : (string)$item->content,
            'source_uri' => $item->source_uri === null ? null : (string)$item->source_uri,
            'related_resource_type' => $relatedType,
            'related_resource_id' => $relatedId,
            'created' => $item->created?->format(DATE_ATOM),
            'modified' => $item->modified?->format(DATE_ATOM),
        ];
    }
}
