<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class ActivityControllerTest extends TestCase
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
        'app.Notebooks',
        'app.NotebookSections',
        'app.Todos',
        'app.Tags',
        'app.TodosTags',
        'app.Libraries',
        'app.LibrariesProjects',
        'app.LibrariesNotebooks',
        'app.CapabilityGrants',
        'app.CrossContextRequests',
    ];

    private function authenticate(int $userId = 1): void
    {
        $this->session(['Auth.user_id' => $userId]);
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $body = json_decode((string)$this->_response?->getBody(), true);
        $this->assertIsArray($body);

        return $body;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activityItems(string $url = '/api/activity'): array
    {
        $this->get($url);
        $this->assertResponseOk();
        $payload = $this->payload();

        return $payload['data']['items'];
    }

    public function testAnonymousCannotListActivity(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->get('/api/activity');

        $this->assertResponseCode(401);
    }

    public function testEmptyActivityListIsReturned(): void
    {
        $this->authenticate();

        $this->get('/api/activity');

        $this->assertResponseOk();
        $payload = $this->payload();
        $this->assertSame([], $payload['data']['items']);
        $this->assertSame(0, $payload['meta']['pagination']['total']);
    }

    public function testCreatingTodoRecordsActivity(): void
    {
        $this->authenticate();
        $this->post('/api/todos', ['title' => 'Write the audit foundation']);
        $this->assertResponseCode(201);
        $todoId = $this->payload()['data']['todo']['id'];

        $items = $this->activityItems();

        $this->assertCount(1, $items);
        $this->assertSame('todo.created', $items[0]['action']);
        $this->assertSame('todo', $items[0]['subject_type']);
        $this->assertSame($todoId, $items[0]['subject_id']);
        $this->assertSame('inbox', $items[0]['metadata']['status']);
        $this->assertNotNull($items[0]['created']);
    }

    public function testUpdatingTodoRecordsActivityWithChangedFields(): void
    {
        $this->authenticate();
        $this->patch('/api/todos/10', ['title' => 'Renamed']);
        $this->assertResponseOk();

        $items = $this->activityItems('/api/activity?action=todo.updated');

        $this->assertCount(1, $items);
        $this->assertSame(['title'], $items[0]['metadata']['fields']);
    }

    public function testLifecycleTransitionRecordsActivity(): void
    {
        $this->authenticate();
        $this->post('/api/todos/10/activate');
        $this->assertResponseOk();

        $items = $this->activityItems('/api/activity?subject_type=todo&subject_id=10');

        $this->assertSame('todo.active', $items[0]['action']);
        $this->assertSame('active', $items[0]['metadata']['status']);
    }

    public function testTagAttachAndDetachRecordActivity(): void
    {
        $this->authenticate();
        $this->post('/api/todos/11/tags/100');
        $this->assertResponseCode(201);
        $this->delete('/api/todos/11/tags/100');
        $this->assertResponseOk();

        $items = $this->activityItems('/api/activity?subject_type=todo&subject_id=11');

        $actions = array_column($items, 'action');
        $this->assertSame(['todo.tag_detached', 'todo.tag_attached'], $actions);
        $this->assertSame(100, $items[0]['metadata']['tag_id']);
    }

    public function testProjectCreationRecordsActivity(): void
    {
        $this->authenticate();
        $this->post('/api/projects', ['name' => 'Audit project']);
        $this->assertResponseCode(201);

        $items = $this->activityItems('/api/activity?subject_type=project');

        $this->assertCount(1, $items);
        $this->assertSame('project.created', $items[0]['action']);
    }

    public function testNotebookCreationRecordsActivity(): void
    {
        $this->authenticate();
        $this->post('/api/notebooks', ['name' => 'Audit notebook']);
        $this->assertResponseCode(201);

        $items = $this->activityItems('/api/activity?subject_type=notebook');

        $this->assertCount(1, $items);
        $this->assertSame('notebook.created', $items[0]['action']);
    }

    public function testLibraryMembershipChangesRecordActivity(): void
    {
        $this->authenticate();
        $this->post('/api/libraries/601/members/project/200');
        $this->assertResponseCode(201);
        $this->delete('/api/libraries/601/members/project/200');
        $this->assertResponseOk();

        $items = $this->activityItems('/api/activity?subject_type=library');

        $this->assertSame(['library.member_removed', 'library.member_added'], array_column($items, 'action'));
        $this->assertSame('project', $items[0]['metadata']['member_type']);
        $this->assertSame(200, $items[0]['metadata']['member_id']);
    }

    public function testCapabilityGrantAndRevokeRecordActivity(): void
    {
        $this->authenticate();
        $this->post('/api/capabilities', [
            'subject_user_id' => 2,
            'resource_type' => 'project',
            'resource_id' => 200,
            'capability' => 'read',
        ]);
        $this->assertResponseCode(201);
        $grantId = $this->payload()['data']['grant']['id'];

        $this->delete('/api/capabilities/' . $grantId);
        $this->assertResponseOk();

        $items = $this->activityItems('/api/activity?subject_type=capability_grant');

        $this->assertSame(['capability.revoked', 'capability.granted'], array_column($items, 'action'));
        $this->assertSame('read', $items[0]['metadata']['capability']);
        $this->assertSame(2, $items[0]['metadata']['subject_user_id']);
    }

    public function testCrossContextRequestLifecycleRecordsActivity(): void
    {
        $this->authenticate(2);
        $this->post('/api/capabilities', [
            'subject_user_id' => 1,
            'resource_type' => 'notebook',
            'resource_id' => 401,
            'capability' => 'send_task',
        ]);
        $this->assertResponseCode(201);

        $this->authenticate(1);
        $this->post('/api/cross-context-requests', [
            'source_type' => 'project',
            'source_id' => 200,
            'target_type' => 'notebook',
            'target_id' => 401,
            'request_type' => 'task',
            'title' => 'Please review',
        ]);
        $this->assertResponseCode(201);
        $requestId = $this->payload()['data']['request']['id'];

        $this->authenticate(2);
        $this->post('/api/cross-context-requests/' . $requestId . '/reject');
        $this->assertResponseOk();

        $this->authenticate(1);
        $items = $this->activityItems('/api/activity?subject_type=cross_context_request');
        $this->assertSame(['request.sent'], array_column($items, 'action'));

        $this->authenticate(2);
        $items = $this->activityItems('/api/activity?subject_type=cross_context_request');
        $this->assertSame(['request.rejected'], array_column($items, 'action'));
    }

    public function testReadOnlyRequestsProduceNoActivity(): void
    {
        $this->authenticate();
        $this->get('/api/todos');
        $this->assertResponseOk();
        $this->get('/api/todos/10');
        $this->assertResponseOk();
        $this->get('/api/projects');
        $this->assertResponseOk();

        $this->assertSame([], $this->activityItems());
    }

    public function testActivityIsNotDuplicatedForSingleMutation(): void
    {
        $this->authenticate();
        $this->post('/api/todos', ['title' => 'Only once']);
        $this->assertResponseCode(201);

        $this->assertCount(1, $this->activityItems());
    }

    public function testActivityFromAnotherUserIsHidden(): void
    {
        $this->authenticate(2);
        $this->post('/api/todos', ['title' => 'Second user work']);
        $this->assertResponseCode(201);

        $this->authenticate(1);

        $this->assertSame([], $this->activityItems());
    }

    public function testPaginationLimitsResults(): void
    {
        $this->authenticate();
        foreach (['a', 'b', 'c'] as $title) {
            $this->post('/api/todos', ['title' => $title]);
            $this->assertResponseCode(201);
        }

        $this->get('/api/activity?page=1&limit=2');
        $this->assertResponseOk();
        $payload = $this->payload();

        $this->assertCount(2, $payload['data']['items']);
        $this->assertSame(3, $payload['meta']['pagination']['total']);
        $this->assertSame(2, $payload['meta']['pagination']['limit']);

        $this->get('/api/activity?page=2&limit=2');
        $this->assertResponseOk();
        $this->assertCount(1, $this->payload()['data']['items']);
    }

    public function testLimitIsCapped(): void
    {
        $this->authenticate();

        $this->get('/api/activity?limit=5000');

        $this->assertResponseOk();
        $this->assertSame(100, $this->payload()['meta']['pagination']['limit']);
    }

    public function testMalformedPageIsRejected(): void
    {
        $this->authenticate();

        $this->get('/api/activity?page=abc');

        $this->assertResponseCode(400);
    }

    public function testMalformedLimitIsRejected(): void
    {
        $this->authenticate();

        $this->get('/api/activity?limit=0');

        $this->assertResponseCode(400);
    }

    public function testUnknownSubjectTypeFilterIsRejected(): void
    {
        $this->authenticate();

        $this->get('/api/activity?subject_type=unicorn');

        $this->assertResponseCode(400);
    }

    public function testMalformedSubjectIdFilterIsRejected(): void
    {
        $this->authenticate();

        $this->get('/api/activity?subject_id=abc');

        $this->assertResponseCode(400);
    }

    public function testMissingSubjectStillListsHistoricalActivity(): void
    {
        $this->authenticate();
        $this->post('/api/todos', ['title' => 'Temporary']);
        $this->assertResponseCode(201);
        $todoId = $this->payload()['data']['todo']['id'];

        $this->post('/api/todos/' . $todoId . '/trash');
        $this->assertResponseOk();
        $this->delete('/api/todos/' . $todoId . '/permanent');
        $this->assertResponseOk();

        $items = $this->activityItems('/api/activity?subject_type=todo&subject_id=' . $todoId);

        $this->assertSame(['todo.deleted', 'todo.trashed', 'todo.created'], array_column($items, 'action'));
    }

    public function testActivityDoesNotExposeSensitiveMetadata(): void
    {
        $this->authenticate();
        $this->post('/api/todos', ['title' => 'Safe', 'password' => 'hunter2', 'token' => 'abc']);
        $this->assertResponseCode(201);

        $this->get('/api/activity');
        $this->assertResponseOk();

        $this->assertResponseNotContains('hunter2');
        $this->assertResponseNotContains('password');
    }
}
