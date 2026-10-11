<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use App\Test\TestCase\SignedInSessionTrait;
use Cake\Database\Exception\QueryException;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class RecordsControllerTest extends TestCase
{
    use IntegrationTestTrait;
    use SignedInSessionTrait;

    private string $csrfToken;

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
        'app.Tags',
        'app.Todos',
        'app.TodosTags',
        'app.Records',
        'app.RecordsTags',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->csrfToken = (new CsrfProtectionMiddleware())->createToken();
        $this->cookie('csrfToken', $this->csrfToken);
    }

    /**
     * @return array<string, string>
     */
    private function csrfHeaders(): array
    {
        return ['Accept' => 'application/json', 'X-CSRF-Token' => $this->csrfToken];
    }

    private function authenticate(int $userId = 1): void
    {
        $this->signIn($userId);
        $this->configRequest(['headers' => $this->csrfHeaders()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $payload = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($payload);

        return $payload;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function postJson(string $url, array $body = []): void
    {
        $this->replaceRequest([
            'headers' => $this->csrfHeaders() + ['Content-Type' => 'application/json'],
        ]);
        $this->post($url, (string)json_encode($body));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function patchJson(string $url, array $body): void
    {
        $this->replaceRequest([
            'headers' => $this->csrfHeaders() + ['Content-Type' => 'application/json'],
        ]);
        $this->patch($url, (string)json_encode($body));
    }

    private function postWithCsrf(string $url): void
    {
        $this->replaceRequest(['headers' => $this->csrfHeaders()]);
        $this->post($url);
    }

    private function deleteWithCsrf(string $url): void
    {
        $this->replaceRequest(['headers' => $this->csrfHeaders()]);
        $this->delete($url);
    }

    /**
     * @return list<string>
     */
    private function listedTitles(string $query = ''): array
    {
        $this->get('/api/records' . $query);
        $this->assertResponseOk($query);

        return array_column($this->payload()['data']['items'], 'title');
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function create(array $fields): int
    {
        $this->postJson('/api/records', $fields);
        $this->assertResponseCode(201);

        return (int)$this->payload()['data']['record']['id'];
    }

    /**
     * @return list<string>
     */
    private function actions(int $userId = 1): array
    {
        return TableRegistry::getTableLocator()->get('ActivityRecords')->find()
            ->where(['actor_user_id' => $userId])
            ->orderBy(['id' => 'ASC'])
            ->all()
            ->extract('action')
            ->toList();
    }

    public function testAnonymousAccessIsDeniedAcrossAllEndpoints(): void
    {
        $this->configRequest(['headers' => $this->csrfHeaders()]);
        $this->get('/api/records');
        $this->assertResponseCode(401);
        $this->postJson('/api/records', ['title' => 'Anonymous']);
        $this->assertResponseCode(401);
        $this->get('/api/records/1200');
        $this->assertResponseCode(401);
        $this->patchJson('/api/records/1200', ['title' => 'Anonymous']);
        $this->assertResponseCode(401);
        $this->deleteWithCsrf('/api/records/1200');
        $this->assertResponseCode(401);
        $this->postWithCsrf('/api/records/1200/tags/101');
        $this->assertResponseCode(401);
        $this->deleteWithCsrf('/api/records/1200/tags/100');
        $this->assertResponseCode(401);
    }

    public function testRecordMutationRequiresCsrfToken(): void
    {
        $this->signIn(1);
        $this->replaceRequest([
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        ]);
        $this->post('/api/records', (string)json_encode(['title' => 'Without CSRF']));

        $this->assertResponseCode(403);
        $this->assertSame(2, TableRegistry::getTableLocator()->get('Records')->find()->where(['user_id' => 1])->count());
    }

    public function testTimelineListsOnlyOwnedRecordsNewestFirst(): void
    {
        $this->authenticate();
        $titles = $this->listedTitles();

        $this->assertSame(['Accessibility meetup', 'Roadmap check-in'], $titles);
        $this->assertSame(2, $this->payload()['meta']['pagination']['total']);
        $record = $this->payload()['data']['items'][1];
        $this->assertSame([['id' => 100, 'name' => 'Important']], $record['tags']);
        $this->assertSame('2026-03-01T10:00:00+00:00', $record['occurred_at']);
        $this->assertSame('discussion', $record['kind']);
        $this->assertArrayNotHasKey('user_id', $record);
    }

    public function testIdenticalOccurrenceTimesAreOrderedByNewestId(): void
    {
        $this->authenticate();
        $first = $this->create(['title' => 'Tie one', 'occurred_at' => '2026-03-01T10:00:00Z']);
        $second = $this->create(['title' => 'Tie two', 'occurred_at' => '2026-03-01T10:00:00Z']);
        $this->assertGreaterThan($first, $second);
        $this->assertLessThan(1200, $second);

        // Three records share 2026-03-01T10:00Z; the fixture's id 1200 is the highest of them.
        $this->assertSame(
            ['Accessibility meetup', 'Roadmap check-in', 'Tie two', 'Tie one'],
            $this->listedTitles(),
        );
        $this->assertSame(['Tie two'], $this->listedTitles('?limit=1&page=3'));
        $this->assertSame(['Tie one'], $this->listedTitles('?limit=1&page=4'));
    }

    public function testSearchCoversTitleSummaryParticipantsAndDetails(): void
    {
        $this->authenticate();

        $this->assertSame(['Roadmap check-in'], $this->listedTitles('?q=ROADMAP'));
        $this->assertSame(['Roadmap check-in'], $this->listedTitles('?q=export+work'));
        $this->assertSame(['Roadmap check-in'], $this->listedTitles('?q=team+lead'));
        $this->assertSame(['Accessibility meetup'], $this->listedTitles('?q=focus+order'));
        $this->assertSame([], $this->listedTitles('?q=Community+hall'));
        $this->assertSame([], $this->listedTitles('?q=user+two'));
        $this->assertSame(['Accessibility meetup', 'Roadmap check-in'], $this->listedTitles('?q=+++'));
    }

    public function testSearchEscapesLikeWildcards(): void
    {
        $this->authenticate();
        foreach (['100% certain', 'snake_case', 'back\\slash', 'plain'] as $title) {
            $this->create(['title' => $title]);
        }

        $this->assertSame(['100% certain'], $this->listedTitles('?q=' . rawurlencode('%')));
        $this->assertSame(['snake_case'], $this->listedTitles('?q=' . rawurlencode('_')));
        $this->assertSame(['back\\slash'], $this->listedTitles('?q=' . rawurlencode('\\')));
    }

    public function testOverlongSearchIsRejected(): void
    {
        $this->authenticate();
        $this->get('/api/records?q=' . str_repeat('a', 255));
        $this->assertResponseOk();
        $this->get('/api/records?q=' . str_repeat('a', 256));
        $this->assertResponseCode(400);
    }

    public function testKindAndTagFilters(): void
    {
        $this->authenticate();

        $this->assertSame(['Accessibility meetup'], $this->listedTitles('?kind=event'));
        $this->assertSame(['Roadmap check-in'], $this->listedTitles('?kind=discussion'));
        $this->assertSame(['Roadmap check-in'], $this->listedTitles('?tag=100'));
        $this->assertSame([], $this->listedTitles('?tag=101'));
        $this->assertSame([], $this->listedTitles('?tag=102'));

        foreach (['?kind=decision', '?kind[]=event', '?tag=abc', '?tag=0', '?tag=99999999999'] as $query) {
            $this->get('/api/records' . $query);
            $this->assertResponseCode(400, $query);
        }
    }

    public function testDateRangeBoundariesAreInclusive(): void
    {
        $this->authenticate();

        $this->assertSame(['Accessibility meetup'], $this->listedTitles('?from=2026-03-02'));
        $this->assertSame(['Roadmap check-in'], $this->listedTitles('?to=2026-03-01'));
        $this->assertSame(['Roadmap check-in'], $this->listedTitles('?from=2026-03-01&to=2026-03-01'));
        $this->assertSame([], $this->listedTitles('?from=2026-03-03'));
        $this->assertSame([], $this->listedTitles('?to=2026-02-28'));
        $this->assertSame(['Accessibility meetup'], $this->listedTitles('?from=2026-03-02T18:30:00Z'));
        $this->assertSame([], $this->listedTitles('?from=2026-03-02T18:30:01Z'));
        $this->assertSame(
            ['Accessibility meetup', 'Roadmap check-in'],
            $this->listedTitles('?to=' . rawurlencode('2026-03-02T19:30:00+01:00')),
        );
        $this->assertSame(['Roadmap check-in'], $this->listedTitles('?to=2026-03-02T18:29:59Z'));
        $this->assertSame(
            ['Accessibility meetup'],
            $this->listedTitles('?kind=event&from=2026-03-01&to=2026-03-31'),
        );

        foreach (
            [
                '?from=2026-02-30',
                '?from=yesterday',
                '?to=2026-3-1',
                '?from=2026-03-02&to=2026-03-01',
                '?from[]=2026-03-01',
                '?to=2026-03-01T10:00:00',
            ] as $query
        ) {
            $this->get('/api/records' . $query);
            $this->assertResponseCode(400, $query);
        }
    }

    public function testPaginationBoundaries(): void
    {
        $this->authenticate();
        $this->assertSame(['Accessibility meetup'], $this->listedTitles('?limit=1'));
        $this->assertSame(['Roadmap check-in'], $this->listedTitles('?limit=1&page=2'));
        $this->assertSame([], $this->listedTitles('?limit=1&page=3'));
        $this->get('/api/records?limit=1000');
        $this->assertSame(100, $this->payload()['meta']['pagination']['limit']);
        foreach (['?limit=0', '?page=0', '?page=abc', '?page=' . str_repeat('9', 30)] as $query) {
            $this->get('/api/records' . $query);
            $this->assertResponseCode(400, $query);
        }
    }

    public function testCreateWithOnlyATitleDefaultsKindAndTimeAndIgnoresOwnershipFields(): void
    {
        $this->authenticate();
        $before = DateTime::now('UTC')->subSeconds(1);
        $this->postJson('/api/records', [
            'title' => '  Corridor chat  ',
            'summary' => '',
            'user_id' => 2,
            'id' => 5,
        ]);
        $after = DateTime::now('UTC')->addSeconds(1);

        $this->assertResponseCode(201);
        $record = $this->payload()['data']['record'];
        $this->assertSame('Corridor chat', $record['title']);
        $this->assertSame('discussion', $record['kind']);
        $this->assertNull($record['summary']);
        $this->assertNull($record['participants']);
        $this->assertNull($record['location']);
        $this->assertNull($record['details']);
        $this->assertSame([], $record['tags']);
        $this->assertNotSame(5, $record['id']);
        $occurredAt = new DateTime($record['occurred_at']);
        $this->assertTrue($occurredAt->greaterThanOrEquals($before) && $occurredAt->lessThanOrEquals($after));
        $stored = TableRegistry::getTableLocator()->get('Records')->get($record['id']);
        $this->assertSame(1, (int)$stored->get('user_id'));
        $this->assertSame(['record.created'], $this->actions());
    }

    public function testCreateWithAllFieldsAcceptsPastAndFutureTimesInUtc(): void
    {
        $this->authenticate();
        $this->postJson('/api/records', [
            'kind' => 'event',
            'title' => 'Planned conference',
            'occurred_at' => '2030-01-15T09:00:00+02:00',
            'participants' => 'Speaker A, Organiser B',
            'location' => '  Main hall  ',
            'summary' => 'Keynote on offline-first design.',
            'details' => "Line one\nLine two",
        ]);

        $this->assertResponseCode(201);
        $record = $this->payload()['data']['record'];
        $this->assertSame('event', $record['kind']);
        $this->assertSame('2030-01-15T07:00:00+00:00', $record['occurred_at']);
        $this->assertSame('Main hall', $record['location']);
        $this->assertSame("Line one\nLine two", $record['details']);

        $past = $this->create(['title' => 'Old meeting', 'occurred_at' => '1999-12-31T23:59:59.5Z']);
        $this->get('/api/records/' . $past);
        $this->assertSame('1999-12-31T23:59:59+00:00', $this->payload()['data']['record']['occurred_at']);
    }

    public function testMalformedOccurredAtIsRejectedWith400(): void
    {
        $this->authenticate();
        foreach (
            [
                '2026-10-11',
                '2026-10-11T09:30:00',
                '2026-10-11 09:30:00Z',
                '2026-02-30T10:00:00Z',
                '2026-10-11T24:00:00Z',
                '2026-10-11T09:60:00Z',
                '2026-10-11T09:30:00+25:00',
                '0000-01-01T00:00:00Z',
                'tomorrow',
                '',
                123,
                null,
                ['2026-10-11T09:30:00Z'],
            ] as $value
        ) {
            $this->postJson('/api/records', ['title' => 'Bad time', 'occurred_at' => $value]);
            $this->assertResponseCode(400, (string)json_encode($value));
        }
        $this->patchJson('/api/records/1200', ['occurred_at' => 'not a time']);
        $this->assertResponseCode(400);
        $this->assertSame(0, TableRegistry::getTableLocator()->get('Records')->find()->where(['title' => 'Bad time'])->count());
    }

    public function testValidationBoundaries(): void
    {
        $this->authenticate();
        $cases = [
            [['title' => str_repeat('t', 255)], 201],
            [['title' => str_repeat('t', 256)], 422],
            [['title' => '   '], 422],
            [[], 422],
            [['title' => 'People', 'participants' => str_repeat('p', 2000)], 201],
            [['title' => 'People', 'participants' => str_repeat('p', 2001)], 422],
            [['title' => 'Place', 'kind' => 'event', 'location' => str_repeat('l', 255)], 201],
            [['title' => 'Place', 'kind' => 'event', 'location' => str_repeat('l', 256)], 422],
            [['title' => 'Place', 'location' => 'Only events have places'], 422],
            [['title' => 'Place', 'kind' => 'discussion', 'location' => '   '], 201],
            [['title' => 'Summary', 'summary' => str_repeat('s', 500)], 201],
            [['title' => 'Summary', 'summary' => str_repeat('s', 501)], 422],
            [['title' => 'Details', 'details' => str_repeat('d', 65536)], 201],
            [['title' => 'Details', 'details' => str_repeat('d', 65537)], 422],
            [['title' => 'Details', 'details' => str_repeat('é', 32769)], 422],
            [['title' => 'Kind', 'kind' => 'decision'], 422],
            [['title' => 'Kind', 'kind' => ''], 422],
        ];
        foreach ($cases as $index => [$body, $status]) {
            $this->postJson('/api/records', $body);
            $this->assertResponseCode($status, 'case ' . $index);
        }
    }

    public function testViewEditAndDeleteDenyForeignAndMissingRecords(): void
    {
        $this->authenticate();
        $this->get('/api/records/1201');
        $this->assertResponseOk();
        $this->assertSame('Community hall', $this->payload()['data']['record']['location']);

        foreach (['1202', '999999', '99999999999'] as $id) {
            $this->get('/api/records/' . $id);
            $this->assertResponseCode(404, $id);
            $this->patchJson('/api/records/' . $id, ['title' => 'Hijack']);
            $this->assertResponseCode(404, $id);
            $this->deleteWithCsrf('/api/records/' . $id);
            $this->assertResponseCode(404, $id);
        }
        $this->assertSame('Other user record', TableRegistry::getTableLocator()->get('Records')->get(1202)->get('title'));
        $this->assertSame([], $this->actions());
    }

    public function testEditRecordsChangedFieldNamesButNeverTheirText(): void
    {
        $this->authenticate();
        $this->patchJson('/api/records/1200', [
            'title' => 'Roadmap review',
            'occurred_at' => '2026-03-03T08:15:00-05:00',
            'participants' => 'Sensitive participant text',
            'user_id' => 2,
        ]);
        $this->assertResponseOk();
        $record = $this->payload()['data']['record'];
        $this->assertSame('Roadmap review', $record['title']);
        $this->assertSame('2026-03-03T13:15:00+00:00', $record['occurred_at']);
        $this->assertSame([['id' => 100, 'name' => 'Important']], $record['tags']);
        $this->assertSame(1, (int)TableRegistry::getTableLocator()->get('Records')->get(1200)->get('user_id'));

        $activity = TableRegistry::getTableLocator()->get('ActivityRecords')->find()
            ->where(['actor_user_id' => 1, 'action' => 'record.updated'])
            ->firstOrFail();
        $this->assertSame('record', $activity->get('subject_type'));
        $this->assertSame(1200, (int)$activity->get('subject_id'));
        $metadata = json_decode((string)$activity->get('metadata'), true);
        $this->assertSame(['title', 'occurred_at', 'participants'], $metadata['fields']);
        $this->assertStringNotContainsString('Sensitive', (string)$activity->get('metadata'));
        $this->assertSame(['Roadmap review', 'Accessibility meetup'], $this->listedTitles());
    }

    public function testSwitchingAnEventToADiscussionRequiresClearingTheLocation(): void
    {
        $this->authenticate();
        $this->patchJson('/api/records/1201', ['kind' => 'discussion']);
        $this->assertResponseCode(422);

        $this->patchJson('/api/records/1201', ['kind' => 'discussion', 'location' => null]);
        $this->assertResponseOk();
        $this->assertSame('discussion', $this->payload()['data']['record']['kind']);
        $this->assertNull($this->payload()['data']['record']['location']);

        $this->patchJson('/api/records/1201', ['title' => '']);
        $this->assertResponseCode(422);
    }

    public function testDeleteRemovesTagJoinsButKeepsTags(): void
    {
        $this->authenticate();
        $this->deleteWithCsrf('/api/records/1200');
        $this->assertResponseOk();

        $locator = TableRegistry::getTableLocator();
        $this->assertFalse($locator->get('Records')->exists(['id' => 1200]));
        $this->assertFalse($locator->get('RecordsTags')->exists(['record_id' => 1200]));
        $this->assertTrue($locator->get('Tags')->exists(['id' => 100]));
        $this->assertTrue($locator->get('RecordsTags')->exists(['record_id' => 1202, 'tag_id' => 102]));
        $this->assertSame(['record.deleted'], $this->actions());

        $this->deleteWithCsrf('/api/records/1200');
        $this->assertResponseCode(404);
    }

    public function testTagAttachAndDetachRequireOwnedRecordAndTag(): void
    {
        $this->authenticate();
        $this->postWithCsrf('/api/records/1201/tags/101');
        $this->assertResponseCode(201);
        $this->assertSame(['Accessibility meetup'], $this->listedTitles('?tag=101'));
        $this->postWithCsrf('/api/records/1201/tags/101');
        $this->assertResponseCode(409);
        $this->postWithCsrf('/api/records/1201/tags/102');
        $this->assertResponseCode(404);
        $this->postWithCsrf('/api/records/1202/tags/101');
        $this->assertResponseCode(404);
        $this->deleteWithCsrf('/api/records/1201/tags/101');
        $this->assertResponseOk();
        $this->deleteWithCsrf('/api/records/1201/tags/101');
        $this->assertResponseCode(404);
        $this->deleteWithCsrf('/api/records/1202/tags/102');
        $this->assertResponseCode(404);

        $this->assertSame(['record.tag_attached', 'record.tag_detached'], $this->actions());
        $this->assertTrue(TableRegistry::getTableLocator()->get('RecordsTags')->exists([
            'record_id' => 1202,
            'tag_id' => 102,
        ]));
    }

    public function testDatabaseRejectsInvalidKindLocationAndDetails(): void
    {
        $connection = TableRegistry::getTableLocator()->get('Records')->getConnection();
        foreach (
            [
                "INSERT INTO records (user_id, kind, title, occurred_at) VALUES (1, 'decision', 'Bad', now())",
                "INSERT INTO records (user_id, kind, title, occurred_at, location) VALUES (1, 'discussion', 'Bad', now(), 'Hall')",
                "INSERT INTO records (user_id, title, occurred_at, details) VALUES (1, 'Bad', now(), repeat('d', 65537))",
            ] as $sql
        ) {
            try {
                $connection->execute($sql);
                $this->fail('Expected the database to reject: ' . $sql);
            } catch (QueryException $exception) {
                $this->assertStringContainsString('records_', $exception->getMessage());
            }
        }
    }

    public function testOwnerDeletionIsRestrictedWhileRecordsExist(): void
    {
        $this->expectException(QueryException::class);
        TableRegistry::getTableLocator()->get('Records')->getConnection()
            ->execute('DELETE FROM users WHERE id = 2');
    }

    public function testRecordsDoNotChangeTodoReviewOrDashboardViews(): void
    {
        $this->authenticate();
        $before = [];
        foreach (['/api/todos', '/api/todos/review-queue', '/api/dashboard'] as $url) {
            $this->get($url);
            $this->assertResponseOk();
            $before[$url] = $this->payload();
        }

        $recordId = $this->create(['title' => 'Unrelated record', 'kind' => 'event', 'location' => 'Hall']);
        $this->postWithCsrf('/api/records/' . $recordId . '/tags/101');
        $this->assertResponseCode(201);
        $this->patchJson('/api/records/' . $recordId, ['summary' => 'Follow up later.']);
        $this->assertResponseOk();

        foreach ($before as $url => $payload) {
            $this->get($url);
            $after = $this->payload();
            // The dashboard's recent-activity panel legitimately shows the new record's history.
            unset($payload['data']['recent']['activity'], $after['data']['recent']['activity']);
            $this->assertSame($payload, $after, $url);
        }
    }
}
