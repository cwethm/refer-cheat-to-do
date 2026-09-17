<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * MVP release verification — destructive-action and data-integrity matrices.
 *
 * These assertions sit above individual slices: they prove that deleting or unlinking one object
 * never destroys a different object, and that restoring keeps the relationships it had.
 */
class MvpReleaseVerificationTest extends TestCase
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
    private function data(): array
    {
        $body = json_decode((string)$this->_response?->getBody(), true);
        $this->assertIsArray($body);

        return $body['data'];
    }

    public function testDeletingTagDoesNotDeleteTaggedTodos(): void
    {
        $this->authenticate();

        $this->delete('/api/tags/100');
        $this->assertResponseOk();

        $this->get('/api/todos/10');
        $this->assertResponseOk();
        $this->assertSame([], $this->data()['todo']['tags']);
    }

    public function testRemovingRelationshipDeletesNeitherTodo(): void
    {
        $this->authenticate();
        $this->post('/api/todos/10/related/11');
        $this->assertResponseCode(201);

        $this->delete('/api/todos/10/related/11');
        $this->assertResponseOk();

        $this->get('/api/todos/10');
        $this->assertResponseOk();
        $this->get('/api/todos/11');
        $this->assertResponseOk();
    }

    public function testRestoringATodoRetainsItsRelationships(): void
    {
        $this->authenticate();
        $this->patch('/api/todos/10', ['project_section_id' => 300]);
        $this->assertResponseOk();

        $this->post('/api/todos/10/trash');
        $this->assertResponseOk();
        $this->post('/api/todos/10/restore');
        $this->assertResponseOk();

        $this->get('/api/todos/10');
        $this->assertResponseOk();
        $todo = $this->data()['todo'];
        $this->assertSame([100], array_column($todo['tags'], 'id'));
        $this->assertSame(300, $todo['project_section_id']);
    }

    public function testPermanentDeletionRequiresTrashFirst(): void
    {
        $this->authenticate();

        $this->delete('/api/todos/10/permanent');
        $this->assertResponseCode(409);

        $this->get('/api/todos/10');
        $this->assertResponseOk();
    }

    public function testPermanentlyDeletingATodoDoesNotDeleteItsTagOrRelatives(): void
    {
        $this->authenticate();
        $this->post('/api/todos/10/related/11');
        $this->assertResponseCode(201);

        $this->post('/api/todos/10/trash');
        $this->assertResponseOk();
        $this->delete('/api/todos/10/permanent');
        $this->assertResponseOk();

        $this->get('/api/tags');
        $this->assertResponseOk();
        $this->assertContains(100, array_column($this->data()['items'], 'id'));

        $this->get('/api/todos/11');
        $this->assertResponseOk();
    }

    public function testDuplicateLibraryMembershipIsRejected(): void
    {
        $this->authenticate();
        $this->post('/api/libraries/600/members/project/200');
        $this->assertResponseCode(201);

        $this->post('/api/libraries/600/members/project/200');
        $this->assertResponseCode(409);
    }

    public function testDuplicateTagAttachmentIsRejected(): void
    {
        $this->authenticate();

        $this->post('/api/todos/10/tags/100');
        $this->assertResponseCode(409);
    }

    public function testParentCyclesAreRejected(): void
    {
        $this->authenticate();
        $this->patch('/api/todos/11/parent', ['parent_todo_id' => 10]);
        $this->assertResponseOk();

        $this->patch('/api/todos/10/parent', ['parent_todo_id' => 11]);
        $this->assertResponseCode(409);
    }

    public function testCrossUserOwnershipReferencesAreRejected(): void
    {
        $this->authenticate();

        $this->post('/api/todos/10/tags/102');
        $this->assertResponseCode(404);

        $this->post('/api/todos/10/related/12');
        $this->assertResponseCode(404);

        $this->post('/api/libraries/600/members/project/201');
        $this->assertResponseCode(404);
    }
}
