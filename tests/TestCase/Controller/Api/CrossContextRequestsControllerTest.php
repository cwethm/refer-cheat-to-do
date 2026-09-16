<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use App\Service\CapabilityService;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class CrossContextRequestsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Projects',
        'app.ProjectSections',
        'app.Notebooks',
        'app.NotebookSections',
        'app.Todos',
        'app.Libraries',
        'app.LibrariesProjects',
        'app.LibrariesNotebooks',
        'app.CapabilityGrants',
        'app.CrossContextRequests',
    ];

    private function authenticate(int $userId = 1): void
    {
        $this->session(['Auth.user_id' => $userId]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    private function capabilities(): CapabilityService
    {
        return new CapabilityService(TableRegistry::getTableLocator());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'source_type' => 'project',
            'source_id' => 200,
            'target_type' => 'notebook',
            'target_id' => 401,
            'request_type' => 'task',
            'title' => 'Please review the draft',
            'body' => 'Details here',
        ];
    }

    private function sendRequest(): int
    {
        $this->capabilities()->grant(2, 1, CapabilityService::CAP_SEND_TASK, 'notebook', 401);
        $this->authenticate();
        $this->post('/api/cross-context-requests', $this->payload());
        $this->assertResponseCode(201);

        $requests = TableRegistry::getTableLocator()->get('CrossContextRequests');

        return (int)$requests->find()->orderBy(['id' => 'DESC'])->firstOrFail()->get('id');
    }

    public function testAnonymousCannotSend(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->post('/api/cross-context-requests', $this->payload());

        $this->assertResponseCode(401);
    }

    public function testAnonymousCannotReadInbox(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->get('/api/cross-context-requests/inbox');

        $this->assertResponseCode(401);
    }

    public function testSendWithoutSendTaskCapabilityIsDenied(): void
    {
        $this->authenticate();

        $this->post('/api/cross-context-requests', $this->payload());

        $this->assertResponseCode(404);
        $requests = TableRegistry::getTableLocator()->get('CrossContextRequests');
        $this->assertSame(0, $requests->find()->count());
    }

    public function testSendCreatesPendingRequest(): void
    {
        $id = $this->sendRequest();

        $this->assertGreaterThan(0, $id);
        $this->authenticate();
        $this->get('/api/cross-context-requests/outbox');
        $this->assertResponseOk();
        $this->assertResponseContains('"status": "pending"');
    }

    public function testStatusCannotBeMassAssignedOnSend(): void
    {
        $this->capabilities()->grant(2, 1, CapabilityService::CAP_SEND_TASK, 'notebook', 401);
        $this->authenticate();

        $this->post('/api/cross-context-requests', $this->payload([
            'status' => 'accepted',
            'created_by_user_id' => 2,
            'resulting_todo_id' => 12,
        ]));

        $this->assertResponseCode(201);
        $requests = TableRegistry::getTableLocator()->get('CrossContextRequests');
        $request = $requests->find()->orderBy(['id' => 'DESC'])->firstOrFail();
        $this->assertSame('pending', (string)$request->get('status'));
        $this->assertSame(1, (int)$request->get('created_by_user_id'));
        $this->assertNull($request->get('resulting_todo_id'));
    }

    public function testMalformedPayloadIsRejected(): void
    {
        $this->capabilities()->grant(2, 1, CapabilityService::CAP_SEND_TASK, 'notebook', 401);
        $this->authenticate();

        $this->post('/api/cross-context-requests', $this->payload(['title' => '']));

        $this->assertResponseCode(400);
    }

    public function testInvalidContextTypeIsRejected(): void
    {
        $this->authenticate();

        $this->post('/api/cross-context-requests', $this->payload(['target_type' => 'library']));

        $this->assertResponseCode(404);
    }

    public function testRecipientSeesRequestInInbox(): void
    {
        $id = $this->sendRequest();

        $this->authenticate(2);
        $this->get('/api/cross-context-requests/inbox');

        $this->assertResponseOk();
        $this->assertResponseContains('"id": ' . $id);
    }

    public function testSenderInboxIsEmpty(): void
    {
        $this->sendRequest();

        $this->authenticate();
        $this->get('/api/cross-context-requests/inbox');

        $this->assertResponseOk();
        $this->assertResponseContains('"items": []');
    }

    public function testUnrelatedUserCannotViewRequest(): void
    {
        $id = $this->sendRequest();

        $this->authenticate(3);
        $this->get('/api/cross-context-requests/' . $id);

        $this->assertResponseCode(404);
    }

    public function testSenderCannotAccept(): void
    {
        $id = $this->sendRequest();

        $this->authenticate();
        $this->post('/api/cross-context-requests/' . $id . '/accept', ['create_todo' => true]);

        $this->assertResponseCode(404);
    }

    public function testRecipientCanModifyThenAcceptCreatingTodo(): void
    {
        $id = $this->sendRequest();

        $this->authenticate(2);
        $this->patch('/api/cross-context-requests/' . $id, ['title' => 'Reviewed draft']);
        $this->assertResponseOk();

        $this->authenticate(2);
        $this->post('/api/cross-context-requests/' . $id . '/accept', ['create_todo' => true]);
        $this->assertResponseOk();
        $this->assertResponseContains('"status": "accepted"');

        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->find()->where(['title' => 'Reviewed draft'])->firstOrFail();
        $this->assertSame(2, (int)$todo->get('user_id'));
    }

    public function testRecipientCanLinkExistingTodo(): void
    {
        $id = $this->sendRequest();

        $this->authenticate(2);
        $this->post('/api/cross-context-requests/' . $id . '/accept', ['link_todo_id' => 12]);

        $this->assertResponseOk();
        $this->assertResponseContains('"resulting_todo_id": 12');
    }

    public function testLinkingAnotherUsersTodoIsRejectedAndRollsBack(): void
    {
        $id = $this->sendRequest();

        $this->authenticate(2);
        $this->post('/api/cross-context-requests/' . $id . '/accept', ['link_todo_id' => 10]);

        $this->assertResponseCode(400);
        $requests = TableRegistry::getTableLocator()->get('CrossContextRequests');
        $this->assertSame('pending', (string)$requests->get($id)->get('status'));
    }

    public function testRecipientCanReject(): void
    {
        $id = $this->sendRequest();

        $this->authenticate(2);
        $this->post('/api/cross-context-requests/' . $id . '/reject', ['resolution_note' => 'not now']);

        $this->assertResponseOk();
        $this->assertResponseContains('"status": "rejected"');
    }

    public function testDoubleResolutionConflicts(): void
    {
        $id = $this->sendRequest();

        $this->authenticate(2);
        $this->post('/api/cross-context-requests/' . $id . '/reject');
        $this->assertResponseOk();

        $this->authenticate(2);
        $this->post('/api/cross-context-requests/' . $id . '/accept');
        $this->assertResponseCode(409);
    }

    public function testCallbackRequiresCallbackCapability(): void
    {
        $id = $this->sendRequest();
        $this->authenticate(2);
        $this->post('/api/cross-context-requests/' . $id . '/accept');
        $this->assertResponseOk();

        $this->authenticate(2);
        $this->post('/api/cross-context-requests/' . $id . '/callback', ['summary' => 'done']);
        $this->assertResponseCode(404);

        $this->capabilities()->grant(1, 2, CapabilityService::CAP_CALLBACK, 'project', 200);
        $this->authenticate(2);
        $this->post('/api/cross-context-requests/' . $id . '/callback', ['summary' => 'done']);
        $this->assertResponseOk();
        $this->assertResponseContains('"callback_summary": "done"');
    }

    public function testSendTaskDoesNotAllowDirectEditOfTargetContent(): void
    {
        $this->sendRequest();

        $this->authenticate();
        $this->patch('/api/notebooks/401', ['name' => 'Hijacked']);

        $this->assertResponseCode(404);
        $notebooks = TableRegistry::getTableLocator()->get('Notebooks');
        $this->assertSame('Other user notebook', (string)$notebooks->get(401)->name);
    }

    public function testSendTaskDoesNotAllowReadingTargetContext(): void
    {
        $this->sendRequest();

        $this->authenticate();
        $this->get('/api/notebooks/401');

        $this->assertResponseCode(404);
    }

    public function testAcceptanceDoesNotGrantOngoingEditAuthority(): void
    {
        $id = $this->sendRequest();
        $this->authenticate(2);
        $this->post('/api/cross-context-requests/' . $id . '/accept', ['create_todo' => true]);
        $this->assertResponseOk();

        $this->authenticate();
        $this->patch('/api/notebooks/401', ['name' => 'Hijacked']);
        $this->assertResponseCode(404);
        $this->assertFalse($this->capabilities()->allows(1, 'edit', 'notebook', 401));
    }

    public function testRevokedCapabilityBlocksResolution(): void
    {
        $id = $this->sendRequest();
        $grant = TableRegistry::getTableLocator()->get('CapabilityGrants')
            ->find()
            ->where(['capability' => 'send_task'])
            ->firstOrFail();
        $this->capabilities()->revoke(2, (int)$grant->get('id'));

        $this->authenticate();
        $this->post('/api/cross-context-requests', $this->payload());
        $this->assertResponseCode(404);

        $requests = TableRegistry::getTableLocator()->get('CrossContextRequests');
        $this->assertSame('pending', (string)$requests->get($id)->get('status'));
    }

    public function testUnknownRequestIsNotFound(): void
    {
        $this->authenticate();

        $this->get('/api/cross-context-requests/9999');

        $this->assertResponseCode(404);
        $this->assertResponseContains('"error"');
    }
}
