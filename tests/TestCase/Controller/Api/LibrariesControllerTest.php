<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class LibrariesControllerTest extends TestCase
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
    ];

    private function authenticate(int $userId = 1): void
    {
        $this->session(['Auth.user_id' => $userId]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    public function testListReturnsOnlyOwnedLibraries(): void
    {
        $this->authenticate();

        $this->get('/api/libraries');

        $this->assertResponseOk();
        $this->assertResponseContains('"Owner library"');
        $this->assertResponseNotContains('"Other user library"');
    }

    public function testListRejectsInvalidPagination(): void
    {
        $this->authenticate();

        $this->get('/api/libraries?page=0');

        $this->assertResponseCode(400);
    }

    public function testAnonymousAccessDenied(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->get('/api/libraries');

        $this->assertResponseCode(401);
    }

    public function testAnonymousMemberAdditionDenied(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->post('/api/libraries/600/members/project/200');

        $this->assertResponseCode(401);
        $this->assertSame([], $this->joinRows('LibrariesProjects'));
    }

    public function testCreateLibraryAssignsOwnershipFromSession(): void
    {
        $this->authenticate();

        $this->post('/api/libraries', [
            'name' => 'Research library',
            'description' => 'context only',
            'user_id' => 2,
        ]);

        $this->assertResponseCode(201);
        $libraries = TableRegistry::getTableLocator()->get('Libraries');
        $library = $libraries->find()->where(['name' => 'Research library'])->firstOrFail();
        $this->assertSame(1, (int)$library->user_id);
    }

    public function testCreateRejectsBlankName(): void
    {
        $this->authenticate();

        $this->post('/api/libraries', ['name' => '']);

        $this->assertResponseCode(422);
        $this->assertResponseContains('"code": "VALIDATION_ERROR"');
    }

    public function testEditCannotReassignOwnership(): void
    {
        $this->authenticate();

        $this->patch('/api/libraries/600', ['name' => 'Renamed', 'user_id' => 2]);

        $this->assertResponseOk();
        $libraries = TableRegistry::getTableLocator()->get('Libraries');
        $library = $libraries->get(600);
        $this->assertSame('Renamed', (string)$library->name);
        $this->assertSame(1, (int)$library->user_id);
    }

    public function testViewOtherUsersLibraryIsNotFound(): void
    {
        $this->authenticate();

        $this->get('/api/libraries/602');

        $this->assertResponseCode(404);
    }

    public function testEditOtherUsersLibraryIsNotFound(): void
    {
        $this->authenticate();

        $this->patch('/api/libraries/602', ['name' => 'Hijacked']);

        $this->assertResponseCode(404);
        $libraries = TableRegistry::getTableLocator()->get('Libraries');
        $this->assertSame('Other user library', (string)$libraries->get(602)->name);
    }

    public function testDeleteOtherUsersLibraryIsNotFound(): void
    {
        $this->authenticate();

        $this->delete('/api/libraries/602');

        $this->assertResponseCode(404);
        $libraries = TableRegistry::getTableLocator()->get('Libraries');
        $this->assertNotNull($libraries->find()->where(['id' => 602])->first());
    }

    public function testAddProjectMemberAndList(): void
    {
        $this->authenticate();

        $this->post('/api/libraries/600/members/project/200');
        $this->assertResponseCode(201);

        $this->get('/api/libraries/600/members');
        $this->assertResponseOk();
        $this->assertResponseContains('"Owner project"');
    }

    public function testAddNotebookMemberAndList(): void
    {
        $this->authenticate();

        $this->post('/api/libraries/600/members/notebook/400');
        $this->assertResponseCode(201);

        $this->get('/api/libraries/600');
        $this->assertResponseOk();
        $this->assertResponseContains('"Owner notebook"');
    }

    public function testDuplicateMemberAdditionConflicts(): void
    {
        $this->authenticate();

        $this->post('/api/libraries/600/members/project/200');
        $this->assertResponseCode(201);

        $this->post('/api/libraries/600/members/project/200');
        $this->assertResponseCode(409);
        $this->assertCount(1, $this->joinRows('LibrariesProjects'));
    }

    public function testUnknownMemberTypeRejected(): void
    {
        $this->authenticate();

        $this->post('/api/libraries/600/members/widget/200');

        $this->assertResponseCode(400);
    }

    public function testCrossUserProjectMembershipDenied(): void
    {
        $this->authenticate();

        $this->post('/api/libraries/600/members/project/201');

        $this->assertResponseCode(404);
        $this->assertSame([], $this->joinRows('LibrariesProjects'));
    }

    public function testCrossUserNotebookMembershipDenied(): void
    {
        $this->authenticate();

        $this->post('/api/libraries/600/members/notebook/401');

        $this->assertResponseCode(404);
        $this->assertSame([], $this->joinRows('LibrariesNotebooks'));
    }

    public function testCrossUserLibraryIdTamperingDenied(): void
    {
        $this->authenticate(2);

        $this->post('/api/libraries/600/members/project/201');

        $this->assertResponseCode(404);
        $this->assertSame([], $this->joinRows('LibrariesProjects'));
    }

    public function testRemoveMemberDoesNotDeleteProject(): void
    {
        $this->authenticate();
        $this->post('/api/libraries/600/members/project/200');
        $this->assertResponseCode(201);

        $this->delete('/api/libraries/600/members/project/200');

        $this->assertResponseOk();
        $projects = TableRegistry::getTableLocator()->get('Projects');
        $this->assertNotNull($projects->find()->where(['id' => 200])->first());
        $this->assertSame([], $this->joinRows('LibrariesProjects'));
    }

    public function testRemoveMemberDoesNotDeleteNotebook(): void
    {
        $this->authenticate();
        $this->post('/api/libraries/600/members/notebook/400');
        $this->assertResponseCode(201);

        $this->delete('/api/libraries/600/members/notebook/400');

        $this->assertResponseOk();
        $notebooks = TableRegistry::getTableLocator()->get('Notebooks');
        $this->assertNotNull($notebooks->find()->where(['id' => 400])->first());
    }

    public function testRemoveAbsentMemberIsNotFound(): void
    {
        $this->authenticate();

        $this->delete('/api/libraries/600/members/project/200');

        $this->assertResponseCode(404);
    }

    public function testDeleteLibraryWithMembersConflicts(): void
    {
        $this->authenticate();
        $this->post('/api/libraries/600/members/project/200');
        $this->assertResponseCode(201);

        $this->delete('/api/libraries/600');

        $this->assertResponseCode(409);
    }

    public function testDeleteEmptyLibraryLeavesMembersIntact(): void
    {
        $this->authenticate();
        $this->post('/api/libraries/600/members/project/200');
        $this->post('/api/libraries/600/members/notebook/400');
        $this->delete('/api/libraries/600/members/project/200');
        $this->delete('/api/libraries/600/members/notebook/400');

        $this->delete('/api/libraries/600');

        $this->assertResponseOk();
        $locator = TableRegistry::getTableLocator();
        $this->assertNotNull($locator->get('Projects')->find()->where(['id' => 200])->first());
        $this->assertNotNull($locator->get('Notebooks')->find()->where(['id' => 400])->first());
    }

    public function testDeletingLibraryDirectlyCascadesOnlyJoinRows(): void
    {
        $this->authenticate();
        $this->post('/api/libraries/600/members/project/200');
        $this->post('/api/libraries/600/members/notebook/400');

        $locator = TableRegistry::getTableLocator();
        $libraries = $locator->get('Libraries');
        $libraries->deleteOrFail($libraries->get(600));

        $this->assertSame([], $this->joinRows('LibrariesProjects'));
        $this->assertSame([], $this->joinRows('LibrariesNotebooks'));
        $this->assertNotNull($locator->get('Projects')->find()->where(['id' => 200])->first());
        $this->assertNotNull($locator->get('Notebooks')->find()->where(['id' => 400])->first());
    }

    public function testMembershipDoesNotGrantEditAuthorityToOtherUsers(): void
    {
        $this->authenticate();
        $this->post('/api/libraries/600/members/project/200');
        $this->assertResponseCode(201);

        $this->authenticate(2);
        $this->patch('/api/projects/200', ['name' => 'Hijacked']);

        $this->assertResponseCode(404);
        $projects = TableRegistry::getTableLocator()->get('Projects');
        $this->assertSame('Owner project', (string)$projects->get(200)->name);
    }

    public function testMembershipDoesNotGrantDeleteAuthorityToOtherUsers(): void
    {
        $this->authenticate();
        $this->post('/api/libraries/600/members/notebook/400');
        $this->assertResponseCode(201);

        $this->authenticate(2);
        $this->delete('/api/notebooks/400');

        $this->assertResponseCode(404);
        $notebooks = TableRegistry::getTableLocator()->get('Notebooks');
        $this->assertNotNull($notebooks->find()->where(['id' => 400])->first());
    }

    public function testMembershipDoesNotTransferOwnership(): void
    {
        $this->authenticate();
        $this->post('/api/libraries/600/members/project/200');
        $this->post('/api/libraries/600/members/notebook/400');

        $locator = TableRegistry::getTableLocator();
        $this->assertSame(1, (int)$locator->get('Projects')->get(200)->user_id);
        $this->assertSame(1, (int)$locator->get('Notebooks')->get(400)->user_id);
    }

    public function testMembershipDoesNotExposeMembersToOtherUsers(): void
    {
        $this->authenticate();
        $this->post('/api/libraries/600/members/project/200');

        $this->authenticate(2);
        $this->get('/api/libraries/600/members');

        $this->assertResponseCode(404);
    }

    /**
     * Read the current join rows for a membership table.
     *
     * @return list<array<string, mixed>>
     */
    private function joinRows(string $table): array
    {
        $rows = [];
        foreach (TableRegistry::getTableLocator()->get($table)->find()->all() as $row) {
            $rows[] = $row->toArray();
        }

        return $rows;
    }
}
