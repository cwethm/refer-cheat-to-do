<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use App\Service\CapabilityService;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class CapabilitiesControllerTest extends TestCase
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
    ];

    private function authenticate(int $userId = 1): void
    {
        $this->session(['Auth.user_id' => $userId]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    private function service(): CapabilityService
    {
        return new CapabilityService(TableRegistry::getTableLocator());
    }

    public function testAnonymousCannotListGrants(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->get('/api/capabilities?resource_type=project&resource_id=200');

        $this->assertResponseCode(401);
    }

    public function testAnonymousCannotCreateGrant(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->post('/api/capabilities', [
            'subject_user_id' => 2,
            'resource_type' => 'project',
            'resource_id' => 200,
            'capability' => 'read',
        ]);

        $this->assertResponseCode(401);
        $this->assertFalse($this->service()->allows(2, 'read', 'project', 200));
    }

    public function testOwnerCanGrantAndList(): void
    {
        $this->authenticate();

        $this->post('/api/capabilities', [
            'subject_user_id' => 2,
            'resource_type' => 'project',
            'resource_id' => 200,
            'capability' => 'read',
        ]);
        $this->assertResponseCode(201);
        $this->assertResponseContains('"capability": "read"');

        $this->get('/api/capabilities?resource_type=project&resource_id=200');
        $this->assertResponseOk();
        $this->assertResponseContains('"subject_user_id": 2');
    }

    public function testGrantorIsTakenFromSessionNotPayload(): void
    {
        $this->authenticate();

        $this->post('/api/capabilities', [
            'subject_user_id' => 2,
            'resource_type' => 'project',
            'resource_id' => 200,
            'capability' => 'read',
            'grantor_user_id' => 2,
            'revoked_at' => '2020-01-01 00:00:00',
        ]);

        $this->assertResponseCode(201);
        $grants = TableRegistry::getTableLocator()->get('CapabilityGrants');
        $grant = $grants->find()->where(['capability' => 'read'])->firstOrFail();
        $this->assertSame(1, (int)$grant->grantor_user_id);
        $this->assertNull($grant->get('revoked_at'));
    }

    public function testDuplicateGrantConflicts(): void
    {
        $this->authenticate();
        $payload = [
            'subject_user_id' => 2,
            'resource_type' => 'project',
            'resource_id' => 200,
            'capability' => 'read',
        ];

        $this->post('/api/capabilities', $payload);
        $this->assertResponseCode(201);

        $this->post('/api/capabilities', $payload);
        $this->assertResponseCode(409);
    }

    public function testUnknownCapabilityIsNotFound(): void
    {
        $this->authenticate();

        $this->post('/api/capabilities', [
            'subject_user_id' => 2,
            'resource_type' => 'project',
            'resource_id' => 200,
            'capability' => 'superuser',
        ]);

        $this->assertResponseCode(404);
    }

    public function testUnknownResourceTypeIsNotFound(): void
    {
        $this->authenticate();

        $this->post('/api/capabilities', [
            'subject_user_id' => 2,
            'resource_type' => 'todo',
            'resource_id' => 10,
            'capability' => 'read',
        ]);

        $this->assertResponseCode(404);
    }

    public function testMissingFieldsAreBadRequests(): void
    {
        $this->authenticate();

        $this->post('/api/capabilities', ['resource_type' => 'project', 'capability' => 'read']);

        $this->assertResponseCode(400);
    }

    public function testListRejectsInvalidResourceId(): void
    {
        $this->authenticate();

        $this->get('/api/capabilities?resource_type=project&resource_id=abc');

        $this->assertResponseCode(400);
    }

    public function testListRejectsUnknownResourceType(): void
    {
        $this->authenticate();

        $this->get('/api/capabilities?resource_type=todo&resource_id=10');

        $this->assertResponseCode(400);
    }

    public function testNonOwnerCannotGrantOnAnotherUsersResource(): void
    {
        $this->authenticate(2);

        $this->post('/api/capabilities', [
            'subject_user_id' => 3,
            'resource_type' => 'project',
            'resource_id' => 200,
            'capability' => 'read',
        ]);

        $this->assertResponseCode(404);
        $this->assertFalse($this->service()->allows(3, 'read', 'project', 200));
    }

    public function testNonOwnerCannotListGrantsOnAnotherUsersResource(): void
    {
        $this->authenticate(2);

        $this->get('/api/capabilities?resource_type=project&resource_id=200');

        $this->assertResponseCode(404);
    }

    public function testSelfEscalationIsForbidden(): void
    {
        $this->service()->grant(1, 2, 'manage_permissions', 'project', 200);
        $this->authenticate(2);

        $this->post('/api/capabilities', [
            'subject_user_id' => 2,
            'resource_type' => 'project',
            'resource_id' => 200,
            'capability' => 'delete',
        ]);

        $this->assertResponseCode(403);
        $this->assertFalse($this->service()->allows(2, 'delete', 'project', 200));
    }

    public function testManagePermissionsHolderCanDelegate(): void
    {
        $this->service()->grant(1, 2, 'manage_permissions', 'project', 200);
        $this->authenticate(2);

        $this->post('/api/capabilities', [
            'subject_user_id' => 3,
            'resource_type' => 'project',
            'resource_id' => 200,
            'capability' => 'read',
        ]);

        $this->assertResponseCode(201);
        $this->assertTrue($this->service()->allows(3, 'read', 'project', 200));
    }

    public function testRevokeRemovesAccessImmediately(): void
    {
        $grant = $this->service()->grant(1, 2, 'read', 'project', 200);
        $this->authenticate();

        $this->delete('/api/capabilities/' . (int)$grant->id);

        $this->assertResponseOk();
        $this->assertFalse($this->service()->allows(2, 'read', 'project', 200));
    }

    public function testSubjectCannotRevokeOwnGrantToEscapeAudit(): void
    {
        $grant = $this->service()->grant(1, 2, 'read', 'project', 200);
        $this->authenticate(2);

        $this->delete('/api/capabilities/' . (int)$grant->id);

        $this->assertResponseCode(404);
        $this->assertTrue($this->service()->allows(2, 'read', 'project', 200));
    }

    public function testRevokeUnknownGrantIsNotFound(): void
    {
        $this->authenticate();

        $this->delete('/api/capabilities/9999');

        $this->assertResponseCode(404);
    }

    public function testReadGrantAllowsViewingAnotherUsersProject(): void
    {
        $this->service()->grant(1, 2, 'read', 'project', 200);
        $this->authenticate(2);

        $this->get('/api/projects/200');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner project"');
    }

    public function testReadGrantAllowsViewingAnotherUsersNotebook(): void
    {
        $this->service()->grant(1, 2, 'read', 'notebook', 400);
        $this->authenticate(2);

        $this->get('/api/notebooks/400');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner notebook"');
    }

    public function testReadGrantDoesNotAllowEditing(): void
    {
        $this->service()->grant(1, 2, 'read', 'project', 200);
        $this->authenticate(2);

        $this->patch('/api/projects/200', ['name' => 'Hijacked']);

        $this->assertResponseCode(404);
        $projects = TableRegistry::getTableLocator()->get('Projects');
        $this->assertSame('Owner project', (string)$projects->get(200)->name);
    }

    public function testReadGrantDoesNotAllowDeleting(): void
    {
        $this->service()->grant(1, 2, 'read', 'notebook', 400);
        $this->authenticate(2);

        $this->delete('/api/notebooks/400');

        $this->assertResponseCode(404);
        $notebooks = TableRegistry::getTableLocator()->get('Notebooks');
        $this->assertNotNull($notebooks->find()->where(['id' => 400])->first());
    }

    public function testReadGrantDoesNotExposeProjectInListings(): void
    {
        $this->service()->grant(1, 2, 'read', 'project', 200);
        $this->authenticate(2);

        $this->get('/api/projects');

        $this->assertResponseOk();
        $this->assertResponseNotContains('"Owner project"');
    }

    public function testRevokedReadGrantDeniesViewImmediately(): void
    {
        $grant = $this->service()->grant(1, 2, 'read', 'project', 200);
        $this->service()->revoke(1, (int)$grant->id);
        $this->authenticate(2);

        $this->get('/api/projects/200');

        $this->assertResponseCode(404);
    }

    public function testLibraryMembershipWithoutGrantRemainsInsufficient(): void
    {
        $this->authenticate();
        $this->post('/api/libraries/600/members/project/200');
        $this->assertResponseCode(201);
        $this->service()->grant(1, 2, 'read', 'library', 600);

        $this->authenticate(2);
        $this->get('/api/projects/200');

        $this->assertResponseCode(404);
    }

    public function testOwnershipRegressionOwnerStillSeesOwnProject(): void
    {
        $this->authenticate();

        $this->get('/api/projects/200');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner project"');
    }
}
