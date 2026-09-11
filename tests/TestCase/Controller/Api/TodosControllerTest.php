<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class TodosControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Todos',
    ];

    public function testListReturnsOnlyCurrentUserTodos(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos');

        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $this->assertResponseContains('"Owner inbox todo"');
        $this->assertResponseContains('"Owner active todo"');
        $this->assertResponseNotContains('"Other user todo"');
    }

    public function testInboxFilterReturnsOnlyInboxTodos(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?status=inbox');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner inbox todo"');
        $this->assertResponseNotContains('"Owner active todo"');
    }

    public function testListRejectsInvalidStatusFilter(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?status=trash');

        $this->assertResponseCode(400);
        $this->assertResponseContains('"code": "BAD_REQUEST"');
    }

    public function testCreateTodoAssignsCurrentUser(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/todos', [
            'title' => 'Created todo',
            'notes' => 'new note',
            'status' => 'inbox',
            'user_id' => 2,
        ]);

        $this->assertResponseCode(201);
        $this->assertContentType('application/json');
        $this->assertResponseContains('"title": "Created todo"');

        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->find()->where(['title' => 'Created todo'])->firstOrFail();
        $this->assertSame(1, (int)$todo->user_id);
    }

    public function testCreateRejectsMissingTitle(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/todos', [
            'notes' => 'missing title',
            'status' => 'inbox',
        ]);

        $this->assertResponseCode(422);
        $this->assertResponseContains('"code": "VALIDATION_ERROR"');
    }

    public function testViewRejectsCrossUserAccess(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos/12');

        $this->assertResponseCode(404);
        $this->assertResponseContains('"code": "NOT_FOUND"');
    }

    public function testEditUpdatesAllowedFields(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->patch('/api/todos/10', [
            'title' => 'Updated title',
            'status' => 'active',
            'user_id' => 2,
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('"title": "Updated title"');
        $this->assertResponseContains('"status": "active"');

        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->get(10);
        $this->assertSame(1, (int)$todo->user_id);
    }

    public function testEditUnknownTodoReturnsNotFound(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->patch('/api/todos/99999', [
            'title' => 'nope',
        ]);

        $this->assertResponseCode(404);
        $this->assertResponseContains('"code": "NOT_FOUND"');
    }

    public function testAnonymousTodoAccessIsDenied(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos');

        $this->assertResponseCode(401);
        $this->assertResponseContains('"code": "UNAUTHORIZED"');
    }
}
