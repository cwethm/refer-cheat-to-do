<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Http\Exception\ValidationException;
use App\Model\Entity\Notebook;
use App\Model\Entity\NotebookSection;
use App\Service\CapabilityService;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ConflictException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use InvalidArgumentException;

class NotebooksController extends AppController
{
    /**
     * List owned notebooks with deterministic pagination.
     */
    public function index(): Response
    {
        $userId = $this->requireUserId();
        $page = $this->readPositiveQueryInt('page', 1);
        $limit = min($this->readPositiveQueryInt('limit', 20), 100);

        $notebooks = $this->fetchTable('Notebooks');
        $query = $notebooks->find()->where(['Notebooks.user_id' => $userId]);
        $total = (clone $query)->count();

        $items = [];
        $pageQuery = $query->orderBy(['Notebooks.id' => 'DESC'])
            ->limit($limit)
            ->offset(($page - 1) * $limit);
        foreach ($pageQuery as $notebook) {
            /** @var \App\Model\Entity\Notebook $notebook */
            $items[] = $this->serializeNotebook($notebook);
        }

        return $this->respond(
            ['items' => $items],
            ['pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]],
        );
    }

    /**
     * Create a notebook for the current user.
     */
    public function add(): Response
    {
        $userId = $this->requireUserId();
        $data = $this->readBody();

        /** @var \App\Model\Table\NotebooksTable $notebooks */
        $notebooks = $this->fetchTable('Notebooks');
        /** @var \App\Model\Entity\Notebook $notebook */
        $notebook = $notebooks->newEntity($data, ['fields' => ['name', 'description']]);
        $notebook->user_id = $userId;
        if ($notebook->hasErrors()) {
            throw new ValidationException('Invalid notebook payload.');
        }
        if (!$notebooks->save($notebook)) {
            throw new ValidationException('Invalid notebook payload.');
        }

        $this->activity()->record($userId, 'notebook.created', 'notebook', (int)$notebook->id);

        return $this->respond(['notebook' => $this->serializeNotebook($notebook)], [], 201);
    }

    /**
     * View a notebook with its ordered sections and their ToDos.
     */
    public function view(string $id): Response
    {
        $userId = $this->requireUserId();
        $notebook = $this->fetchReadableNotebookOrFail($id, $userId);

        return $this->respond(['notebook' => $this->serializeNotebook($notebook, true)]);
    }

    /**
     * Update an owned notebook.
     */
    public function edit(string $id): Response
    {
        $userId = $this->requireUserId();
        $notebook = $this->fetchOwnedNotebookOrFail($id, $userId);
        $data = $this->readBody();

        /** @var \App\Model\Table\NotebooksTable $notebooks */
        $notebooks = $this->fetchTable('Notebooks');
        $notebook = $notebooks->patchEntity($notebook, $data, ['fields' => ['name', 'description']]);
        if ($notebook->hasErrors() || !$notebooks->save($notebook)) {
            throw new ValidationException('Invalid notebook payload.');
        }

        return $this->respond(['notebook' => $this->serializeNotebook($notebook)]);
    }

    /**
     * Delete an owned notebook that has no sections.
     */
    public function delete(string $id): Response
    {
        $userId = $this->requireUserId();
        $notebook = $this->fetchOwnedNotebookOrFail($id, $userId);

        $sections = $this->fetchTable('NotebookSections');
        if ($sections->exists(['notebook_id' => (int)$notebook->id])) {
            throw new ConflictException('Notebook still contains sections.');
        }

        $notebooks = $this->fetchTable('Notebooks');
        if (!$notebooks->delete($notebook)) {
            throw new InternalErrorException('Unable to delete notebook.');
        }

        $this->activity()->record($userId, 'notebook.deleted', 'notebook', (int)$id);

        return $this->respond(['message' => 'Notebook deleted.']);
    }

    /**
     * List the ordered sections of an owned notebook.
     */
    public function sections(string $id): Response
    {
        $userId = $this->requireUserId();
        $notebook = $this->fetchOwnedNotebookOrFail($id, $userId, true);

        $items = [];
        foreach ($notebook->notebook_sections as $section) {
            $items[] = $this->serializeSection($section);
        }

        return $this->respond(['items' => $items]);
    }

    /**
     * Append a section to an owned notebook.
     */
    public function addSection(string $id): Response
    {
        $userId = $this->requireUserId();
        $notebook = $this->fetchOwnedNotebookOrFail($id, $userId);
        $data = $this->readBody();

        /** @var \App\Model\Table\NotebookSectionsTable $sections */
        $sections = $this->fetchTable('NotebookSections');
        /** @var \App\Model\Entity\NotebookSection $section */
        $section = $sections->newEntity($data, ['fields' => ['name']]);
        $section->set('notebook_id', (int)$notebook->id);
        $section->set('position', $sections->nextPosition((int)$notebook->id));
        if ($section->hasErrors() || !$sections->save($section)) {
            throw new ValidationException('Invalid section payload.');
        }

        return $this->respond(['section' => $this->serializeSection($section)], [], 201);
    }

    /**
     * Rename and/or reposition a section of an owned notebook.
     */
    public function editSection(string $id, string $sectionId): Response
    {
        $userId = $this->requireUserId();
        $notebook = $this->fetchOwnedNotebookOrFail($id, $userId);
        $section = $this->fetchNotebookSectionOrFail($sectionId, (int)$notebook->id);
        $data = $this->readBody();

        /** @var \App\Model\Table\NotebookSectionsTable $sections */
        $sections = $this->fetchTable('NotebookSections');
        if (array_key_exists('name', $data)) {
            $section = $sections->patchEntity($section, $data, ['fields' => ['name']]);
            if ($section->hasErrors() || !$sections->save($section)) {
                throw new ValidationException('Invalid section payload.');
            }
        }

        if (array_key_exists('position', $data)) {
            $position = $data['position'];
            if (is_string($position) && ctype_digit($position)) {
                $position = (int)$position;
            }
            if (!is_int($position)) {
                throw new BadRequestException('Invalid section position.');
            }
            try {
                $sections->moveToPosition($section, $position);
            } catch (InvalidArgumentException $exception) {
                throw new BadRequestException($exception->getMessage(), null, $exception);
            }
            $section = $this->fetchNotebookSectionOrFail($sectionId, (int)$notebook->id);
        }

        return $this->respond(['section' => $this->serializeSection($section)]);
    }

    /**
     * Delete an empty section of an owned notebook.
     */
    public function deleteSection(string $id, string $sectionId): Response
    {
        $userId = $this->requireUserId();
        $notebook = $this->fetchOwnedNotebookOrFail($id, $userId);
        $section = $this->fetchNotebookSectionOrFail($sectionId, (int)$notebook->id);

        $todos = $this->fetchTable('Todos');
        if ($todos->exists(['notebook_section_id' => (int)$section->id])) {
            throw new ConflictException('Section still contains ToDos.');
        }

        /** @var \App\Model\Table\NotebookSectionsTable $sections */
        $sections = $this->fetchTable('NotebookSections');
        if (!$sections->delete($section)) {
            throw new InternalErrorException('Unable to delete section.');
        }
        $sections->renumberPositions((int)$notebook->id);

        return $this->respond(['message' => 'Section deleted.']);
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
     * Find an owned notebook by id or fail with 404.
     */
    private function fetchOwnedNotebookOrFail(string $id, int $userId, bool $withSections = false): Notebook
    {
        if (!ctype_digit($id) || (int)$id < 1) {
            throw new NotFoundException('Notebook not found.');
        }

        /** @var \App\Model\Table\NotebooksTable $notebooks */
        $notebooks = $this->fetchTable('Notebooks');
        $query = $notebooks->find()->where(['Notebooks.id' => (int)$id, 'Notebooks.user_id' => $userId]);
        if ($withSections) {
            $query->contain(['NotebookSections' => ['Todos']]);
        }
        /** @var \App\Model\Entity\Notebook|null $notebook */
        $notebook = $query->first();
        if ($notebook === null) {
            throw new NotFoundException('Notebook not found.');
        }

        return $notebook;
    }

    /**
     * Find a notebook the caller owns or explicitly holds `read` on, or fail with 404.
     *
     * Read access for a non-owner requires an explicit capability grant; nothing is inferred from
     * Library membership or from any other capability.
     */
    private function fetchReadableNotebookOrFail(string $id, int $userId): Notebook
    {
        if (!ctype_digit($id) || (int)$id < 1) {
            throw new NotFoundException('Notebook not found.');
        }

        /** @var \App\Model\Entity\Notebook|null $notebook */
        $notebook = $this->fetchTable('Notebooks')->find()
            ->where(['Notebooks.id' => (int)$id])
            ->contain(['NotebookSections' => ['Todos']])
            ->first();
        if ($notebook === null) {
            throw new NotFoundException('Notebook not found.');
        }
        if ((int)$notebook->user_id === $userId) {
            return $notebook;
        }
        $allowed = $this->capabilities()->allows(
            $userId,
            CapabilityService::CAP_READ,
            CapabilityService::RESOURCE_NOTEBOOK,
            (int)$notebook->id,
        );
        if (!$allowed) {
            throw new NotFoundException('Notebook not found.');
        }

        return $notebook;
    }

    /**
     * Find a section belonging to the given notebook or fail with 404.
     */
    private function fetchNotebookSectionOrFail(string $sectionId, int $notebookId): NotebookSection
    {
        if (!ctype_digit($sectionId) || (int)$sectionId < 1) {
            throw new NotFoundException('Section not found.');
        }

        /** @var \App\Model\Table\NotebookSectionsTable $sections */
        $sections = $this->fetchTable('NotebookSections');
        /** @var \App\Model\Entity\NotebookSection|null $section */
        $section = $sections->find()
            ->where(['NotebookSections.id' => (int)$sectionId, 'NotebookSections.notebook_id' => $notebookId])
            ->first();
        if ($section === null) {
            throw new NotFoundException('Section not found.');
        }

        return $section;
    }

    /**
     * Serialize notebook fields for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeNotebook(Notebook $notebook, bool $withSections = false): array
    {
        $payload = [
            'id' => (int)$notebook->id,
            'user_id' => (int)$notebook->user_id,
            'name' => (string)$notebook->name,
            'description' => $notebook->description === null ? null : (string)$notebook->description,
            'created' => $notebook->created?->format(DATE_ATOM),
            'modified' => $notebook->modified?->format(DATE_ATOM),
        ];

        if ($withSections) {
            $sections = [];
            foreach ($notebook->notebook_sections as $section) {
                $sections[] = $this->serializeSection($section, true);
            }
            $payload['sections'] = $sections;
        }

        return $payload;
    }

    /**
     * Serialize section fields for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeSection(NotebookSection $section, bool $withTodos = false): array
    {
        $payload = [
            'id' => (int)$section->id,
            'notebook_id' => (int)$section->notebook_id,
            'name' => (string)$section->name,
            'position' => (int)$section->position,
            'created' => $section->created?->format(DATE_ATOM),
            'modified' => $section->modified?->format(DATE_ATOM),
        ];

        if ($withTodos) {
            $todos = [];
            foreach ($section->todos ?? [] as $todo) {
                $todos[] = [
                    'id' => (int)$todo->id,
                    'title' => (string)$todo->title,
                    'status' => (string)$todo->status,
                ];
            }
            $payload['todos'] = $todos;
        }

        return $payload;
    }
}
