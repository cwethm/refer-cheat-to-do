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
        'app.Tags',
        'app.TodosTags',
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

    public function testTextSearchReturnsMatchingTodos(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?q=active');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner active todo"');
        $this->assertResponseNotContains('"Owner inbox todo"');
    }

    public function testTagFilterReturnsMatchingOwnedTodos(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?tag=100');

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

    public function testListRejectsInvalidTagFilter(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?tag=zero');

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

    public function testAttachTagCreatesTodoTagRelationship(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/todos/10/tags/101', []);

        $this->assertResponseCode(201);
        $this->assertResponseContains('"Tag attached."');

        $todosTags = TableRegistry::getTableLocator()->get('TodosTags');
        $this->assertTrue($todosTags->exists(['todo_id' => 10, 'tag_id' => 101]));
    }

    public function testListIncludesAttachedTagsInTodoPayload(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/todos/10/tags/101', []);
        $this->assertResponseCode(201);

        $this->get('/api/todos');
        $this->assertResponseOk();

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('data', $decoded);
        $this->assertIsArray($decoded['data']);
        $this->assertArrayHasKey('items', $decoded['data']);
        $this->assertIsArray($decoded['data']['items']);

        $todo = array_values(array_filter(
            $decoded['data']['items'],
            static fn (mixed $item): bool => is_array($item) && ($item['id'] ?? null) === 10,
        ))[0] ?? null;
        $this->assertIsArray($todo);
        $this->assertArrayHasKey('tags', $todo);
        $this->assertContains(
            ['id' => 101, 'name' => 'Reading'],
            $todo['tags'],
        );
    }

    public function testAttachTagRejectsDuplicateRelationship(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/todos/10/tags/100', []);

        $this->assertResponseCode(409);
        $this->assertResponseContains('"code": "CONFLICT"');
    }

    public function testAttachTagRejectsCrossUserTag(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/todos/10/tags/102', []);

        $this->assertResponseCode(404);
        $this->assertResponseContains('"code": "NOT_FOUND"');
    }

    public function testDetachTagDeletesTodoTagRelationship(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->delete('/api/todos/10/tags/100');

        $this->assertResponseOk();
        $this->assertResponseContains('"Tag detached."');

        $todosTags = TableRegistry::getTableLocator()->get('TodosTags');
        $this->assertFalse($todosTags->exists(['todo_id' => 10, 'tag_id' => 100]));
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
