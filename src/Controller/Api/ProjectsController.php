<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Http\Exception\ValidationException;
use App\Model\Entity\Project;
use App\Model\Entity\ProjectSection;
use App\Service\CapabilityService;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ConflictException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use InvalidArgumentException;

class ProjectsController extends AppController
{
    /**
     * List owned projects with deterministic pagination.
     */
    public function index(): Response
    {
        $userId = $this->requireUserId();
        $page = $this->readPositiveQueryInt('page', 1);
        $limit = min($this->readPositiveQueryInt('limit', 20), 100);

        $projects = $this->fetchTable('Projects');
        $query = $projects->find()->where(['Projects.user_id' => $userId]);
        $total = (clone $query)->count();

        $items = [];
        foreach ($query->orderBy(['Projects.id' => 'DESC'])->limit($limit)->offset(($page - 1) * $limit) as $project) {
            /** @var \App\Model\Entity\Project $project */
            $items[] = $this->serializeProject($project);
        }

        return $this->respond(
            ['items' => $items],
            ['pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]],
        );
    }

    /**
     * Create a project for the current user.
     */
    public function add(): Response
    {
        $userId = $this->requireUserId();
        $data = $this->readBody();

        /** @var \App\Model\Table\ProjectsTable $projects */
        $projects = $this->fetchTable('Projects');
        /** @var \App\Model\Entity\Project $project */
        $project = $projects->newEntity($data, ['fields' => ['name', 'description']]);
        $project->user_id = $userId;
        if ($project->hasErrors()) {
            throw new ValidationException('Invalid project payload.');
        }
        if (!$projects->save($project)) {
            throw new ValidationException('Invalid project payload.');
        }

        return $this->respond(['project' => $this->serializeProject($project)], [], 201);
    }

    /**
     * View a project with its ordered sections and their ToDos.
     */
    public function view(string $id): Response
    {
        $userId = $this->requireUserId();
        $project = $this->fetchReadableProjectOrFail($id, $userId);

        return $this->respond(['project' => $this->serializeProject($project, true)]);
    }

    /**
     * Update an owned project.
     */
    public function edit(string $id): Response
    {
        $userId = $this->requireUserId();
        $project = $this->fetchOwnedProjectOrFail($id, $userId);
        $data = $this->readBody();

        /** @var \App\Model\Table\ProjectsTable $projects */
        $projects = $this->fetchTable('Projects');
        $project = $projects->patchEntity($project, $data, ['fields' => ['name', 'description']]);
        if ($project->hasErrors() || !$projects->save($project)) {
            throw new ValidationException('Invalid project payload.');
        }

        return $this->respond(['project' => $this->serializeProject($project)]);
    }

    /**
     * Delete an owned project that has no sections.
     */
    public function delete(string $id): Response
    {
        $userId = $this->requireUserId();
        $project = $this->fetchOwnedProjectOrFail($id, $userId);

        $sections = $this->fetchTable('ProjectSections');
        if ($sections->exists(['project_id' => (int)$project->id])) {
            throw new ConflictException('Project still contains sections.');
        }

        $projects = $this->fetchTable('Projects');
        if (!$projects->delete($project)) {
            throw new InternalErrorException('Unable to delete project.');
        }

        return $this->respond(['message' => 'Project deleted.']);
    }

    /**
     * List the ordered sections of an owned project.
     */
    public function sections(string $id): Response
    {
        $userId = $this->requireUserId();
        $project = $this->fetchOwnedProjectOrFail($id, $userId, true);

        $items = [];
        foreach ($project->project_sections as $section) {
            $items[] = $this->serializeSection($section);
        }

        return $this->respond(['items' => $items]);
    }

    /**
     * Append a section to an owned project.
     */
    public function addSection(string $id): Response
    {
        $userId = $this->requireUserId();
        $project = $this->fetchOwnedProjectOrFail($id, $userId);
        $data = $this->readBody();

        /** @var \App\Model\Table\ProjectSectionsTable $sections */
        $sections = $this->fetchTable('ProjectSections');
        /** @var \App\Model\Entity\ProjectSection $section */
        $section = $sections->newEntity($data, ['fields' => ['name']]);
        $section->set('project_id', (int)$project->id);
        $section->set('position', $sections->nextPosition((int)$project->id));
        if ($section->hasErrors() || !$sections->save($section)) {
            throw new ValidationException('Invalid section payload.');
        }

        return $this->respond(['section' => $this->serializeSection($section)], [], 201);
    }

    /**
     * Rename and/or reposition a section of an owned project.
     */
    public function editSection(string $id, string $sectionId): Response
    {
        $userId = $this->requireUserId();
        $project = $this->fetchOwnedProjectOrFail($id, $userId);
        $section = $this->fetchProjectSectionOrFail($sectionId, (int)$project->id);
        $data = $this->readBody();

        /** @var \App\Model\Table\ProjectSectionsTable $sections */
        $sections = $this->fetchTable('ProjectSections');
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
            $section = $this->fetchProjectSectionOrFail($sectionId, (int)$project->id);
        }

        return $this->respond(['section' => $this->serializeSection($section)]);
    }

