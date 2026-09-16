<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\Database\Exception\QueryException;
use Cake\I18n\DateTime;
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
        'app.ActivityRecords',
        'app.Todos',
        'app.Tags',
        'app.TodosTags',
        'app.RelatedTodos',
        'app.Projects',
        'app.ProjectSections',
        'app.Notebooks',
        'app.NotebookSections',
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

    public function testViewIncludesAttachedTagsInTodoPayload(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos/10');

        $this->assertResponseOk();
        $this->assertResponseContains('"tags": [');
        $this->assertResponseContains('"id": 100');
        $this->assertResponseContains('"name": "Important"');
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
            static fn(mixed $item): bool => is_array($item) && ($item['id'] ?? null) === 10,
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

    public function testAttachTagRejectsSecondAttachAttempt(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/todos/10/tags/101', []);
        $this->assertResponseCode(201);

        $this->post('/api/todos/10/tags/101', []);
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

    public function testCreateResponseIncludesTagsCollection(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/todos', ['title' => 'Todo without tags']);

        $this->assertResponseCode(201);
        $this->assertResponseContains('"tags": []');
    }

    public function testEditResponseIncludesAttachedTags(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->patch('/api/todos/10', ['title' => 'Renamed todo']);

        $this->assertResponseOk();
        $this->assertResponseContains('"title": "Renamed todo"');
        $this->assertResponseContains('"tags": [');
        $this->assertResponseContains('"name": "Important"');
    }

    public function testWhitespaceOnlySearchAppliesNoTextFilter(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?q=%20%20%20');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner inbox todo"');
        $this->assertResponseContains('"Owner active todo"');
        $this->assertResponseNotContains('"Other user todo"');
    }

    public function testEmptySearchAppliesNoTextFilter(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?q=');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner inbox todo"');
        $this->assertResponseContains('"Owner active todo"');
    }

    public function testSearchEscapesWildcardCharacters(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?q=%25');

        $this->assertResponseOk();
        $this->assertResponseNotContains('"Owner inbox todo"');
        $this->assertResponseContains('"total": 0');
    }

    public function testCombinedTagAndStatusAndTextFilters(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?q=Owner&status=inbox&tag=100');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner inbox todo"');
        $this->assertResponseNotContains('"Owner active todo"');
    }

    public function testCrossUserTagFilterReturnsNoResults(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?tag=102');

        $this->assertResponseOk();
        $this->assertResponseContains('"total": 0');
        $this->assertResponseNotContains('"Other user todo"');
    }

    public function testNotesSearchReturnsMatchingTodos(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?q=owner%20active');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner active todo"');
        $this->assertResponseNotContains('"Owner inbox todo"');
    }

    public function testListRejectsInvalidPagination(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?page=0');

        $this->assertResponseCode(400);
        $this->assertResponseContains('"code": "BAD_REQUEST"');
    }

    public function testListOrderingIsDeterministicallyDescendingById(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos');

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertLessThan(
            (int)strpos($body, '"Owner inbox todo"'),
            (int)strpos($body, '"Owner active todo"'),
        );
    }

    public function testViewRejectsMalformedId(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos/abc');

        $this->assertResponseCode(404);
        $this->assertResponseContains('"code": "NOT_FOUND"');
    }

    public function testAttachTagRejectsUnknownTag(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/todos/10/tags/9999');

        $this->assertResponseCode(404);
    }

    public function testAttachTagRejectsCrossUserTodo(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/todos/12/tags/100');

        $this->assertResponseCode(404);
    }

    public function testDeletingTagRemovesOnlyTheRelationship(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->delete('/api/tags/100');

        $this->assertResponseOk();

        $todosTags = TableRegistry::getTableLocator()->get('TodosTags');
        $this->assertFalse($todosTags->exists(['tag_id' => 100]));
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $this->assertTrue($todos->exists(['id' => 10]));
    }

    public function testCreateRejectsMalformedJsonBody(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'input' => '{"title": "broken"',
        ]);

        $this->post('/api/todos');

        $this->assertResponseCode(400);
    }

    public function testAssignTodoToOwnedSection(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->patch('/api/todos/10', ['project_section_id' => 300]);

        $this->assertResponseOk();
        $this->assertResponseContains('"project_section_id": 300');

        $todos = TableRegistry::getTableLocator()->get('Todos');
        $this->assertSame(300, (int)$todos->get(10)->project_section_id);
    }

    public function testMoveTodoBetweenSections(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->get(10);
        $todo->set('project_section_id', 300);
        $todos->saveOrFail($todo);

        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->patch('/api/todos/10', ['project_section_id' => 301]);

        $this->assertResponseOk();
        $this->assertSame(301, (int)$todos->get(10)->project_section_id);
    }

    public function testUnassignTodoFromSection(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->get(10);
        $todo->set('project_section_id', 300);
        $todos->saveOrFail($todo);

        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->patch('/api/todos/10', ['project_section_id' => null]);

        $this->assertResponseOk();
        $this->assertResponseContains('"project_section_id": null');
        $this->assertNull($todos->get(10)->project_section_id);
    }

    public function testAssignRejectsCrossUserSection(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->patch('/api/todos/10', ['project_section_id' => 303]);

        $this->assertResponseCode(404);

        $todos = TableRegistry::getTableLocator()->get('Todos');
        $this->assertNull($todos->get(10)->project_section_id);
    }

    public function testAssignRejectsInvalidSectionValue(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->patch('/api/todos/10', ['project_section_id' => 'abc']);

        $this->assertResponseCode(400);
    }

    public function testProjectFilterReturnsAssignedTodos(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->get(10);
        $todo->set('project_section_id', 300);
        $todos->saveOrFail($todo);

        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?project=200');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner inbox todo"');
        $this->assertResponseNotContains('"Owner active todo"');
    }

    public function testSectionFilterCombinesWithStatusFilter(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');
        foreach ([10, 11] as $id) {
            $todo = $todos->get($id);
            $todo->set('project_section_id', 300);
            $todos->saveOrFail($todo);
        }

        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?section=300&status=active');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner active todo"');
        $this->assertResponseNotContains('"Owner inbox todo"');
    }

    public function testCrossUserProjectFilterReturnsNoResults(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?project=201');

        $this->assertResponseOk();
        $this->assertResponseContains('"total": 0');
    }

    public function testListRejectsInvalidProjectFilter(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?project=zero');

        $this->assertResponseCode(400);
    }

    public function testUnassignedTodosRemainListedWithoutProjectFilter(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos');

        $this->assertResponseOk();
        $this->assertResponseContains('"project_section_id": null');
    }

    public function testAssignTodoToOwnedNotebookSection(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->patch('/api/todos/10', ['notebook_section_id' => 500]);

        $this->assertResponseOk();
        $this->assertResponseContains('"notebook_section_id": 500');

        $todos = TableRegistry::getTableLocator()->get('Todos');
        $this->assertSame(500, (int)$todos->get(10)->notebook_section_id);
    }

    public function testAssignRejectsCrossUserNotebookSection(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->patch('/api/todos/10', ['notebook_section_id' => 503]);

        $this->assertResponseCode(404);
    }

    public function testSimultaneousProjectAndNotebookAssignmentIsRejected(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->get(10);
        $todo->set('project_section_id', 300);
        $todos->saveOrFail($todo);

        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->patch('/api/todos/10', ['notebook_section_id' => 500]);

        $this->assertResponseCode(409);
        $this->assertResponseContains('"code": "CONFLICT"');
        $this->assertNull($todos->get(10)->notebook_section_id);
        $this->assertSame(300, (int)$todos->get(10)->project_section_id);
    }

    public function testMoveTodoFromProjectSectionToNotebookSectionInOneRequest(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->get(10);
        $todo->set('project_section_id', 300);
        $todos->saveOrFail($todo);

        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->patch('/api/todos/10', [
            'project_section_id' => null,
            'notebook_section_id' => 500,
        ]);

        $this->assertResponseOk();
        $updated = $todos->get(10);
        $this->assertNull($updated->project_section_id);
        $this->assertSame(500, (int)$updated->notebook_section_id);
    }

    public function testDatabaseRejectsDualSectionAssignment(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');

        $this->expectException(QueryException::class);
        $todos->getConnection()->update('todos', [
            'project_section_id' => 300,
            'notebook_section_id' => 500,
        ], ['id' => 10]);
    }

    public function testNotebookFilterReturnsAssignedTodos(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->get(10);
        $todo->set('notebook_section_id', 500);
        $todos->saveOrFail($todo);

        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?notebook=400');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner inbox todo"');
        $this->assertResponseNotContains('"Owner active todo"');
    }

    public function testNotebookSectionFilterCombinesWithTagFilter(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->get(10);
        $todo->set('notebook_section_id', 500);
        $todos->saveOrFail($todo);

        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?notebook_section=500&tag=100');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner inbox todo"');
    }

    public function testCrossUserNotebookFilterReturnsNoResults(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos?notebook=401');

        $this->assertResponseOk();
        $this->assertResponseContains('"total": 0');
    }

    private function authenticatedJsonRequest(int $userId = 1): void
    {
        $this->session(['Auth.user_id' => $userId]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        ]);
    }

    private function moveTodoTo(int $todoId, string ...$actions): void
    {
        foreach ($actions as $action) {
            $this->authenticatedJsonRequest();
            $this->post('/api/todos/' . $todoId . '/' . $action, '{}');
            $this->assertResponseOk();
        }
    }

    public function testArchiveEndpointArchivesOwnedTodo(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos/10/archive', '{}');

        $this->assertResponseOk();
        $this->assertResponseContains('"status": "archived"');
        $this->assertResponseNotContains('"archived_at": null');
    }

    public function testActivateAndCompleteEndpoints(): void
    {
        $this->authenticatedJsonRequest();
        $this->post('/api/todos/10/activate', '{}');
        $this->assertResponseOk();
        $this->assertResponseContains('"status": "active"');

        $this->authenticatedJsonRequest();
        $this->post('/api/todos/10/complete', '{}');
        $this->assertResponseOk();
        $this->assertResponseContains('"status": "done"');
    }

    public function testTrashRestoreRoundTripPreservesPreviousStatus(): void
    {
        $this->moveTodoTo(10, 'activate', 'trash');

        $this->authenticatedJsonRequest();
        $this->post('/api/todos/10/restore', '{}');

        $this->assertResponseOk();
        $this->assertResponseContains('"status": "active"');
        $this->assertResponseContains('"trashed_at": null');
    }

    public function testRepeatedTransitionReturnsConflict(): void
    {
        $this->moveTodoTo(10, 'archive');

        $this->authenticatedJsonRequest();
        $this->post('/api/todos/10/archive', '{}');

        $this->assertResponseCode(409);
        $this->assertResponseContains('"code": "CONFLICT"');
    }

    public function testInvalidTransitionFromTrashedReturnsConflict(): void
    {
        $this->moveTodoTo(10, 'trash');

        $this->authenticatedJsonRequest();
        $this->post('/api/todos/10/activate', '{}');

        $this->assertResponseCode(409);
    }

    public function testRestoreOfNonTrashedTodoReturnsConflict(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos/10/restore', '{}');

        $this->assertResponseCode(409);
    }

    public function testPermanentDeleteRequiresTrashedTodo(): void
    {
        $this->authenticatedJsonRequest();
        $this->delete('/api/todos/10/permanent');
        $this->assertResponseCode(409);

        $this->moveTodoTo(10, 'trash');

        $this->authenticatedJsonRequest();
        $this->delete('/api/todos/10/permanent');
        $this->assertResponseOk();

        $this->authenticatedJsonRequest();
        $this->get('/api/todos/10');
        $this->assertResponseCode(404);
    }

    public function testArchivedAndTrashedTodosAreHiddenFromDefaultList(): void
    {
        $this->moveTodoTo(10, 'archive');
        $this->moveTodoTo(11, 'trash');

        $this->authenticatedJsonRequest();
        $this->get('/api/todos');

        $this->assertResponseOk();
        $this->assertResponseNotContains('"Owner inbox todo"');
        $this->assertResponseNotContains('"Owner active todo"');
    }

    public function testArchivedTodosAreVisibleWithExplicitStatusFilter(): void
    {
        $this->moveTodoTo(10, 'archive');

        $this->authenticatedJsonRequest();
        $this->get('/api/todos?status=archived');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner inbox todo"');
    }

    public function testStatusCannotBeSetDirectlyToArchivedOnCreate(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos', json_encode(['title' => 'Sneaky', 'status' => 'archived']));

        $this->assertResponseCode(409);
    }

    public function testStatusCannotBeSetDirectlyToTrashedOnUpdate(): void
    {
        $this->authenticatedJsonRequest();

        $this->patch('/api/todos/10', json_encode(['status' => 'trashed']));

        $this->assertResponseCode(409);
    }

    public function testUnknownStatusOnCreateIsRejected(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos', json_encode(['title' => 'Bad status', 'status' => 'nope']));

        $this->assertResponseCode(422);
    }

    public function testLifecycleActionOnAnotherUsersTodoReturnsNotFound(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos/12/archive', '{}');

        $this->assertResponseCode(404);
    }

    public function testAnonymousLifecycleActionIsDenied(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        ]);

        $this->post('/api/todos/10/archive', '{}');

        $this->assertResponseCode(401);
    }

    public function testLifecycleActionWithMalformedIdReturnsNotFound(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos/abc/archive', '{}');

        $this->assertResponseCode(404);
    }

    public function testTagsSurviveTrashAndRestore(): void
    {
        $this->moveTodoTo(10, 'trash');

        $this->authenticatedJsonRequest();
        $this->post('/api/todos/10/restore', '{}');

        $this->assertResponseOk();
        $this->assertResponseContains('"Important"');
    }

    public function testReviewQueueReturnsOnlyOwnedEligibleTodosWithReasons(): void
    {
        $this->authenticatedJsonRequest();

        $this->get('/api/todos/review-queue');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner inbox todo"');
        $this->assertResponseNotContains('"Other user todo"');
        $this->assertResponseContains('"never_reviewed"');
        $this->assertResponseContains('"no_section"');
    }

    public function testReviewQueueExcludesArchivedAndTrashedTodos(): void
    {
        $this->moveTodoTo(10, 'archive');
        $this->moveTodoTo(11, 'trash');

        $this->authenticatedJsonRequest();
        $this->get('/api/todos/review-queue');

        $this->assertResponseOk();
        $this->assertResponseContains('"total": 0');
    }

    public function testReviewQueueExcludesDoneTodos(): void
    {
        $this->moveTodoTo(10, 'complete');

        $this->authenticatedJsonRequest();
        $this->get('/api/todos/review-queue');

        $this->assertResponseOk();
        $this->assertResponseNotContains('"Owner inbox todo"');
    }

    public function testRestoredTodoReappearsInReviewQueue(): void
    {
        $this->moveTodoTo(10, 'trash');
        $this->moveTodoTo(10, 'restore');

        $this->authenticatedJsonRequest();
        $this->get('/api/todos/review-queue');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner inbox todo"');
    }

    public function testReviewQueueIsOrderedByNextReviewThenId(): void
    {
        $this->authenticatedJsonRequest();
        $this->post('/api/todos/11/snooze', json_encode(['days' => 1]));
        $this->assertResponseOk();

        $this->authenticatedJsonRequest();
        $this->get('/api/todos/review-queue');

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertLessThan(
            (int)strpos($body, 'Owner active todo'),
            (int)strpos($body, 'Owner inbox todo'),
        );
    }

    public function testMarkReviewedUpdatesTimestampsAndClearsReviewReasons(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos/10/reviewed', json_encode(['review_interval_days' => 14]));

        $this->assertResponseOk();
        $this->assertResponseContains('"review_interval_days": 14');
        $this->assertResponseNotContains('"last_reviewed_at": null');
        $this->assertResponseNotContains('"next_review_at": null');
    }

    public function testMarkReviewedAcceptsExplicitNextReviewDate(): void
    {
        $this->authenticatedJsonRequest();
        $future = DateTime::now()->addDays(45)->format(DATE_ATOM);

        $this->post('/api/todos/10/reviewed', json_encode(['next_review_at' => $future]));

        $this->assertResponseOk();
    }

    public function testSnoozeUpdatesNextReview(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos/10/snooze', json_encode(['days' => 10]));

        $this->assertResponseOk();
        $this->assertResponseNotContains('"next_review_at": null');
        $this->assertResponseContains('"last_reviewed_at": null');
    }

    public function testAddingTagRemovesTheUntaggedReviewReason(): void
    {
        $this->authenticatedJsonRequest();
        $this->post('/api/todos/11/tags/100', '{}');
        $this->assertResponseSuccess();

        $this->authenticatedJsonRequest();
        $this->get('/api/todos/review-queue');
        $this->assertResponseOk();

        $decoded = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($decoded);
        foreach ($decoded['data']['items'] as $entry) {
            if ($entry['todo']['id'] === 11) {
                $this->assertNotContains('no_tags', $entry['reasons']);
            }
        }
    }

    public function testMarkReviewedRejectsInvalidInterval(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos/10/reviewed', json_encode(['review_interval_days' => 0]));

        $this->assertResponseCode(422);
    }

    public function testMarkReviewedRejectsMalformedDate(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos/10/reviewed', json_encode(['next_review_at' => 'not-a-date']));

        $this->assertResponseCode(422);
    }

    public function testMarkReviewedRejectsPastDate(): void
    {
        $this->authenticatedJsonRequest();
        $past = DateTime::now()->subDays(1)->format(DATE_ATOM);

        $this->post('/api/todos/10/reviewed', json_encode(['next_review_at' => $past]));

        $this->assertResponseCode(422);
    }

    public function testSnoozeRejectsMissingArguments(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos/10/snooze', '{}');

        $this->assertResponseCode(422);
    }

    public function testSnoozeRejectsBothArguments(): void
    {
        $this->authenticatedJsonRequest();
        $future = DateTime::now()->addDays(3)->format(DATE_ATOM);

        $this->post('/api/todos/10/snooze', json_encode(['days' => 3, 'until' => $future]));

        $this->assertResponseCode(422);
    }

    public function testReviewActionOnAnotherUsersTodoIsDenied(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos/12/reviewed', '{}');

        $this->assertResponseCode(404);
    }

    public function testAnonymousReviewQueueIsDenied(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/todos/review-queue');

        $this->assertResponseCode(401);
    }

    public function testReviewQueueRejectsInvalidPagination(): void
    {
        $this->authenticatedJsonRequest();

        $this->get('/api/todos/review-queue?page=0');

        $this->assertResponseCode(400);
    }

    public function testReviewActionsDoNotChangeStatus(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos/10/reviewed', '{}');

        $this->assertResponseOk();
        $this->assertResponseContains('"status": "inbox"');
    }

    public function testRelateAndHierarchyExposeRelatedTodos(): void
    {
        $this->authenticatedJsonRequest();
        $this->post('/api/todos/10/related/11', '{}');
        $this->assertResponseCode(201);

        $this->authenticatedJsonRequest();
        $this->get('/api/todos/10/hierarchy');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner active todo"');
    }

    public function testRelateIsRejectedForDuplicatePair(): void
    {
        $this->authenticatedJsonRequest();
        $this->post('/api/todos/10/related/11', '{}');
        $this->assertResponseCode(201);

        $this->authenticatedJsonRequest();
        $this->post('/api/todos/11/related/10', '{}');

        $this->assertResponseCode(409);
    }

    public function testRelateRejectsSelfReference(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos/10/related/10', '{}');

        $this->assertResponseCode(409);
    }

    public function testRelateToAnotherUsersTodoReturnsNotFound(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos/10/related/12', '{}');

        $this->assertResponseCode(404);
    }

    public function testRelateToUnknownTodoReturnsNotFound(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos/10/related/999999', '{}');

        $this->assertResponseCode(404);
    }

    public function testUnrelateRemovesLinkWithoutDeletingTodos(): void
    {
        $this->authenticatedJsonRequest();
        $this->post('/api/todos/10/related/11', '{}');
        $this->assertResponseCode(201);

        $this->authenticatedJsonRequest();
        $this->delete('/api/todos/11/related/10');
        $this->assertResponseOk();

        $this->authenticatedJsonRequest();
        $this->get('/api/todos/10');
        $this->assertResponseOk();

        $this->authenticatedJsonRequest();
        $this->get('/api/todos/11');
        $this->assertResponseOk();
    }

    public function testUnrelateUnknownLinkReturnsNotFound(): void
    {
        $this->authenticatedJsonRequest();

        $this->delete('/api/todos/10/related/11');

        $this->assertResponseCode(404);
    }

    public function testSetParentCreatesChildRelationship(): void
    {
        $this->authenticatedJsonRequest();
        $this->patch('/api/todos/11/parent', json_encode(['parent_todo_id' => 10]));
        $this->assertResponseOk();
        $this->assertResponseContains('"parent_todo_id": 10');

        $this->authenticatedJsonRequest();
        $this->get('/api/todos/10/hierarchy');
        $this->assertResponseOk();
        $this->assertResponseContains('"Owner active todo"');
    }

    public function testSetParentAcceptsNullToDetach(): void
    {
        $this->authenticatedJsonRequest();
        $this->patch('/api/todos/11/parent', json_encode(['parent_todo_id' => 10]));
        $this->assertResponseOk();

        $this->authenticatedJsonRequest();
        $this->patch('/api/todos/11/parent', json_encode(['parent_todo_id' => null]));

        $this->assertResponseOk();
        $this->assertResponseContains('"parent_todo_id": null');
    }

    public function testSetParentRejectsCycle(): void
    {
        $this->authenticatedJsonRequest();
        $this->patch('/api/todos/11/parent', json_encode(['parent_todo_id' => 10]));
        $this->assertResponseOk();

        $this->authenticatedJsonRequest();
        $this->patch('/api/todos/10/parent', json_encode(['parent_todo_id' => 11]));

        $this->assertResponseCode(409);
    }

    public function testSetParentRejectsSelfParent(): void
    {
        $this->authenticatedJsonRequest();

        $this->patch('/api/todos/10/parent', json_encode(['parent_todo_id' => 10]));

        $this->assertResponseCode(409);
    }

    public function testSetParentRejectsAnotherUsersTodo(): void
    {
        $this->authenticatedJsonRequest();

        $this->patch('/api/todos/10/parent', json_encode(['parent_todo_id' => 12]));

        $this->assertResponseCode(404);
    }

    public function testSetParentRejectsMalformedValue(): void
    {
        $this->authenticatedJsonRequest();

        $this->patch('/api/todos/10/parent', json_encode(['parent_todo_id' => 'abc']));

        $this->assertResponseCode(422);
    }

    public function testTerminalObjectiveCanBeSetAndCleared(): void
    {
        $this->authenticatedJsonRequest();
        $this->patch('/api/todos/10/objective', json_encode(['terminal_objective' => 'Answer the question']));
        $this->assertResponseOk();
        $this->assertResponseContains('"terminal_objective": "Answer the question"');

        $this->authenticatedJsonRequest();
        $this->patch('/api/todos/10/objective', json_encode(['terminal_objective' => null]));
        $this->assertResponseOk();
        $this->assertResponseContains('"terminal_objective": null');
    }

    public function testTerminalObjectiveRejectsNonStringValue(): void
    {
        $this->authenticatedJsonRequest();

        $this->patch('/api/todos/10/objective', json_encode(['terminal_objective' => ['nope']]));

        $this->assertResponseCode(422);
    }

    public function testChildReportsResultWithoutMutatingParent(): void
    {
        $this->authenticatedJsonRequest();
        $this->patch('/api/todos/11/parent', json_encode(['parent_todo_id' => 10]));
        $this->assertResponseOk();

        $this->authenticatedJsonRequest();
        $this->post('/api/todos/11/result', json_encode(['result' => 'Investigation complete']));
        $this->assertResponseOk();
        $this->assertResponseContains('"result_summary": "Investigation complete"');

        $this->authenticatedJsonRequest();
        $this->get('/api/todos/10');
        $this->assertResponseOk();
        $this->assertResponseContains('"status": "inbox"');
        $this->assertResponseContains('"result_summary": null');
        $this->assertResponseContains('"objective_satisfied_at": null');
    }

    public function testReportResultRequiresParent(): void
    {
        $this->authenticatedJsonRequest();

        $this->post('/api/todos/10/result', json_encode(['result' => 'No parent']));

        $this->assertResponseCode(409);
    }

    public function testReportResultIsNotRepeatable(): void
    {
        $this->authenticatedJsonRequest();
        $this->patch('/api/todos/11/parent', json_encode(['parent_todo_id' => 10]));
        $this->assertResponseOk();

        $this->authenticatedJsonRequest();
        $this->post('/api/todos/11/result', json_encode(['result' => 'First']));
        $this->assertResponseOk();

        $this->authenticatedJsonRequest();
        $this->post('/api/todos/11/result', json_encode(['result' => 'Second']));

        $this->assertResponseCode(409);
    }

    public function testReportResultRejectsMalformedPayload(): void
    {
        $this->authenticatedJsonRequest();
        $this->patch('/api/todos/11/parent', json_encode(['parent_todo_id' => 10]));
        $this->assertResponseOk();

        $this->authenticatedJsonRequest();
        $this->post('/api/todos/11/result', json_encode(['result' => 42]));

        $this->assertResponseCode(422);
    }

    public function testPermanentDeleteIsBlockedWhileChildrenExist(): void
    {
        $this->authenticatedJsonRequest();
        $this->patch('/api/todos/11/parent', json_encode(['parent_todo_id' => 10]));
        $this->assertResponseOk();

        $this->moveTodoTo(10, 'trash');

        $this->authenticatedJsonRequest();
        $this->delete('/api/todos/10/permanent');

        $this->assertResponseCode(409);
    }

    public function testHierarchyOnAnotherUsersTodoReturnsNotFound(): void
    {
        $this->authenticatedJsonRequest();

        $this->get('/api/todos/12/hierarchy');

        $this->assertResponseCode(404);
    }

    public function testAnonymousRelationshipActionIsDenied(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        ]);

        $this->post('/api/todos/10/related/11', '{}');

        $this->assertResponseCode(401);
    }

    public function testTerminalObjectiveDoesNotChangeLifecycleStatus(): void
    {
        $this->authenticatedJsonRequest();
        $this->patch('/api/todos/10/objective', json_encode(['terminal_objective' => 'Ship it']));

        $this->assertResponseOk();
        $this->assertResponseContains('"status": "inbox"');
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
