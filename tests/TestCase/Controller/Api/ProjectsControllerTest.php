<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class ProjectsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.ActivityRecords',
        'app.Projects',
        'app.ProjectSections',
        'app.Todos',
        'app.Tags',
        'app.TodosTags',
    ];

    private function authenticate(int $userId = 1): void
    {
        $this->session(['Auth.user_id' => $userId]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    public function testListReturnsOnlyOwnedProjects(): void
    {
        $this->authenticate();

        $this->get('/api/projects');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner project"');
        $this->assertResponseNotContains('"Other user project"');
    }

    public function testCreateProjectAssignsOwnership(): void
    {
        $this->authenticate();

        $this->post('/api/projects', [
            'name' => 'Launch plan',
            'description' => 'ship it',
            'user_id' => 2,
        ]);

        $this->assertResponseCode(201);
        $this->assertResponseContains('"name": "Launch plan"');

        $projects = TableRegistry::getTableLocator()->get('Projects');
        $project = $projects->find()->where(['name' => 'Launch plan'])->firstOrFail();
        $this->assertSame(1, (int)$project->user_id);
    }

    public function testCreateRejectsBlankName(): void
    {
        $this->authenticate();

        $this->post('/api/projects', ['name' => '']);

        $this->assertResponseCode(422);
        $this->assertResponseContains('"code": "VALIDATION_ERROR"');
    }

    public function testViewReturnsOrderedSectionsWithTodos(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->get(10);
        $todo->set('project_section_id', 301);
        $todos->saveOrFail($todo);

        $this->authenticate();

        $this->get('/api/projects/200');

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertLessThan((int)strpos($body, '"Doing"'), (int)strpos($body, '"Planning"'));
        $this->assertLessThan((int)strpos($body, '"Review"'), (int)strpos($body, '"Doing"'));
        $this->assertResponseContains('"Owner inbox todo"');
    }

    public function testViewRejectsCrossUserProject(): void
    {
        $this->authenticate();

        $this->get('/api/projects/201');

        $this->assertResponseCode(404);
    }

    public function testViewRejectsMalformedId(): void
    {
        $this->authenticate();

        $this->get('/api/projects/abc');

        $this->assertResponseCode(404);
    }

    public function testEditCannotReassignOwnership(): void
    {
        $this->authenticate();

        $this->patch('/api/projects/200', ['name' => 'Renamed project', 'user_id' => 2]);

        $this->assertResponseOk();

        $projects = TableRegistry::getTableLocator()->get('Projects');
        $project = $projects->get(200);
        $this->assertSame(1, (int)$project->user_id);
        $this->assertSame('Renamed project', $project->name);
    }

    public function testEditRejectsCrossUserProject(): void
    {
        $this->authenticate();

        $this->patch('/api/projects/201', ['name' => 'Hijacked']);

        $this->assertResponseCode(404);
    }

    public function testDeleteRejectsProjectWithSections(): void
    {
        $this->authenticate();

        $this->delete('/api/projects/200');

        $this->assertResponseCode(409);
        $this->assertResponseContains('"code": "CONFLICT"');
    }

    public function testDeleteRemovesEmptyProject(): void
    {
        $projects = TableRegistry::getTableLocator()->get('Projects');
        $project = $projects->newEmptyEntity();
        $project->set('user_id', 1);
        $project->set('name', 'Empty project');
        $projects->saveOrFail($project);

        $this->authenticate();

        $this->delete('/api/projects/' . $project->id);

        $this->assertResponseOk();
        $this->assertFalse($projects->exists(['id' => $project->id]));
    }

    public function testCreateSectionAppendsAtEnd(): void
    {
        $this->authenticate();

        $this->post('/api/projects/200/sections', ['name' => 'Done', 'position' => 1, 'project_id' => 201]);

        $this->assertResponseCode(201);
        $this->assertResponseContains('"position": 4');

        $sections = TableRegistry::getTableLocator()->get('ProjectSections');
        $section = $sections->find()->where(['name' => 'Done'])->firstOrFail();
        $this->assertSame(200, (int)$section->project_id);
    }

    public function testCreateSectionRejectsBlankName(): void
    {
        $this->authenticate();

        $this->post('/api/projects/200/sections', ['name' => '  ']);

        $this->assertResponseCode(422);
    }

    public function testCreateSectionRejectsCrossUserProject(): void
    {
        $this->authenticate();

        $this->post('/api/projects/201/sections', ['name' => 'Injected']);

        $this->assertResponseCode(404);
    }

    public function testListSectionsReturnsOrderedSections(): void
    {
        $this->authenticate();

        $this->get('/api/projects/200/sections');

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertLessThan((int)strpos($body, '"Review"'), (int)strpos($body, '"Planning"'));
    }

    public function testReorderSectionUpdatesSiblingPositions(): void
    {
        $this->authenticate();

        $this->patch('/api/projects/200/sections/302', ['position' => 1]);

        $this->assertResponseOk();
        $this->assertResponseContains('"position": 1');

        $sections = TableRegistry::getTableLocator()->get('ProjectSections');
        $this->assertSame(1, (int)$sections->get(302)->position);
        $this->assertSame(2, (int)$sections->get(300)->position);
        $this->assertSame(3, (int)$sections->get(301)->position);
    }

    public function testReorderRejectsInvalidPosition(): void
    {
        $this->authenticate();

        $this->patch('/api/projects/200/sections/302', ['position' => 9]);

        $this->assertResponseCode(400);
        $this->assertResponseContains('"code": "BAD_REQUEST"');
    }

    public function testReorderRejectsNonNumericPosition(): void
    {
        $this->authenticate();

        $this->patch('/api/projects/200/sections/302', ['position' => 'first']);

        $this->assertResponseCode(400);
    }

    public function testEditSectionRejectsSectionFromAnotherProject(): void
    {
        $this->authenticate();

        $this->patch('/api/projects/200/sections/303', ['name' => 'Hijacked']);

        $this->assertResponseCode(404);
    }

    public function testDeleteSectionRejectsSectionWithTodos(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->get(10);
        $todo->set('project_section_id', 300);
        $todos->saveOrFail($todo);

        $this->authenticate();

        $this->delete('/api/projects/200/sections/300');

        $this->assertResponseCode(409);
    }

    public function testDeleteSectionRenumbersRemainingSections(): void
    {
        $this->authenticate();

        $this->delete('/api/projects/200/sections/300');

        $this->assertResponseOk();

        $sections = TableRegistry::getTableLocator()->get('ProjectSections');
        $this->assertFalse($sections->exists(['id' => 300]));
        $this->assertSame(1, (int)$sections->get(301)->position);
        $this->assertSame(2, (int)$sections->get(302)->position);
    }

    public function testAnonymousProjectAccessIsDenied(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/projects');

        $this->assertResponseCode(401);
        $this->assertResponseContains('"code": "UNAUTHORIZED"');
    }

    public function testAnonymousSectionCreationIsDenied(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/projects/200/sections', ['name' => 'Anonymous']);

        $this->assertResponseCode(401);
    }

    public function testListRejectsInvalidPagination(): void
    {
        $this->authenticate();

        $this->get('/api/projects?page=abc');

        $this->assertResponseCode(400);
    }
}
