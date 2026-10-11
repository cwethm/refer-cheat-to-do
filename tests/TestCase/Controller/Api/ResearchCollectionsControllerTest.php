<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use App\Service\CapabilityService;
use App\Test\TestCase\SignedInSessionTrait;
use Cake\Database\Exception\QueryException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class ResearchCollectionsControllerTest extends TestCase
{
    use IntegrationTestTrait;
    use SignedInSessionTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.ActivityRecords',
        'app.ResearchCollections',
        'app.ResearchCollectionItems',
        'app.CapabilityGrants',
        'app.Projects',
        'app.Notebooks',
        'app.Todos',
    ];

    private function authenticate(int $userId = 1): void
    {
        $this->signIn($userId);
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
    }

    private function grant(int $userId, string $capability): void
    {
        (new CapabilityService(TableRegistry::getTableLocator()))->grant(
            1,
            $userId,
            $capability,
            CapabilityService::RESOURCE_RESEARCH_COLLECTION,
            900,
        );
    }

    public function testAnonymousAccessIsDenied(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->get('/api/research-collections');

        $this->assertResponseCode(401);
    }

    public function testAnonymousAccessIsDeniedAcrossAllEndpoints(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/research-collections');
        $this->assertResponseCode(401);
        $this->post('/api/research-collections', ['name' => 'Anonymous']);
        $this->assertResponseCode(401);
        $this->get('/api/research-collections/900');
        $this->assertResponseCode(401);
        $this->patch('/api/research-collections/900', ['name' => 'Anonymous']);
        $this->assertResponseCode(401);
        $this->delete('/api/research-collections/900');
        $this->assertResponseCode(401);
        $this->get('/api/research-collections/900/items');
        $this->assertResponseCode(401);
        $this->post('/api/research-collections/900/items', ['title' => 'Anonymous']);
        $this->assertResponseCode(401);
        $this->get('/api/research-collections/900/items/990');
        $this->assertResponseCode(401);
        $this->patch('/api/research-collections/900/items/990', ['title' => 'Anonymous']);
        $this->assertResponseCode(401);
        $this->delete('/api/research-collections/900/items/990');
        $this->assertResponseCode(401);
    }

    public function testListOmitsOtherUsersCollectionsUntilGrantedRead(): void
    {
        $this->authenticate(2);
        $this->get('/api/research-collections');
        $this->assertResponseOk();
        $this->assertResponseNotContains('Owner collection');
        $this->assertResponseContains('Other collection');

        $this->grant(2, CapabilityService::CAP_READ);
        $this->get('/api/research-collections');
        $this->assertResponseOk();
        $this->assertResponseContains('Owner collection');
        $this->assertResponseContains('Other collection');
    }

    public function testCreateAssignsOwnerAndCreatorFromSession(): void
    {
        $this->authenticate(2);
        $this->post('/api/research-collections', [
            'name' => 'New collection',
            'description' => 'Research',
            'user_id' => 1,
            'created_by_user_id' => 1,
        ]);

        $this->assertResponseCode(201);
        $collection = TableRegistry::getTableLocator()->get('ResearchCollections')
            ->find()->where(['name' => 'New collection'])->firstOrFail();
        $this->assertSame(2, (int)$collection->user_id);
        $this->assertSame(2, (int)$collection->created_by_user_id);
        $this->assertSame(1, TableRegistry::getTableLocator()->get('ActivityRecords')
            ->find()->where(['subject_type' => 'research_collection'])->count());
    }

    public function testReadRequiresReadCapabilityAndReadDoesNotAllowEdit(): void
    {
        $this->authenticate(2);
        $this->get('/api/research-collections/900');
        $this->assertResponseCode(404);

        $this->grant(2, CapabilityService::CAP_READ);
        $this->get('/api/research-collections/900');
        $this->assertResponseOk();
        $this->patch('/api/research-collections/900', ['name' => 'Attempted edit']);
        $this->assertResponseCode(404);
    }

    public function testEditCapabilityDoesNotImplyReadAndCanEditCollection(): void
    {
        $this->grant(2, CapabilityService::CAP_EDIT);
        $this->authenticate(2);

        $this->patch('/api/research-collections/900', ['name' => 'Edited by grantee']);

        $this->assertResponseOk();
        $this->assertResponseContains('Edited by grantee');
        $this->get('/api/research-collections/900');
        $this->assertResponseCode(404);
    }

    public function testManagePermissionsDoesNotGrantCollectionRead(): void
    {
        $this->grant(2, CapabilityService::CAP_MANAGE_PERMISSIONS);
        $this->authenticate(2);

        $this->get('/api/research-collections/900');

        $this->assertResponseCode(404);
    }

    public function testCapabilityApiSharesCollectionWithNamedUser(): void
    {
        $this->authenticate();
        $this->post('/api/capabilities', [
            'subject_user_id' => 2,
            'resource_type' => CapabilityService::RESOURCE_RESEARCH_COLLECTION,
            'resource_id' => 900,
            'capability' => CapabilityService::CAP_READ,
        ]);
        $this->assertResponseCode(201);

        $this->authenticate(2);
        $this->get('/api/research-collections/900');
        $this->assertResponseOk();
    }

    public function testItemsRequireCollectionReadAndItemMutationsRequireEdit(): void
    {
        $this->authenticate(2);
        $this->get('/api/research-collections/900/items');
        $this->assertResponseCode(404);
        $this->get('/api/research-collections/900/items/990');
        $this->assertResponseCode(404);

        $this->grant(2, CapabilityService::CAP_READ);
        $this->get('/api/research-collections/900/items');
        $this->assertResponseOk();
        $this->patch('/api/research-collections/900/items/990', ['title' => 'No edit grant']);
        $this->assertResponseCode(404);
    }

    public function testItemMutationRequiresEditAndDeleteCapabilitiesIndependently(): void
    {
        $this->grant(2, CapabilityService::CAP_READ);
        $this->authenticate(2);
        $this->post('/api/research-collections/900/items', ['title' => 'Denied']);
        $this->assertResponseCode(404);

        $this->grant(2, CapabilityService::CAP_EDIT);
        $this->post('/api/research-collections/900/items', ['title' => 'Allowed']);
        $this->assertResponseCode(201);
        $this->delete('/api/research-collections/900/items/990');
        $this->assertResponseCode(404);

        $this->grant(2, CapabilityService::CAP_DELETE);
        $this->delete('/api/research-collections/900/items/990');
        $this->assertResponseOk();
        $this->delete('/api/research-collections/900');
        $this->assertResponseOk();
    }

    public function testReaderDoesNotSeeReferenceToResourceTheyCannotAccess(): void
    {
        $this->grant(2, CapabilityService::CAP_READ);
        $this->authenticate(2);

        $this->get('/api/research-collections/900/items/990');

        $this->assertResponseOk();
        $this->assertResponseContains('"related_resource_type": null');
        $this->assertResponseContains('"related_resource_id": null');
    }

    public function testItemCreateValidatesContentLimitAndRecordsActivity(): void
    {
        $this->authenticate();
        $this->post('/api/research-collections/900/items', [
            'title' => 'New source',
            'content' => 'Safe plain text',
            'related_resource_type' => 'todo',
            'related_resource_id' => 10,
        ]);
        $this->assertResponseCode(201);

        $this->post('/api/research-collections/900/items', [
            'title' => 'Oversized source',
            'content' => str_repeat('x', 65537),
        ]);
        $this->assertResponseCode(400);
        $this->assertSame(1, TableRegistry::getTableLocator()->get('ActivityRecords')
            ->find()->where(['subject_type' => 'research_collection_item'])->count());
    }

    public function testCannotLinkToAnotherUsersTodo(): void
    {
        $this->authenticate();
        $this->post('/api/research-collections/900/items', [
            'title' => 'Foreign reference',
            'related_resource_type' => 'todo',
            'related_resource_id' => 12,
        ]);

        $this->assertResponseCode(404);
    }

    public function testDeleteRequiresDeleteAndCascadesItemsAndGrants(): void
    {
        $this->grant(2, CapabilityService::CAP_DELETE);
        $this->authenticate(2);

        $this->delete('/api/research-collections/900');

        $this->assertResponseOk();
        $this->assertFalse(TableRegistry::getTableLocator()->get('ResearchCollections')->exists(['id' => 900]));
        $this->assertFalse(TableRegistry::getTableLocator()->get('ResearchCollectionItems')->exists([
            'research_collection_id' => 900,
        ]));
        $this->assertFalse(TableRegistry::getTableLocator()->get('CapabilityGrants')->exists([
            'resource_type' => CapabilityService::RESOURCE_RESEARCH_COLLECTION,
            'resource_id' => 900,
        ]));
    }

    public function testOwnerAccountDeletionIsRestrictedWhileCollectionExists(): void
    {
        $collections = TableRegistry::getTableLocator()->get('ResearchCollections');
        $collection = $collections->newEntity(['name' => 'Restricted owner collection']);
        $collection->set('user_id', 3);
        $collection->set('created_by_user_id', 3);
        $collections->saveOrFail($collection);

        $this->expectException(QueryException::class);
        TableRegistry::getTableLocator()->get('Users')->deleteOrFail(
            TableRegistry::getTableLocator()->get('Users')->get(3),
        );
    }

    public function testCollectionAndItemListsValidatePagination(): void
    {
        $this->authenticate();

        $this->get('/api/research-collections?page=0');
        $this->assertResponseCode(400);
        $this->get('/api/research-collections/900/items?limit=abc');
        $this->assertResponseCode(400);
    }

    public function testDeletingReferencedTodoOrphansButPreservesCollectionItem(): void
    {
        $todo = TableRegistry::getTableLocator()->get('Todos')->get(10);
        TableRegistry::getTableLocator()->get('Todos')->deleteOrFail($todo);

        $item = TableRegistry::getTableLocator()->get('ResearchCollectionItems')->get(990);
        $this->assertSame('Source item', $item->title);
        $this->assertNull($item->related_resource_type);
        $this->assertNull($item->related_resource_id);
    }

    public function testDeletingReferencedNotebookOrphansButPreservesCollectionItem(): void
    {
        $items = TableRegistry::getTableLocator()->get('ResearchCollectionItems');
        $item = $items->newEntity([
            'title' => 'Notebook source',
            'related_resource_type' => 'notebook',
            'related_resource_id' => 400,
        ]);
        $item->set('research_collection_id', 900);
        $items->saveOrFail($item);
        $notebook = TableRegistry::getTableLocator()->get('Notebooks')->get(400);
        TableRegistry::getTableLocator()->get('Notebooks')->deleteOrFail($notebook);

        $orphan = $items->get((int)$item->id);
        $this->assertSame('Notebook source', $orphan->title);
        $this->assertNull($orphan->related_resource_type);
        $this->assertNull($orphan->related_resource_id);
    }

    public function testDeletingReferencedProjectOrphansButPreservesCollectionItem(): void
    {
        $items = TableRegistry::getTableLocator()->get('ResearchCollectionItems');
        $item = $items->newEntity([
            'title' => 'Project source',
            'related_resource_type' => 'project',
            'related_resource_id' => 200,
        ]);
        $item->set('research_collection_id', 900);
        $items->saveOrFail($item);
        $project = TableRegistry::getTableLocator()->get('Projects')->get(200);
        TableRegistry::getTableLocator()->get('Projects')->deleteOrFail($project);

        $orphan = $items->get((int)$item->id);
        $this->assertSame('Project source', $orphan->title);
        $this->assertNull($orphan->related_resource_type);
        $this->assertNull($orphan->related_resource_id);
    }
}
