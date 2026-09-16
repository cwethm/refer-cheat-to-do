<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class TagsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Todos',
        'app.Tags',
        'app.TodosTags',
    ];

    public function testListReturnsOnlyCurrentUserTags(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/tags');

        $this->assertResponseOk();
        $this->assertResponseContains('"Important"');
        $this->assertResponseContains('"Reading"');
        $this->assertResponseNotContains('"Private"');
    }

    public function testCreateTagNormalizesNameAndAssignsOwner(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/tags', [
            'name' => '  Follow   Up ',
            'user_id' => 2,
        ]);

        $this->assertResponseCode(201);
        $this->assertResponseContains('"name": "Follow Up"');

        $tags = TableRegistry::getTableLocator()->get('Tags');
        $tag = $tags->find()->where(['name' => 'Follow Up'])->firstOrFail();
        $this->assertSame(1, (int)$tag->user_id);
    }

    public function testCreateRejectsDuplicateNormalizedNameForUser(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->post('/api/tags', [
            'name' => ' important ',
        ]);

        $this->assertResponseCode(422);
        $this->assertResponseContains('"code": "VALIDATION_ERROR"');
    }

    public function testEditRejectsCrossUserAccess(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->patch('/api/tags/102', ['name' => 'Nope']);

        $this->assertResponseCode(404);
        $this->assertResponseContains('"code": "NOT_FOUND"');
    }

    public function testDeleteRemovesOwnedTag(): void
    {
        $this->session(['Auth.user_id' => 1]);
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->delete('/api/tags/101');

        $this->assertResponseOk();
        $this->assertResponseContains('"Tag deleted."');

        $tags = TableRegistry::getTableLocator()->get('Tags');
        $this->assertNull($tags->find()->where(['id' => 101])->first());
    }

    public function testAnonymousTagAccessIsDenied(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->get('/api/tags');

        $this->assertResponseCode(401);
        $this->assertResponseContains('"code": "UNAUTHORIZED"');
    }
}
