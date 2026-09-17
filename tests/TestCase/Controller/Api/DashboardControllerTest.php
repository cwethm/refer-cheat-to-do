<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class DashboardControllerTest extends TestCase
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
    private function dashboard(string $url = '/api/dashboard'): array
    {
        $this->get($url);
        $this->assertResponseOk();

        return $this->payload()['data'];
    }

    public function testAnonymousCannotSeeDashboard(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);

        $this->get('/api/dashboard');

        $this->assertResponseCode(401);
    }

    public function testDashboardShapeIsComplete(): void
    {
        $this->authenticate();

        $data = $this->dashboard();

        $this->assertSame(['inbox', 'active', 'review_due', 'orphans'], array_keys($data['counts']));
        $this->assertSame(
            ['todos', 'projects', 'notebooks', 'libraries', 'activity'],
            array_keys($data['recent']),
        );
    }

    public function testEmptyWorkspaceReportsZeroes(): void
    {
        $this->authenticate(3);

        $data = $this->dashboard();

        $this->assertSame(0, $data['counts']['inbox']);
        $this->assertSame(0, $data['counts']['active']);
        $this->assertSame(0, $data['counts']['review_due']);
        $this->assertSame(0, $data['counts']['orphans']);
        $this->assertSame([], $data['recent']['todos']);
        $this->assertSame([], $data['recent']['projects']);
        $this->assertSame([], $data['recent']['notebooks']);
        $this->assertSame([], $data['recent']['libraries']);
        $this->assertSame([], $data['recent']['activity']);
    }

    public function testInboxCountMatchesTodoListing(): void
    {
        $this->authenticate();
        $this->post('/api/todos', ['title' => 'New capture']);
        $this->assertResponseCode(201);

        $this->get('/api/todos?status=inbox');
        $this->assertResponseOk();
        $listed = $this->payload()['meta']['pagination']['total'];

        $this->assertSame($listed, $this->dashboard()['counts']['inbox']);
    }

    public function testArchivedAndTrashedTodosAreExcluded(): void
    {
        $this->authenticate();
        $this->post('/api/todos', ['title' => 'Will be archived']);
        $this->assertResponseCode(201);
        $archivedId = $this->payload()['data']['todo']['id'];
        $before = $this->dashboard()['counts']['inbox'];

        $this->post('/api/todos/' . $archivedId . '/archive');
        $this->assertResponseOk();

        $data = $this->dashboard();
        $this->assertSame($before - 1, $data['counts']['inbox']);
        $this->assertNotContains($archivedId, array_column($data['recent']['todos'], 'id'));
    }

    public function testReviewDueCountMatchesReviewQueue(): void
    {
        $this->authenticate();
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todos->updateAll(['next_review_at' => new DateTime('2000-01-01 00:00:00')], ['id' => 10]);

        $data = $this->dashboard();

        $this->assertSame(1, $data['counts']['review_due']);

        $todos->updateAll(['next_review_at' => new DateTime('+30 days')], ['id' => 10]);

        $this->assertSame(0, $this->dashboard()['counts']['review_due']);
    }

    public function testOrphanCountMatchesReviewQueueTotal(): void
    {
        $this->authenticate();

        $this->get('/api/todos/review-queue?limit=100');
        $this->assertResponseOk();
        $queueTotal = $this->payload()['meta']['pagination']['total'];

        $this->assertSame($queueTotal, $this->dashboard()['counts']['orphans']);
    }

    public function testRecentListsAreScopedToTheOwner(): void
    {
        $this->authenticate(2);

        $data = $this->dashboard();

        $this->assertSame([201], array_column($data['recent']['projects'], 'id'));
        $this->assertSame([401], array_column($data['recent']['notebooks'], 'id'));
        $this->assertSame([602], array_column($data['recent']['libraries'], 'id'));
        $this->assertSame([12], array_column($data['recent']['todos'], 'id'));
    }

    public function testCountsAreScopedToTheOwner(): void
    {
        $this->authenticate(2);
        $ownCount = $this->dashboard()['counts']['inbox'];

        $this->authenticate();
        $this->post('/api/todos', ['title' => 'Not visible to user two']);
        $this->assertResponseCode(201);

        $this->authenticate(2);
        $this->assertSame($ownCount, $this->dashboard()['counts']['inbox']);
    }

    public function testRecentActivityIsIncludedAndScoped(): void
    {
        $this->authenticate();
        $this->post('/api/todos', ['title' => 'Recorded']);
        $this->assertResponseCode(201);

        $activity = $this->dashboard()['recent']['activity'];

        $this->assertCount(1, $activity);
        $this->assertSame('todo.created', $activity[0]['action']);

        $this->authenticate(2);
        $this->assertSame([], $this->dashboard()['recent']['activity']);
    }

    public function testRecentLimitIsHonoured(): void
    {
        $this->authenticate();
        foreach (range(1, 7) as $index) {
            $this->post('/api/todos', ['title' => 'Item ' . $index]);
            $this->assertResponseCode(201);
        }

        $this->assertCount(5, $this->dashboard()['recent']['todos']);
        $this->assertCount(2, $this->dashboard('/api/dashboard?recent_limit=2')['recent']['todos']);
    }

    public function testRecentLimitIsCapped(): void
    {
        $this->authenticate();

        $this->get('/api/dashboard?recent_limit=9999');

        $this->assertResponseOk();
    }

    public function testMalformedRecentLimitIsRejected(): void
    {
        $this->authenticate();

        $this->get('/api/dashboard?recent_limit=abc');

        $this->assertResponseCode(400);
    }

    public function testZeroRecentLimitIsRejected(): void
    {
        $this->authenticate();

        $this->get('/api/dashboard?recent_limit=0');

        $this->assertResponseCode(400);
    }

    public function testDashboardDoesNotRecordActivity(): void
    {
        $this->authenticate();
        $this->get('/api/dashboard');
        $this->assertResponseOk();

        $this->get('/api/activity');
        $this->assertResponseOk();
        $this->assertSame([], $this->payload()['data']['items']);
    }
}
