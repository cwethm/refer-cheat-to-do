<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\CapabilityService;
use App\Service\CrossContextRequestService;
use App\Service\LibraryMembershipService;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use DomainException;

class CrossContextRequestServiceTest extends TestCase
{
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
        'app.Libraries',
        'app.LibrariesProjects',
        'app.LibrariesNotebooks',
        'app.CapabilityGrants',
        'app.CrossContextRequests',
    ];

    private CapabilityService $capabilities;
    private CrossContextRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $locator = TableRegistry::getTableLocator();
        $this->capabilities = new CapabilityService($locator);
        $this->service = new CrossContextRequestService($locator, $this->capabilities);
    }

    /**
     * Base payload sending from user 1's project into user 2's notebook.
     *
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

    private function allowSending(): void
    {
        $this->capabilities->grant(2, 1, CapabilityService::CAP_SEND_TASK, 'notebook', 401);
    }

    public function testOwnerCannotSendWithoutSendTaskCapability(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Not permitted to send requests');
        $this->service->send(1, $this->payload());
    }

    public function testSendRequiresSourceAuthority(): void
    {
        $this->capabilities->grant(1, 2, CapabilityService::CAP_SEND_TASK, 'notebook', 400);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Not permitted to act for the source context');
        $this->service->send(2, $this->payload(['target_id' => 400]));
    }

    public function testSendCreatesPendingRequest(): void
    {
        $this->allowSending();

        $request = $this->service->send(1, $this->payload());

        $this->assertSame(CrossContextRequestService::STATUS_PENDING, $request->status);
        $this->assertSame(1, (int)$request->created_by_user_id);
        $this->assertNull($request->resolved_at);
    }

    public function testSendRejectsSelfAddressedRequest(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cannot send a request to itself');
        $this->service->send(1, $this->payload(['target_type' => 'project', 'target_id' => 200]));
    }

    public function testSendRejectsUnknownContextType(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown context type.');
        $this->service->send(1, $this->payload(['target_type' => 'library']));
    }

    public function testSendRejectsUnknownRequestType(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown request type.');
        $this->service->send(1, $this->payload(['request_type' => 'command']));
    }

    public function testSendRejectsMissingTarget(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown target context.');
        $this->service->send(1, $this->payload(['target_id' => 9999]));
    }

    public function testSendRejectsMalformedPayload(): void
    {
        $this->allowSending();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid cross-context request.');
        $this->service->send(1, $this->payload(['title' => '']));
    }

    public function testInboxOnlyShowsRequestsTheActorMayResolve(): void
    {
        $this->allowSending();
        $this->service->send(1, $this->payload());

        $this->assertSame([], $this->service->inbox(1));
        $this->assertCount(1, $this->service->inbox(2));
        $this->assertSame([], $this->service->inbox(3));
    }

    public function testOutboxShowsSenderRequests(): void
    {
        $this->allowSending();
        $this->service->send(1, $this->payload());

        $this->assertCount(1, $this->service->outbox(1));
        $this->assertSame([], $this->service->outbox(2));
    }

    public function testSenderCanViewButNotResolve(): void
    {
        $this->allowSending();
        $request = $this->service->send(1, $this->payload());

        $this->assertSame((int)$request->id, (int)$this->service->viewable(1, (int)$request->id)->id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown cross-context request.');
        $this->service->reject(1, (int)$request->id);
    }

    public function testUnrelatedUserCannotView(): void
    {
        $this->allowSending();
        $request = $this->service->send(1, $this->payload());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown cross-context request.');
        $this->service->viewable(3, (int)$request->id);
    }

    public function testRecipientCanModifyPendingRequest(): void
    {
        $this->allowSending();
        $request = $this->service->send(1, $this->payload());

        $modified = $this->service->modify(2, (int)$request->id, ['title' => 'Reworded', 'body' => null]);

        $this->assertSame('Reworded', $modified->title);
        $this->assertNull($modified->body);
        $this->assertTrue($modified->isPending());
    }

    public function testModifyRejectsEmptyChangeSet(): void
    {
        $this->allowSending();
        $request = $this->service->send(1, $this->payload());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Nothing to modify.');
        $this->service->modify(2, (int)$request->id, []);
    }

    public function testAcceptCreatesTodoOwnedByTargetOwner(): void
    {
        $this->allowSending();
        $request = $this->service->send(1, $this->payload());

        $accepted = $this->service->accept(2, (int)$request->id, ['create_todo' => true]);

        $this->assertSame(CrossContextRequestService::STATUS_ACCEPTED, $accepted->status);
        $this->assertSame(2, (int)$accepted->resolved_by_user_id);
        $this->assertNotNull($accepted->resulting_todo_id);
        $todo = TableRegistry::getTableLocator()->get('Todos')->get((int)$accepted->resulting_todo_id);
        $this->assertSame(2, (int)$todo->get('user_id'));
        $this->assertSame('Please review the draft', (string)$todo->get('title'));
    }

    public function testAcceptCanLinkExistingTodoOwnedByTargetOwner(): void
    {
        $this->allowSending();
        $request = $this->service->send(1, $this->payload());

        $accepted = $this->service->accept(2, (int)$request->id, ['link_todo_id' => 12]);

        $this->assertSame(12, (int)$accepted->resulting_todo_id);
    }

    public function testAcceptRejectsLinkingAnotherUsersTodo(): void
    {
        $this->allowSending();
        $request = $this->service->send(1, $this->payload());

        try {
            $this->service->accept(2, (int)$request->id, ['link_todo_id' => 10]);
            $this->fail('Linking a foreign ToDo must fail.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Invalid linked ToDo', $exception->getMessage());
        }

        $reloaded = $this->service->viewable(2, (int)$request->id);
        $this->assertTrue($reloaded->isPending(), 'A failed acceptance must roll back to pending.');
        $this->assertNull($reloaded->resolved_at);
    }

    public function testAcceptWithoutTodoLeavesNoResultingTodo(): void
    {
        $this->allowSending();
        $request = $this->service->send(1, $this->payload());

        $accepted = $this->service->accept(2, (int)$request->id, ['resolution_note' => 'noted']);

        $this->assertNull($accepted->resulting_todo_id);
        $this->assertSame('noted', $accepted->resolution_note);
    }

    public function testRejectResolvesRequest(): void
    {
        $this->allowSending();
        $request = $this->service->send(1, $this->payload());

        $rejected = $this->service->reject(2, (int)$request->id, 'not now');

        $this->assertSame(CrossContextRequestService::STATUS_REJECTED, $rejected->status);
        $this->assertSame('not now', $rejected->resolution_note);
        $this->assertNotNull($rejected->resolved_at);
    }

    public function testDoubleResolutionIsRejected(): void
    {
        $this->allowSending();
        $request = $this->service->send(1, $this->payload());
        $this->service->reject(2, (int)$request->id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('already resolved');
        $this->service->accept(2, (int)$request->id);
    }

    public function testModifyAfterResolutionIsRejected(): void
    {
        $this->allowSending();
        $request = $this->service->send(1, $this->payload());
        $this->service->accept(2, (int)$request->id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('already resolved');
        $this->service->modify(2, (int)$request->id, ['title' => 'too late']);
    }

    public function testRevokedCapabilityBlocksResolutionAfterSending(): void
    {
        $this->allowSending();
        $request = $this->service->send(1, $this->payload());
        $grant = $this->capabilities->grant(2, 3, CapabilityService::CAP_CONTRIBUTE, 'notebook', 401);
        $this->assertTrue($this->service->canResolve(3, $request));

        $this->capabilities->revoke(2, (int)$grant->id);

        $this->assertFalse($this->service->canResolve(3, $request));
        $this->expectException(DomainException::class);
        $this->service->accept(3, (int)$request->id);
    }

    public function testCallbackRequiresResolution(): void
    {
        $this->allowSending();
        $request = $this->service->send(1, $this->payload());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('must be resolved before a callback');
        $this->service->callback(2, (int)$request->id, 'done');
    }

    public function testCallbackRequiresCallbackCapabilityOnSource(): void
    {
        $this->allowSending();
        $request = $this->service->send(1, $this->payload());
        $this->service->accept(2, (int)$request->id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Not permitted to call back');
        $this->service->callback(2, (int)$request->id, 'done');
    }

    public function testCallbackIsRecordedOnce(): void
    {
        $this->allowSending();
        $this->capabilities->grant(1, 2, CapabilityService::CAP_CALLBACK, 'project', 200);
        $request = $this->service->send(1, $this->payload());
        $this->service->accept(2, (int)$request->id);

        $answered = $this->service->callback(2, (int)$request->id, 'draft reviewed');
        $this->assertSame('draft reviewed', $answered->callback_summary);
        $this->assertNotNull($answered->callback_at);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('already has a callback');
        $this->service->callback(2, (int)$request->id, 'again');
    }

    public function testCallbackRejectsEmptySummary(): void
    {
        $this->allowSending();
        $this->capabilities->grant(1, 2, CapabilityService::CAP_CALLBACK, 'project', 200);
        $request = $this->service->send(1, $this->payload());
        $this->service->accept(2, (int)$request->id);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid callback summary.');
        $this->service->callback(2, (int)$request->id, '');
    }

    public function testSendTaskDoesNotGrantEditOrContributeOnTarget(): void
    {
        $this->allowSending();

        $denied = [
            CapabilityService::CAP_EDIT,
            CapabilityService::CAP_CONTRIBUTE,
            CapabilityService::CAP_DELETE,
        ];
        foreach ($denied as $cap) {
            $this->assertFalse($this->capabilities->allows(1, $cap, 'notebook', 401));
        }
    }

    public function testLibraryMembershipDoesNotEnableSending(): void
    {
        $libraries = TableRegistry::getTableLocator()->get('Libraries');
        $membership = new LibraryMembershipService(TableRegistry::getTableLocator());
        $membership->addMember($libraries->get(600), 'project', 200);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Not permitted to send requests');
        $this->service->send(1, $this->payload());
    }

    public function testDatabaseRejectsUnknownStatus(): void
    {
        $this->expectExceptionMessageMatches('/status/');
        TableRegistry::getTableLocator()->get('CrossContextRequests')->getConnection()->execute(
            'INSERT INTO cross_context_requests (source_type, source_id, target_type, target_id, request_type, '
            . "title, status, created_by_user_id) VALUES ('project', 200, 'notebook', 401, 'task', 't', 'done', 1)",
        );
    }

    public function testDatabaseRejectsResolvedRowWithoutResolver(): void
    {
        $this->expectExceptionMessageMatches('/resolution/');
        TableRegistry::getTableLocator()->get('CrossContextRequests')->getConnection()->execute(
            'INSERT INTO cross_context_requests (source_type, source_id, target_type, target_id, request_type, '
            . "title, status, created_by_user_id) VALUES ('project', 200, 'notebook', 401, 'task', 't', "
            . "'accepted', 1)",
        );
    }
}
