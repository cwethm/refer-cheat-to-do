<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * End-to-end MVP journeys and the MVP authorization sweep.
 *
 * These tests exercise the critical user journeys defined by the Slice 13 test plan across the
 * whole API surface rather than any single slice.
 */
class MvpJourneyTest extends TestCase
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
     * @return array<string, mixed>
     */
    private function data(): array
    {
        return $this->payload()['data'];
    }

    public function testJourneyACaptureAndRediscover(): void
    {
        $this->authenticate();

        $this->post('/api/todos', ['title' => 'Draft the release notes']);
        $this->assertResponseCode(201);
        $todoId = $this->data()['todo']['id'];

        $this->post('/api/tags', ['name' => 'Release Queue']);
        $this->assertResponseCode(201);
        $tagId = $this->data()['tag']['id'];

        $this->post('/api/todos/' . $todoId . '/tags/' . $tagId);
        $this->assertResponseCode(201);

        $this->get('/api/todos?tag=' . $tagId);
        $this->assertResponseOk();
        $this->assertSame([$todoId], array_column($this->data()['items'], 'id'));

        $this->get('/api/todos?q=release notes');
        $this->assertResponseOk();
        $this->assertContains($todoId, array_column($this->data()['items'], 'id'));

        $this->get('/api/todos/' . $todoId);
        $this->assertResponseOk();
        $this->assertSame(['Release Queue'], array_column($this->data()['todo']['tags'], 'name'));
    }

    public function testJourneyBOrganize(): void
    {
        $this->authenticate();

        $this->post('/api/projects', ['name' => 'Launch']);
        $this->assertResponseCode(201);
        $projectId = $this->data()['project']['id'];

        $this->post('/api/projects/' . $projectId . '/sections', ['name' => 'Prep']);
        $this->assertResponseCode(201);
        $projectSectionId = $this->data()['section']['id'];

        $this->post('/api/todos', ['title' => 'Book the venue']);
        $this->assertResponseCode(201);
        $todoId = $this->data()['todo']['id'];

        $this->patch('/api/todos/' . $todoId, ['project_section_id' => $projectSectionId]);
        $this->assertResponseOk();

        $this->post('/api/notebooks', ['name' => 'Retro']);
        $this->assertResponseCode(201);
        $notebookId = $this->data()['notebook']['id'];

        $this->post('/api/notebooks/' . $notebookId . '/sections', ['name' => 'Notes']);
        $this->assertResponseCode(201);
        $notebookSectionId = $this->data()['section']['id'];

        $this->patch('/api/todos/' . $todoId, ['notebook_section_id' => $notebookSectionId]);
        $this->assertResponseCode(409);

        $this->get('/api/todos?project=' . $projectId);
        $this->assertResponseOk();
        $this->assertSame([$todoId], array_column($this->data()['items'], 'id'));

        $this->get('/api/todos?notebook=' . $notebookId);
        $this->assertResponseOk();
        $this->assertSame([], $this->data()['items']);
    }

    public function testJourneyCReview(): void
    {
        $this->authenticate();
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todos->updateAll(['next_review_at' => new DateTime('2000-01-01 00:00:00')], ['id' => 10]);

        $this->get('/api/dashboard');
        $this->assertResponseOk();
        $this->assertSame(1, $this->data()['counts']['review_due']);

        $this->get('/api/todos/review-queue?limit=100');
        $this->assertResponseOk();
        $this->assertContains(10, array_column(array_column($this->data()['items'], 'todo'), 'id'));

        $this->post('/api/todos/10/snooze', ['days' => 7]);
        $this->assertResponseOk();

        $this->get('/api/dashboard');
        $this->assertResponseOk();
        $this->assertSame(0, $this->data()['counts']['review_due']);

        $todos->updateAll(['next_review_at' => new DateTime('-1 day')], ['id' => 10]);

        $this->get('/api/dashboard');
        $this->assertResponseOk();
        $this->assertSame(1, $this->data()['counts']['review_due']);
    }

    public function testJourneyDLifecycle(): void
    {
        $this->authenticate();
        $this->post('/api/todos', ['title' => 'Temporary item']);
        $this->assertResponseCode(201);
        $todoId = $this->data()['todo']['id'];

        $this->post('/api/todos/' . $todoId . '/archive');
        $this->assertResponseOk();

        $this->get('/api/todos');
        $this->assertResponseOk();
        $this->assertNotContains($todoId, array_column($this->data()['items'], 'id'));

        $this->post('/api/todos/' . $todoId . '/activate');
        $this->assertResponseOk();

        $this->get('/api/todos');
        $this->assertResponseOk();
        $this->assertContains($todoId, array_column($this->data()['items'], 'id'));

        $this->post('/api/todos/' . $todoId . '/trash');
        $this->assertResponseOk();

        $this->get('/api/todos');
        $this->assertResponseOk();
        $this->assertNotContains($todoId, array_column($this->data()['items'], 'id'));

        $this->post('/api/todos/' . $todoId . '/restore');
        $this->assertResponseOk();

        $this->delete('/api/todos/' . $todoId . '/permanent');
        $this->assertResponseCode(409);

        $this->post('/api/todos/' . $todoId . '/trash');
        $this->assertResponseOk();

        $this->delete('/api/todos/' . $todoId . '/permanent');
        $this->assertResponseOk();

        $this->get('/api/todos/' . $todoId);
        $this->assertResponseCode(404);

        $this->delete('/api/todos/' . $todoId . '/permanent');
        $this->assertResponseCode(404);
    }

    public function testJourneyELibrarySafety(): void
    {
        $this->authenticate();
        $this->post('/api/libraries', ['name' => 'Shared context']);
        $this->assertResponseCode(201);
        $libraryId = $this->data()['library']['id'];

        $this->post('/api/libraries/' . $libraryId . '/members/project/200');
        $this->assertResponseCode(201);
        $this->post('/api/libraries/' . $libraryId . '/members/notebook/400');
        $this->assertResponseCode(201);

        $this->get('/api/libraries/' . $libraryId . '/members');
        $this->assertResponseOk();
        $members = $this->data();
        $this->assertSame([200], array_column($members['projects'], 'id'));
        $this->assertSame([400], array_column($members['notebooks'], 'id'));

        // A second user who is not the library owner gains nothing from membership.
        $this->authenticate(2);
        $this->get('/api/libraries/' . $libraryId);
        $this->assertResponseCode(404);
        $this->patch('/api/projects/200', ['name' => 'Hijacked']);
        $this->assertResponseCode(404);
        $this->delete('/api/notebooks/400');
        $this->assertResponseCode(404);

        // Removing membership and deleting the library never destroys the members.
        $this->authenticate();
        $this->delete('/api/libraries/' . $libraryId . '/members/project/200');
        $this->assertResponseOk();
        $this->delete('/api/libraries/' . $libraryId . '/members/notebook/400');
        $this->assertResponseOk();
        $this->delete('/api/libraries/' . $libraryId);
        $this->assertResponseOk();

        $this->get('/api/projects/200');
        $this->assertResponseOk();
        $this->get('/api/notebooks/400');
        $this->assertResponseOk();
    }

    public function testJourneyFCrossContextRequest(): void
    {
        // User two grants user one permission to send tasks to their notebook.
        $this->authenticate(2);
        $this->post('/api/capabilities', [
            'subject_user_id' => 1,
            'resource_type' => 'notebook',
            'resource_id' => 401,
            'capability' => 'send_task',
        ]);
        $this->assertResponseCode(201);
        $grantId = $this->data()['grant']['id'];

        $this->authenticate();
        $this->post('/api/cross-context-requests', [
            'source_type' => 'project',
            'source_id' => 200,
            'target_type' => 'notebook',
            'target_id' => 401,
            'request_type' => 'task',
            'title' => 'Please summarize the launch risks',
        ]);
        $this->assertResponseCode(201);
        $requestId = $this->data()['request']['id'];

        $this->authenticate(2);
        $this->get('/api/cross-context-requests/inbox');
        $this->assertResponseOk();
        $this->assertContains($requestId, array_column($this->data()['items'], 'id'));

        $this->post('/api/cross-context-requests/' . $requestId . '/accept', ['create_todo' => true]);
        $this->assertResponseOk();
        $request = $this->data()['request'];
        $this->assertSame('accepted', $request['status']);
        $this->assertNotNull($request['resulting_todo_id']);

        // The created ToDo belongs to the target owner, not the sender.
        $this->get('/api/todos/' . $request['resulting_todo_id']);
        $this->assertResponseOk();
        $this->authenticate();
        $this->get('/api/todos/' . $request['resulting_todo_id']);
        $this->assertResponseCode(404);

        $this->authenticate();
        $this->post('/api/capabilities', [
            'subject_user_id' => 2,
            'resource_type' => 'project',
            'resource_id' => 200,
            'capability' => 'callback',
        ]);
        $this->assertResponseCode(201);

        $this->authenticate(2);
        $this->post('/api/cross-context-requests/' . $requestId . '/callback', ['summary' => 'Summarized']);
        $this->assertResponseOk();

        $this->authenticate();
        $this->get('/api/cross-context-requests/' . $requestId);
        $this->assertResponseOk();
        $this->assertSame('Summarized', $this->data()['request']['callback_summary']);

        $this->get('/api/activity?subject_type=cross_context_request');
        $this->assertResponseOk();
        $this->assertSame(['request.sent'], array_column($this->data()['items'], 'action'));

        // Once the grant is revoked the sender can no longer send into that context.
        $this->authenticate(2);
        $this->delete('/api/capabilities/' . $grantId);
        $this->assertResponseOk();

        $this->authenticate();
        $this->post('/api/cross-context-requests', [
            'source_type' => 'project',
            'source_id' => 200,
            'target_type' => 'notebook',
            'target_id' => 401,
            'request_type' => 'task',
            'title' => 'Second attempt',
        ]);
        $this->assertResponseCode(404);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function crossUserResourceProvider(): array
    {
        return [
            ['GET', '/api/todos/12'],
            ['PATCH', '/api/todos/12'],
            ['GET', '/api/projects/201'],
            ['PATCH', '/api/projects/201'],
            ['DELETE', '/api/projects/201'],
            ['GET', '/api/notebooks/401'],
            ['PATCH', '/api/notebooks/401'],
            ['DELETE', '/api/notebooks/401'],
            ['GET', '/api/libraries/602'],
            ['PATCH', '/api/libraries/602'],
            ['DELETE', '/api/libraries/602'],
            ['GET', '/api/libraries/602/members'],
        ];
    }

    #[DataProvider('crossUserResourceProvider')]
    public function testCrossUserResourceAccessIsDenied(string $method, string $url): void
    {
        $this->authenticate();

        match ($method) {
            'GET' => $this->get($url),
            'PATCH' => $this->patch($url, ['name' => 'Hijacked', 'title' => 'Hijacked']),
            'DELETE' => $this->delete($url),
            default => $this->fail('Unsupported method.'),
        };

        $this->assertResponseCode(404);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function protectedEndpointProvider(): array
    {
        return [
            ['/api/todos'],
            ['/api/tags'],
            ['/api/projects'],
            ['/api/notebooks'],
            ['/api/libraries'],
            ['/api/cross-context-requests/inbox'],
            ['/api/activity'],
            ['/api/dashboard'],
            ['/api/todos/review-queue'],
        ];
    }

    #[DataProvider('protectedEndpointProvider')]
    public function testAnonymousAccessIsDenied(string $url): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->get($url);

        $this->assertResponseCode(401);
    }

    public function testOwnershipCannotBeReassignedThroughUpdateEndpoints(): void
    {
        $this->authenticate();

        $this->patch('/api/todos/10', ['user_id' => 2]);
        $this->assertResponseOk();
        $this->patch('/api/projects/200', ['user_id' => 2]);
        $this->assertResponseOk();
        $this->patch('/api/notebooks/400', ['user_id' => 2]);
        $this->assertResponseOk();
        $this->patch('/api/libraries/600', ['user_id' => 2]);
        $this->assertResponseOk();

        $this->get('/api/todos/10');
        $this->assertResponseOk();
        $this->get('/api/projects/200');
        $this->assertResponseOk();
        $this->get('/api/notebooks/400');
        $this->assertResponseOk();
        $this->get('/api/libraries/600');
        $this->assertResponseOk();
    }

    public function testPermanentDeletionCannotBeReachedThroughUpdateEndpoint(): void
    {
        $this->authenticate();

        $this->patch('/api/todos/10', ['status' => 'trashed']);
        $this->assertResponseCode(409);

        $this->get('/api/todos/10');
        $this->assertResponseOk();
    }

    public function testCapabilityEscalationIsRejected(): void
    {
        $this->authenticate();

        // Granting yourself capabilities on someone else's resource is not possible.
        $this->post('/api/capabilities', [
            'subject_user_id' => 1,
            'resource_type' => 'notebook',
            'resource_id' => 401,
            'capability' => 'manage_permissions',
        ]);
        $this->assertResponseCode(404);

        $this->get('/api/notebooks/401');
        $this->assertResponseCode(404);
    }

    public function testCrossContextMutationWithoutCapabilityIsRejected(): void
    {
        $this->authenticate();

        $this->post('/api/cross-context-requests', [
            'source_type' => 'project',
            'source_id' => 200,
            'target_type' => 'notebook',
            'target_id' => 401,
            'request_type' => 'task',
            'title' => 'No capability',
        ]);

        $this->assertResponseCode(404);
    }

    public function testApiPayloadsDoNotLeakCredentials(): void
    {
        $this->authenticate();

        foreach (['/api/todos', '/api/projects', '/api/notebooks', '/api/libraries', '/api/dashboard'] as $url) {
            $this->get($url);
            $this->assertResponseOk();
            $this->assertResponseNotContains('password');
            $this->assertResponseNotContains('password_hash');
        }
    }

    public function testInvalidPayloadsAreRejectedConsistently(): void
    {
        $this->authenticate();

        $this->post('/api/todos', ['title' => '']);
        $this->assertResponseCode(422);

        $this->post('/api/projects', ['name' => '']);
        $this->assertResponseCode(422);

        $this->post('/api/notebooks', ['name' => '']);
        $this->assertResponseCode(422);

        $this->post('/api/libraries', ['name' => '']);
        $this->assertResponseCode(422);
    }

    public function testErrorEnvelopeIsConsistent(): void
    {
        $this->authenticate();

        $this->get('/api/todos/999999');

        $this->assertResponseCode(404);
        $payload = $this->payload();
        $this->assertArrayHasKey('error', $payload);
        $this->assertArrayHasKey('code', $payload['error']);
        $this->assertArrayHasKey('message', $payload['error']);
    }

    public function testPaginationIsConsistentAcrossCollections(): void
    {
        $this->authenticate();

        foreach (['/api/todos', '/api/projects', '/api/notebooks', '/api/libraries', '/api/activity'] as $url) {
            $this->get($url . '?page=1&limit=1');
            $this->assertResponseOk();
            $meta = $this->payload()['meta']['pagination'];
            $this->assertSame(1, $meta['page']);
            $this->assertSame(1, $meta['limit']);
            $this->assertArrayHasKey('total', $meta);
        }
    }

    public function testCollectionsAreNotUnbounded(): void
    {
        $this->authenticate();

        foreach (['/api/todos', '/api/projects', '/api/notebooks', '/api/libraries', '/api/activity'] as $url) {
            $this->get($url . '?limit=100000');
            $this->assertResponseOk();
            $this->assertLessThanOrEqual(100, $this->payload()['meta']['pagination']['limit']);
        }
    }
}
