<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class NotebooksControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Notebooks',
        'app.NotebookSections',
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

    public function testListReturnsOnlyOwnedNotebooks(): void
    {
        $this->authenticate();

        $this->get('/api/notebooks');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner notebook"');
        $this->assertResponseNotContains('"Other user notebook"');
    }

    public function testCreateNotebookAssignsOwnership(): void
    {
        $this->authenticate();

        $this->post('/api/notebooks', [
            'name' => 'Launch plan',
            'description' => 'ship it',
            'user_id' => 2,
        ]);

        $this->assertResponseCode(201);
        $this->assertResponseContains('"name": "Launch plan"');

        $notebooks = TableRegistry::getTableLocator()->get('Notebooks');
        $notebook = $notebooks->find()->where(['name' => 'Launch plan'])->firstOrFail();
        $this->assertSame(1, (int)$notebook->user_id);
    }

    public function testCreateRejectsBlankName(): void
    {
        $this->authenticate();

        $this->post('/api/notebooks', ['name' => '']);

        $this->assertResponseCode(422);
        $this->assertResponseContains('"code": "VALIDATION_ERROR"');
    }

    public function testViewReturnsOrderedSectionsWithTodos(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->get(10);
        $todo->set('notebook_section_id', 501);
        $todos->saveOrFail($todo);

        $this->authenticate();

        $this->get('/api/notebooks/400');

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertLessThan((int)strpos($body, '"Ideas"'), (int)strpos($body, '"Reference"'));
        $this->assertLessThan((int)strpos($body, '"Archive notes"'), (int)strpos($body, '"Ideas"'));
        $this->assertResponseContains('"Owner inbox todo"');
    }

    public function testViewRejectsCrossUserNotebook(): void
    {
        $this->authenticate();

        $this->get('/api/notebooks/401');

        $this->assertResponseCode(404);
    }

    public function testViewRejectsMalformedId(): void
    {
        $this->authenticate();

        $this->get('/api/notebooks/abc');

        $this->assertResponseCode(404);
    }

    public function testEditCannotReassignOwnership(): void
    {
        $this->authenticate();

        $this->patch('/api/notebooks/400', ['name' => 'Renamed notebook', 'user_id' => 2]);

        $this->assertResponseOk();

        $notebooks = TableRegistry::getTableLocator()->get('Notebooks');
        $notebook = $notebooks->get(400);
        $this->assertSame(1, (int)$notebook->user_id);
        $this->assertSame('Renamed notebook', $notebook->name);
    }

    public function testEditRejectsCrossUserNotebook(): void
    {
        $this->authenticate();

        $this->patch('/api/notebooks/401', ['name' => 'Hijacked']);

        $this->assertResponseCode(404);
    }

    public function testDeleteRejectsNotebookWithSections(): void
    {
        $this->authenticate();

        $this->delete('/api/notebooks/400');

        $this->assertResponseCode(409);
        $this->assertResponseContains('"code": "CONFLICT"');
    }

    public function testDeleteRemovesEmptyNotebook(): void
    {
        $notebooks = TableRegistry::getTableLocator()->get('Notebooks');
        $notebook = $notebooks->newEmptyEntity();
        $notebook->set('user_id', 1);
        $notebook->set('name', 'Empty notebook');
        $notebooks->saveOrFail($notebook);

        $this->authenticate();

        $this->delete('/api/notebooks/' . $notebook->id);

        $this->assertResponseOk();
        $this->assertFalse($notebooks->exists(['id' => $notebook->id]));
    }

    public function testCreateSectionAppendsAtEnd(): void
    {
        $this->authenticate();

        $this->post('/api/notebooks/400/sections', ['name' => 'Done', 'position' => 1, 'notebook_id' => 401]);

        $this->assertResponseCode(201);
        $this->assertResponseContains('"position": 4');

        $sections = TableRegistry::getTableLocator()->get('NotebookSections');
        $section = $sections->find()->where(['name' => 'Done'])->firstOrFail();
        $this->assertSame(400, (int)$section->notebook_id);
    }

    public function testCreateSectionRejectsBlankName(): void
    {
        $this->authenticate();

        $this->post('/api/notebooks/400/sections', ['name' => '  ']);

        $this->assertResponseCode(422);
    }

    public function testCreateSectionRejectsCrossUserNotebook(): void
    {
        $this->authenticate();

        $this->post('/api/notebooks/401/sections', ['name' => 'Injected']);

        $this->assertResponseCode(404);
    }

    public function testListSectionsReturnsOrderedSections(): void
    {
        $this->authenticate();

        $this->get('/api/notebooks/400/sections');

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertLessThan((int)strpos($body, '"Archive notes"'), (int)strpos($body, '"Reference"'));
    }

    public function testReorderSectionUpdatesSiblingPositions(): void
    {
        $this->authenticate();

        $this->patch('/api/notebooks/400/sections/502', ['position' => 1]);

        $this->assertResponseOk();
        $this->assertResponseContains('"position": 1');

        $sections = TableRegistry::getTableLocator()->get('NotebookSections');
        $this->assertSame(1, (int)$sections->get(502)->position);
        $this->assertSame(2, (int)$sections->get(500)->position);
        $this->assertSame(3, (int)$sections->get(501)->position);
    }

    public function testReorderRejectsInvalidPosition(): void
    {
        $this->authenticate();

        $this->patch('/api/notebooks/400/sections/502', ['position' => 9]);

        $this->assertResponseCode(400);
        $this->assertResponseContains('"code": "BAD_REQUEST"');
    }

    public function testReorderRejectsNonNumericPosition(): void
    {
        $this->authenticate();

        $this->patch('/api/notebooks/400/sections/502', ['position' => 'first']);

        $this->assertResponseCode(400);
    }

    public function testEditSectionRejectsSectionFromAnotherNotebook(): void
    {
        $this->authenticate();

        $this->patch('/api/notebooks/400/sections/503', ['name' => 'Hijacked']);

        $this->assertResponseCode(404);
    }

    public function testDeleteSectionRejectsSectionWithTodos(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->get(10);
        $todo->set('notebook_section_id', 500);
        $todos->saveOrFail($todo);

        $this->authenticate();

        $this->delete('/api/notebooks/400/sections/500');

        $this->assertResponseCode(409);
    }

    public function testDeleteSectionRenumbersRemainingSections(): void
    {
        $this->authenticate();

        $this->delete('/api/notebooks/400/sections/500');

        $this->assertResponseOk();

        $sections = TableRegistry::getTableLocator()->get('NotebookSections');
        $this->assertFalse($sections->exists(['id' => 500]));
        $this->assertSame(1, (int)$sections->get(501)->position);
        $this->assertSame(2, (int)$sections->get(502)->position);
    }

    public function testAnonymousNotebookAccessIsDenied(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/notebooks');

        $this->assertResponseCode(401);
        $this->assertResponseContains('"code": "UNAUTHORIZED"');
    }

    public function testAnonymousSectionCreationIsDenied(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/notebooks/400/sections', ['name' => 'Anonymous']);

        $this->assertResponseCode(401);
    }

    public function testListRejectsInvalidPagination(): void
    {
        $this->authenticate();

        $this->get('/api/notebooks?page=abc');

        $this->assertResponseCode(400);
    }
}
