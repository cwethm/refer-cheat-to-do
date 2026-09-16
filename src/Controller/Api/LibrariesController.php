<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Http\Exception\ValidationException;
use App\Model\Entity\Library;
use App\Service\LibraryMembershipService;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ConflictException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use DomainException;

class LibrariesController extends AppController
{
    /**
     * List owned libraries with deterministic pagination.
     */
    public function index(): Response
    {
        $userId = $this->requireUserId();
        $page = $this->readPositiveQueryInt('page', 1);
        $limit = min($this->readPositiveQueryInt('limit', 20), 100);

        $libraries = $this->fetchTable('Libraries');
        $query = $libraries->find()->where(['Libraries.user_id' => $userId]);
        $total = (clone $query)->count();

        $items = [];
        $pageQuery = $query->orderBy(['Libraries.id' => 'DESC'])
            ->limit($limit)
            ->offset(($page - 1) * $limit);
        foreach ($pageQuery as $library) {
            /** @var \App\Model\Entity\Library $library */
            $items[] = $this->serializeLibrary($library);
        }

        return $this->respond(
            ['items' => $items],
            ['pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]],
        );
    }

    /**
     * Create a library for the current user.
     */
    public function add(): Response
    {
        $userId = $this->requireUserId();
        $data = $this->readBody();

        /** @var \App\Model\Table\LibrariesTable $libraries */
        $libraries = $this->fetchTable('Libraries');
        /** @var \App\Model\Entity\Library $library */
        $library = $libraries->newEntity($data, ['fields' => ['name', 'description']]);
        $library->set('user_id', $userId);
        if ($library->hasErrors() || !$libraries->save($library)) {
            throw new ValidationException('Invalid library payload.');
        }

        return $this->respond(['library' => $this->serializeLibrary($library)], [], 201);
    }

    /**
     * View an owned library with its members.
     */
    public function view(string $id): Response
    {
        $userId = $this->requireUserId();
        $library = $this->fetchOwnedLibraryOrFail($id, $userId);

        return $this->respond(['library' => $this->serializeLibrary($library, true)]);
    }

    /**
     * Update an owned library.
     */
    public function edit(string $id): Response
    {
        $userId = $this->requireUserId();
        $library = $this->fetchOwnedLibraryOrFail($id, $userId);
        $data = $this->readBody();

        /** @var \App\Model\Table\LibrariesTable $libraries */
        $libraries = $this->fetchTable('Libraries');
        $library = $libraries->patchEntity($library, $data, ['fields' => ['name', 'description']]);
        if ($library->hasErrors() || !$libraries->save($library)) {
            throw new ValidationException('Invalid library payload.');
        }

        return $this->respond(['library' => $this->serializeLibrary($library)]);
    }

    /**
     * Delete an owned library that has no members.
     */
    public function delete(string $id): Response
    {
        $userId = $this->requireUserId();
        $library = $this->fetchOwnedLibraryOrFail($id, $userId);

        if ($this->membership()->hasMembers($library)) {
            throw new ConflictException('Library still contains members.');
        }

        $libraries = $this->fetchTable('Libraries');
        if (!$libraries->delete($library)) {
            throw new InternalErrorException('Unable to delete library.');
        }

        return $this->respond(['message' => 'Library deleted.']);
    }

    /**
     * List the members of an owned library.
     */
    public function members(string $id): Response
    {
        $userId = $this->requireUserId();
        $library = $this->fetchOwnedLibraryOrFail($id, $userId);

        return $this->respond($this->serializeMembers($library, $userId));
    }

    /**
     * Add a Project or Notebook to an owned library.
     */
    public function addMember(string $id, string $memberType, string $memberId): Response
    {
        $userId = $this->requireUserId();
        $library = $this->fetchOwnedLibraryOrFail($id, $userId);
        $membership = $this->membership();
        $this->assertMemberType($memberType);
        if (!ctype_digit($memberId) || (int)$memberId < 1) {
            throw new NotFoundException('Library member not found.');
        }

        try {
            $membership->addMember($library, $memberType, (int)$memberId);
        } catch (DomainException $exception) {
            if (str_contains($exception->getMessage(), 'already')) {
                throw new ConflictException($exception->getMessage(), null, $exception);
            }

            throw new NotFoundException('Library member not found.', null, $exception);
        }

        return $this->respond([
            'library_id' => (int)$library->id,
            'member_type' => $memberType,
            'member_id' => (int)$memberId,
        ], [], 201);
    }

    /**
     * Remove a Project or Notebook from an owned library without deleting it.
     */
    public function removeMember(string $id, string $memberType, string $memberId): Response
    {
        $userId = $this->requireUserId();
        $library = $this->fetchOwnedLibraryOrFail($id, $userId);
        $this->assertMemberType($memberType);
        if (!ctype_digit($memberId) || (int)$memberId < 1) {
            throw new NotFoundException('Library member not found.');
        }

        try {
            $this->membership()->removeMember($library, $memberType, (int)$memberId);
        } catch (DomainException $exception) {
            throw new NotFoundException('Library member not found.', null, $exception);
        }

        return $this->respond([
            'library_id' => (int)$library->id,
            'member_type' => $memberType,
            'member_id' => (int)$memberId,
            'message' => 'Library member removed.',
        ]);
    }

    /**
     * Resolve the library membership authority.
     */
    private function membership(): LibraryMembershipService
    {
        return new LibraryMembershipService($this->getTableLocator());
    }

    /**
     * Reject unknown member kinds.
     */
    private function assertMemberType(string $memberType): void
    {
        if (!$this->membership()->isSupportedType($memberType)) {
            throw new BadRequestException('Invalid library member type.');
        }
    }

    /**
     * Read and validate a JSON request body.
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
     * Find an owned library by id or fail with 404.
     */
    private function fetchOwnedLibraryOrFail(string $id, int $userId): Library
    {
        if (!ctype_digit($id) || (int)$id < 1) {
            throw new NotFoundException('Library not found.');
        }

        /** @var \App\Model\Table\LibrariesTable $libraries */
        $libraries = $this->fetchTable('Libraries');
        /** @var \App\Model\Entity\Library|null $library */
        $library = $libraries->find()
            ->where(['Libraries.id' => (int)$id, 'Libraries.user_id' => $userId])
            ->first();
        if ($library === null) {
            throw new NotFoundException('Library not found.');
        }

        return $library;
    }

    /**
     * Serialize library fields for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeLibrary(Library $library, bool $withMembers = false): array
    {
        $payload = [
            'id' => (int)$library->id,
            'user_id' => (int)$library->user_id,
            'name' => (string)$library->name,
            'description' => $library->description === null ? null : (string)$library->description,
            'created' => $library->created?->format(DATE_ATOM),
            'modified' => $library->modified?->format(DATE_ATOM),
        ];
        if ($withMembers) {
            $payload += $this->serializeMembers($library, (int)$library->user_id);
        }

        return $payload;
    }

    /**
     * Serialize the owned members of a library.
     *
     * @return array<string, mixed>
     */
    private function serializeMembers(Library $library, int $userId): array
    {
        $membership = $this->membership();

        return [
            'projects' => $this->serializeOwnedRecords(
                'Projects',
                $membership->memberIds($library, LibraryMembershipService::MEMBER_PROJECT),
                $userId,
            ),
            'notebooks' => $this->serializeOwnedRecords(
                'Notebooks',
                $membership->memberIds($library, LibraryMembershipService::MEMBER_NOTEBOOK),
                $userId,
            ),
        ];
    }

    /**
     * Serialize owned records referenced by id, skipping anything not owned by the caller.
     *
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    private function serializeOwnedRecords(string $table, array $ids, int $userId): array
    {
        if ($ids === []) {
            return [];
        }

        $items = [];
        $rows = $this->fetchTable($table)->find()
            ->where(['id IN' => $ids, 'user_id' => $userId])
            ->orderBy(['id' => 'ASC'])
            ->all();
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int)$row->get('id'),
                'name' => (string)$row->get('name'),
                'description' => $row->get('description') === null ? null : (string)$row->get('description'),
            ];
        }

        return $items;
    }
}
