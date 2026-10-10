<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use App\Service\CapabilityService;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class ResearchCollectionsControllerTest extends TestCase
{
    use IntegrationTestTrait;

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
        $this->session(['Auth.user_id' => $userId]);
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
}
