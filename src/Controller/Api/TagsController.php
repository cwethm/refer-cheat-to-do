<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Http\Exception\ValidationException;
use App\Model\Entity\Tag;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;

class TagsController extends AppController
{
    /**
     * List owned tags with deterministic pagination.
     */
    public function index(): Response
    {
        $userId = $this->requireUserId();
        $page = $this->readPositiveQueryInt('page', 1);
        $limit = min($this->readPositiveQueryInt('limit', 20), 100);

        /** @var \App\Model\Table\TagsTable $tagsTable */
        $tagsTable = $this->fetchTable('Tags');
        $query = $tagsTable->find()->where(['user_id' => $userId]);

        $total = (clone $query)->count();
        $offset = ($page - 1) * $limit;
        $tags = $query
            ->orderBy(['name' => 'ASC', 'id' => 'ASC'])
            ->limit($limit)
            ->offset($offset)
            ->all();

        $items = [];
        foreach ($tags as $tag) {
            /** @var \App\Model\Entity\Tag $tag */
            $items[] = $this->serializeTag($tag);
        }

        return $this->respond(
            ['items' => $items],
            ['pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]],
        );
    }

    /**
     * Create a tag for the current user.
     */
    public function add(): Response
    {
        $userId = $this->requireUserId();
        $data = $this->request->getData();
        if (!is_array($data)) {
            throw new BadRequestException('Malformed request body.');
        }

        /** @var \App\Model\Table\TagsTable $tagsTable */
        $tagsTable = $this->fetchTable('Tags');
        /** @var \App\Model\Entity\Tag $tag */
        $tag = $tagsTable->newEntity($data, ['fields' => ['name']]);
        $tag->user_id = $userId;
        if ($tag->hasErrors()) {
            throw new ValidationException('Invalid tag payload.');
        }
        if (!$tagsTable->save($tag)) {
            if ($tag->getErrors() !== []) {
                throw new ValidationException('Invalid tag payload.');
            }
            throw new InternalErrorException('Unable to save tag.');
        }

        return $this->respond(['tag' => $this->serializeTag($tag)], [], 201);
    }

    /**
     * Update a user-owned tag.
     */
    public function edit(string $id): Response
    {
        $userId = $this->requireUserId();
        $tag = $this->fetchOwnedTagOrFail($id, $userId);
        $data = $this->request->getData();
        if (!is_array($data)) {
            throw new BadRequestException('Malformed request body.');
        }

        /** @var \App\Model\Table\TagsTable $tagsTable */
        $tagsTable = $this->fetchTable('Tags');
        $tag = $tagsTable->patchEntity($tag, $data, ['fields' => ['name']]);
        if ($tag->hasErrors()) {
            throw new ValidationException('Invalid tag payload.');
        }
        if (!$tagsTable->save($tag)) {
            if ($tag->getErrors() !== []) {
                throw new ValidationException('Invalid tag payload.');
            }
            throw new InternalErrorException('Unable to update tag.');
        }

        return $this->respond(['tag' => $this->serializeTag($tag)]);
    }

    /**
     * Delete a user-owned tag.
     */
    public function delete(string $id): Response
    {
        $userId = $this->requireUserId();
        $tag = $this->fetchOwnedTagOrFail($id, $userId);

        /** @var \App\Model\Table\TagsTable $tagsTable */
        $tagsTable = $this->fetchTable('Tags');
        if (!$tagsTable->delete($tag)) {
            throw new InternalErrorException('Unable to delete tag.');
        }

        return $this->respond(['message' => 'Tag deleted.']);
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
     * Serialize tag entity fields for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeTag(Tag $tag): array
    {
        return [
            'id' => (int)$tag->id,
            'user_id' => (int)$tag->user_id,
            'name' => (string)$tag->name,
            'created' => $tag->created?->format(DATE_ATOM),
            'modified' => $tag->modified?->format(DATE_ATOM),
        ];
    }
}