    /**
     * Delete an empty section of an owned project.
     */
    public function deleteSection(string $id, string $sectionId): Response
    {
        $userId = $this->requireUserId();
        $project = $this->fetchOwnedProjectOrFail($id, $userId);
        $section = $this->fetchProjectSectionOrFail($sectionId, (int)$project->id);

        $todos = $this->fetchTable('Todos');
        if ($todos->exists(['project_section_id' => (int)$section->id])) {
            throw new ConflictException('Section still contains ToDos.');
        }

        /** @var \App\Model\Table\ProjectSectionsTable $sections */
        $sections = $this->fetchTable('ProjectSections');
        if (!$sections->delete($section)) {
            throw new InternalErrorException('Unable to delete section.');
        }
        $sections->renumberPositions((int)$project->id);

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
     * Find an owned project by id or fail with 404.
     */
    private function fetchOwnedProjectOrFail(string $id, int $userId, bool $withSections = false): Project
    {
        if (!ctype_digit($id) || (int)$id < 1) {
            throw new NotFoundException('Project not found.');
        }

        /** @var \App\Model\Table\ProjectsTable $projects */
        $projects = $this->fetchTable('Projects');
        $query = $projects->find()->where(['Projects.id' => (int)$id, 'Projects.user_id' => $userId]);
        if ($withSections) {
            $query->contain(['ProjectSections' => ['Todos']]);
        }
        /** @var \App\Model\Entity\Project|null $project */
        $project = $query->first();
        if ($project === null) {
            throw new NotFoundException('Project not found.');
        }

        return $project;
    }

    /**
     * Find a project the caller owns or explicitly holds `read` on, or fail with 404.
     *
     * Read access for a non-owner requires an explicit capability grant; nothing is inferred from
     * Library membership or from any other capability.
     */
    private function fetchReadableProjectOrFail(string $id, int $userId): Project
    {
        if (!ctype_digit($id) || (int)$id < 1) {
            throw new NotFoundException('Project not found.');
        }

        /** @var \App\Model\Entity\Project|null $project */
        $project = $this->fetchTable('Projects')->find()
            ->where(['Projects.id' => (int)$id])
            ->contain(['ProjectSections' => ['Todos']])
            ->first();
        if ($project === null) {
            throw new NotFoundException('Project not found.');
        }
        if ((int)$project->user_id === $userId) {
            return $project;
        }
        $allowed = $this->capabilities()->allows(
            $userId,
            CapabilityService::CAP_READ,
            CapabilityService::RESOURCE_PROJECT,
            (int)$project->id,
        );
        if (!$allowed) {
            throw new NotFoundException('Project not found.');
        }

        return $project;
    }

    /**
     * Find a section belonging to the given project or fail with 404.
     */
    private function fetchProjectSectionOrFail(string $sectionId, int $projectId): ProjectSection
    {
        if (!ctype_digit($sectionId) || (int)$sectionId < 1) {
            throw new NotFoundException('Section not found.');
        }

        /** @var \App\Model\Table\ProjectSectionsTable $sections */
        $sections = $this->fetchTable('ProjectSections');
        /** @var \App\Model\Entity\ProjectSection|null $section */
        $section = $sections->find()
            ->where(['ProjectSections.id' => (int)$sectionId, 'ProjectSections.project_id' => $projectId])
            ->first();
        if ($section === null) {
            throw new NotFoundException('Section not found.');
        }

        return $section;
    }

    /**
     * Serialize project fields for API responses.
     *
     * @return array<string, mixed>
     */
    private function serializeProject(Project $project, bool $withSections = false): array
    {
        $payload = [
            'id' => (int)$project->id,
            'user_id' => (int)$project->user_id,
            'name' => (string)$project->name,
            'description' => $project->description === null ? null : (string)$project->description,
            'created' => $project->created?->format(DATE_ATOM),
            'modified' => $project->modified?->format(DATE_ATOM),
        ];

        if ($withSections) {
            $sections = [];
            foreach ($project->project_sections as $section) {
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
    private function serializeSection(ProjectSection $section, bool $withTodos = false): array
    {
        $payload = [
            'id' => (int)$section->id,
            'project_id' => (int)$section->project_id,
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
