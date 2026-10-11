<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use Cake\Database\Exception\QueryException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class ReferenceCardsControllerTest extends TestCase
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
        'app.Tags',
        'app.Todos',
        'app.TodosTags',
        'app.ReferenceCards',
        'app.ReferenceCardsTags',
        'app.CheatSheets',
        'app.CheatSheetsReferenceCards',
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
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        ]);
        $this->post($url, (string)json_encode($body));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function patchJson(string $url, array $body): void
    {
        $this->replaceRequest([
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        ]);
        $this->patch($url, (string)json_encode($body));
    }

    /**
     * @return list<string>
     */
    private function listedTitles(string $query = ''): array
    {
        $this->get('/api/reference-cards' . $query);
        $this->assertResponseOk();

        return array_column($this->payload()['data']['items'], 'title');
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
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/api/reference-cards');
        $this->assertResponseCode(401);
        $this->post('/api/reference-cards', ['title' => 'Anonymous']);
        $this->assertResponseCode(401);
        $this->get('/api/reference-cards/1000');
        $this->assertResponseCode(401);
        $this->patch('/api/reference-cards/1000', ['title' => 'Anonymous']);
        $this->assertResponseCode(401);
        $this->delete('/api/reference-cards/1000');
        $this->assertResponseCode(401);
        $this->post('/api/reference-cards/1000/tags/101');
        $this->assertResponseCode(401);
        $this->delete('/api/reference-cards/1000/tags/100');
        $this->assertResponseCode(401);
    }

    public function testIndexListsOnlyOwnedCardsInTitleOrder(): void
    {
        $this->authenticate();
        $titles = $this->listedTitles();

        $this->assertSame(['Active listening', 'Declining scope creep'], $titles);
        $this->assertSame(2, $this->payload()['meta']['pagination']['total']);
        $card = $this->payload()['data']['items'][0];
        $this->assertSame([['id' => 100, 'name' => 'Important']], $card['tags']);
        $this->assertArrayNotHasKey('user_id', $card);
    }

    public function testSearchIsCaseInsensitiveAndCoversDialogueFields(): void
    {
        $this->authenticate();

        $this->assertSame(['Active listening'], $this->listedTitles('?q=PARAPHRASE'));
        $this->assertSame(['Declining scope creep'], $this->listedTitles('?q=trade-off'));
        $this->assertSame([], $this->listedTitles('?q=user+two'));
        $this->assertSame(['Active listening', 'Declining scope creep'], $this->listedTitles('?q=+++'));
    }

    public function testSearchEscapesLikeWildcards(): void
    {
        $this->authenticate();
        foreach (['100% certain', 'snake_case', 'back\\slash', 'plain'] as $title) {
            $this->postJson('/api/reference-cards', ['title' => $title]);
            $this->assertResponseCode(201);
        }

        $this->assertSame(['100% certain'], $this->listedTitles('?q=' . rawurlencode('%')));
        $this->assertSame(['snake_case'], $this->listedTitles('?q=' . rawurlencode('_')));
        $this->assertSame(['back\\slash'], $this->listedTitles('?q=' . rawurlencode('\\')));
    }

    public function testOverlongSearchIsRejected(): void
    {
        $this->authenticate();
        $this->get('/api/reference-cards?q=' . str_repeat('a', 255));
        $this->assertResponseOk();
        $this->get('/api/reference-cards?q=' . str_repeat('a', 256));
        $this->assertResponseCode(400);
    }

    public function testTypeTagAndSheetFilters(): void
    {
        $this->authenticate();

        $this->assertSame(['Declining scope creep'], $this->listedTitles('?card_type=dialogue_pattern'));
        $this->assertSame(['Active listening'], $this->listedTitles('?tag=100'));
        $this->assertSame([], $this->listedTitles('?tag=102'));
        $this->assertSame(['Active listening', 'Declining scope creep'], $this->listedTitles('?cheat_sheet=1100'));
        $this->assertSame([], $this->listedTitles('?cheat_sheet=1101'));

        foreach (['?card_type=note', '?tag=abc', '?tag=0', '?cheat_sheet=-1', '?cheat_sheet=99999999999'] as $query) {
            $this->get('/api/reference-cards' . $query);
            $this->assertResponseCode(400, $query);
        }
    }

    public function testPaginationBoundaries(): void
    {
        $this->authenticate();
        $this->assertSame(['Active listening'], $this->listedTitles('?limit=1'));
        $this->assertSame(['Declining scope creep'], $this->listedTitles('?limit=1&page=2'));
        $this->assertSame([], $this->listedTitles('?limit=1&page=3'));
        $this->get('/api/reference-cards?limit=1000');
        $this->assertSame(100, $this->payload()['meta']['pagination']['limit']);
        foreach (['?limit=0', '?page=0', '?page=abc'] as $query) {
            $this->get('/api/reference-cards' . $query);
            $this->assertResponseCode(400, $query);
        }
    }

    public function testCreateConceptCardIgnoresOwnershipFields(): void
    {
        $this->authenticate();
        $this->postJson('/api/reference-cards', [
            'title' => '  Five whys  ',
            'summary' => '',
            'body' => 'Ask why repeatedly.',
            'user_id' => 2,
            'id' => 5,
        ]);

        $this->assertResponseCode(201);
        $card = $this->payload()['data']['card'];
        $this->assertSame('Five whys', $card['title']);
        $this->assertSame('concept', $card['card_type']);
        $this->assertNull($card['summary']);
        $this->assertNotSame(5, $card['id']);
        $stored = TableRegistry::getTableLocator()->get('ReferenceCards')->get($card['id']);
        $this->assertSame(1, (int)$stored->get('user_id'));
        $this->assertSame(['reference_card.created'], $this->actions());
    }

    public function testCreateDialoguePatternCard(): void
    {
        $this->authenticate();
        $this->postJson('/api/reference-cards', [
            'card_type' => 'dialogue_pattern',
            'title' => 'Asking for a decision',
            'situation' => str_repeat('s', 2000),
            'aim' => 'Get a clear yes or no.',
            'approach' => 'State the options.',
            'avoid' => 'Open-ended questions.',
            'rationale' => 'Clear asks get clear answers.',
        ]);

        $this->assertResponseCode(201);
        $card = $this->payload()['data']['card'];
        $this->assertSame('dialogue_pattern', $card['card_type']);
        $this->assertSame(2000, mb_strlen($card['situation']));
    }

    public function testValidationBoundaries(): void
    {
        $this->authenticate();
        $cases = [
            [['title' => str_repeat('t', 255)], 201],
            [['title' => str_repeat('t', 256)], 422],
            [['title' => '   '], 422],
            [[], 422],
            [['title' => 'Summary', 'summary' => str_repeat('s', 500)], 201],
            [['title' => 'Summary', 'summary' => str_repeat('s', 501)], 422],
            [['title' => 'Body', 'body' => str_repeat('b', 65536)], 201],
            [['title' => 'Body', 'body' => str_repeat('b', 65537)], 422],
            [['title' => 'Body', 'body' => str_repeat('é', 32769)], 422],
            [['title' => 'Type', 'card_type' => 'note'], 422],
            [['title' => 'Concept', 'situation' => 'Not allowed here'], 422],
            [['title' => 'Concept', 'card_type' => 'concept', 'rationale' => 'Nope'], 422],
            [['title' => 'Long', 'card_type' => 'dialogue_pattern', 'aim' => str_repeat('a', 2001)], 422],
        ];
        foreach ($cases as $index => [$body, $status]) {
            $this->postJson('/api/reference-cards', $body);
            $this->assertResponseCode($status, 'case ' . $index);
        }
    }

    public function testViewDeniesForeignAndMissingCards(): void
    {
        $this->authenticate();
        $this->get('/api/reference-cards/1001');
        $this->assertResponseOk();
        $this->assertSame('Protect the commitment without damaging trust.', $this->payload()['data']['card']['aim']);

        foreach (['1002', '999999', '99999999999'] as $id) {
            $this->get('/api/reference-cards/' . $id);
            $this->assertResponseCode(404, $id);
        }
        $this->patchJson('/api/reference-cards/1002', ['title' => 'Hijack']);
        $this->assertResponseCode(404);
        $this->delete('/api/reference-cards/1002');
        $this->assertResponseCode(404);
        $this->assertSame('Other user card', TableRegistry::getTableLocator()->get('ReferenceCards')->get(1002)->get('title'));
    }

    public function testEditSwitchingToConceptRequiresClearingDialogueFields(): void
    {
        $this->authenticate();
        $this->patchJson('/api/reference-cards/1001', ['card_type' => 'concept']);
        $this->assertResponseCode(422);

        $this->patchJson('/api/reference-cards/1001', [
            'card_type' => 'concept',
            'situation' => null,
            'aim' => null,
            'approach' => null,
            'avoid' => null,
            'rationale' => null,
            'title' => 'Scope creep',
        ]);
        $this->assertResponseOk();
        $card = $this->payload()['data']['card'];
        $this->assertSame('concept', $card['card_type']);
        $this->assertNull($card['aim']);

        $record = TableRegistry::getTableLocator()->get('ActivityRecords')->find()
            ->where(['actor_user_id' => 1, 'action' => 'reference_card.updated'])
            ->firstOrFail();
        $metadata = json_decode((string)$record->get('metadata'), true);
        $this->assertContains('card_type', $metadata['fields']);
        $this->assertContains('title', $metadata['fields']);
    }

    public function testDeleteRemovesMembershipsAndRenumbersButKeepsSheetsAndTags(): void
    {
        $this->authenticate();
        $this->delete('/api/reference-cards/1001');
        $this->assertResponseOk();

        $locator = TableRegistry::getTableLocator();
        $this->assertFalse($locator->get('ReferenceCards')->exists(['id' => 1001]));
        $this->assertTrue($locator->get('CheatSheets')->exists(['id' => 1100]));
        $memberships = $locator->get('CheatSheetsReferenceCards')->find()
            ->where(['cheat_sheet_id' => 1100])
            ->all()
            ->toList();
        $this->assertCount(1, $memberships);
        $this->assertSame(1000, (int)$memberships[0]->get('reference_card_id'));
        $this->assertSame(1, (int)$memberships[0]->get('position'));

        $this->delete('/api/reference-cards/1000');
        $this->assertResponseOk();
        $this->assertTrue($locator->get('Tags')->exists(['id' => 100]));
        $this->assertFalse($locator->get('ReferenceCardsTags')->exists(['reference_card_id' => 1000]));
        $this->assertSame(['reference_card.deleted', 'reference_card.deleted'], $this->actions());
    }

    public function testTagAttachAndDetachRequireOwnedCardAndTag(): void
    {
        $this->authenticate();
        $this->post('/api/reference-cards/1001/tags/101');
        $this->assertResponseCode(201);
        $this->post('/api/reference-cards/1001/tags/101');
        $this->assertResponseCode(409);
        $this->post('/api/reference-cards/1001/tags/102');
        $this->assertResponseCode(404);
        $this->post('/api/reference-cards/1002/tags/101');
        $this->assertResponseCode(404);
        $this->delete('/api/reference-cards/1001/tags/101');
        $this->assertResponseOk();
        $this->delete('/api/reference-cards/1001/tags/101');
        $this->assertResponseCode(404);
        $this->delete('/api/reference-cards/1002/tags/102');
        $this->assertResponseCode(404);

        $this->assertSame(['reference_card.tag_attached', 'reference_card.tag_detached'], $this->actions());
        $this->assertTrue(TableRegistry::getTableLocator()->get('ReferenceCardsTags')->exists([
            'reference_card_id' => 1002,
            'tag_id' => 102,
        ]));
    }

    public function testDatabaseRejectsInvalidTypeAndConceptDialogueFields(): void
    {
        $connection = TableRegistry::getTableLocator()->get('ReferenceCards')->getConnection();
        foreach (
            [
                "INSERT INTO reference_cards (user_id, card_type, title) VALUES (1, 'note', 'Bad')",
                "INSERT INTO reference_cards (user_id, card_type, title, aim) VALUES (1, 'concept', 'Bad', 'x')",
                "INSERT INTO reference_cards (user_id, title, body) VALUES (1, 'Bad', repeat('b', 65537))",
            ] as $sql
        ) {
            try {
                $connection->execute($sql);
                $this->fail('Expected the database to reject: ' . $sql);
            } catch (QueryException $exception) {
                $this->assertStringContainsString('reference_cards_', $exception->getMessage());
            }
        }
    }

    public function testOwnerDeletionIsRestrictedWhileCardsExist(): void
    {
        $this->expectException(QueryException::class);
        TableRegistry::getTableLocator()->get('ReferenceCards')->getConnection()
            ->execute('DELETE FROM users WHERE id = 2');
    }

    public function testCardsDoNotChangeTodoReviewOrDashboardViews(): void
    {
        $this->authenticate();
        $before = [];
        foreach (['/api/todos', '/api/todos/review-queue', '/api/dashboard'] as $url) {
            $this->get($url);
            $this->assertResponseOk();
            $before[$url] = $this->payload();
        }

        $this->postJson('/api/reference-cards', ['title' => 'Unrelated card']);
        $this->assertResponseCode(201);
        $cardId = $this->payload()['data']['card']['id'];
        $this->post('/api/reference-cards/' . $cardId . '/tags/101');
        $this->assertResponseCode(201);

        foreach ($before as $url => $payload) {
            $this->get($url);
            $after = $this->payload();
            // The dashboard's recent-activity panel legitimately shows the new card's history.
            unset($payload['data']['recent']['activity'], $after['data']['recent']['activity']);
            $this->assertSame($payload, $after, $url);
        }
    }
}
