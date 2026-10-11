<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Api;

use App\Test\TestCase\SignedInSessionTrait;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class CheatSheetsControllerTest extends TestCase
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
        'app.Tags',
        'app.ReferenceCards',
        'app.ReferenceCardsTags',
        'app.CheatSheets',
        'app.CheatSheetsReferenceCards',
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

    private function deleteWithCsrf(string $url): void
    {
        $this->replaceRequest(['headers' => $this->csrfHeaders()]);
        $this->delete($url);
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function order(int $sheetId = 1100): array
    {
        $rows = [];
        $query = TableRegistry::getTableLocator()->get('CheatSheetsReferenceCards')->find()
            ->where(['cheat_sheet_id' => $sheetId])
            ->orderBy(['position' => 'ASC']);
        foreach ($query as $row) {
            $rows[] = [(int)$row->get('reference_card_id'), (int)$row->get('position')];
        }

        return $rows;
    }

    /**
     * Create an extra card for user one and return its id.
     */
    private function createCard(string $title): int
    {
        $this->postJson('/api/reference-cards', ['title' => $title]);
        $this->assertResponseCode(201);

        return (int)$this->payload()['data']['card']['id'];
    }

    /**
     * @return list<string>
     */
    private function actions(): array
    {
        return TableRegistry::getTableLocator()->get('ActivityRecords')->find()
            ->where(['actor_user_id' => 1, 'subject_type' => 'cheat_sheet'])
            ->orderBy(['id' => 'ASC'])
            ->all()
            ->extract('action')
            ->toList();
    }

    public function testAnonymousAccessIsDeniedAcrossAllEndpoints(): void
    {
        $this->configRequest(['headers' => $this->csrfHeaders()]);
        $requests = [
            ['get', '/api/cheat-sheets'],
            ['post', '/api/cheat-sheets'],
            ['get', '/api/cheat-sheets/1100'],
            ['patch', '/api/cheat-sheets/1100'],
            ['delete', '/api/cheat-sheets/1100'],
            ['get', '/api/cheat-sheets/1100/cards'],
            ['post', '/api/cheat-sheets/1100/cards'],
            ['patch', '/api/cheat-sheets/1100/cards/1000'],
            ['delete', '/api/cheat-sheets/1100/cards/1000'],
        ];
        foreach ($requests as [$method, $url]) {
            $this->replaceRequest(['headers' => $this->csrfHeaders()]);
            $this->{$method}($url);
            $this->assertResponseCode(401, $method . ' ' . $url);
        }
    }

    public function testCheatSheetMutationRequiresCsrfToken(): void
    {
        $this->replaceRequest([
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        ]);
        $this->post('/api/cheat-sheets', (string)json_encode(['name' => 'Without CSRF']));

        $this->assertResponseCode(403);
    }

    public function testIndexListsOnlyOwnedSheetsWithCardCounts(): void
    {
        $this->authenticate();
        $this->get('/api/cheat-sheets');
        $this->assertResponseOk();
        $items = $this->payload()['data']['items'];

        $this->assertCount(1, $items);
        $this->assertSame('Meeting sheet', $items[0]['name']);
        $this->assertSame(2, $items[0]['card_count']);
        $this->assertSame(1, $this->payload()['meta']['pagination']['total']);
    }

    public function testCreateEditAndValidate(): void
    {
        $this->authenticate();
        $this->postJson('/api/cheat-sheets', ['name' => 'Interview prep', 'user_id' => 2]);
        $this->assertResponseCode(201);
        $sheet = $this->payload()['data']['cheat_sheet'];
        $this->assertSame(0, $sheet['card_count']);
        $this->assertSame(
            1,
            (int)TableRegistry::getTableLocator()->get('CheatSheets')->get($sheet['id'])->get('user_id'),
        );

        $this->patchJson('/api/cheat-sheets/' . $sheet['id'], ['name' => 'Interview cards', 'description' => 'Notes']);
        $this->assertResponseOk();
        $this->assertSame('Interview cards', $this->payload()['data']['cheat_sheet']['name']);

        foreach ([[], ['name' => ''], ['name' => str_repeat('n', 256)]] as $body) {
            $this->postJson('/api/cheat-sheets', $body);
            $this->assertResponseCode(422);
        }
        $this->assertSame(['cheat_sheet.created', 'cheat_sheet.updated'], $this->actions());
    }

    public function testForeignAndMissingSheetsAreNotFound(): void
    {
        $this->authenticate();
        foreach (['1101', '999999', '99999999999'] as $id) {
            $this->get('/api/cheat-sheets/' . $id);
            $this->assertResponseCode(404, $id);
            $this->get('/api/cheat-sheets/' . $id . '/cards');
            $this->assertResponseCode(404, $id);
        }
        $this->patchJson('/api/cheat-sheets/1101', ['name' => 'Hijack']);
        $this->assertResponseCode(404);
        $this->postJson('/api/cheat-sheets/1101/cards', ['reference_card_id' => 1000]);
        $this->assertResponseCode(404);
        $this->patchJson('/api/cheat-sheets/1101/cards/1002', ['position' => 1]);
        $this->assertResponseCode(404);
        $this->deleteWithCsrf('/api/cheat-sheets/1101/cards/1002');
        $this->assertResponseCode(404);
        $this->deleteWithCsrf('/api/cheat-sheets/1101');
        $this->assertResponseCode(404);
        $this->assertSame([[1002, 1]], $this->order(1101));
    }

    public function testCardsAreListedInSheetOrder(): void
    {
        $this->authenticate();
        $this->get('/api/cheat-sheets/1100/cards');
        $this->assertResponseOk();
        $items = $this->payload()['data']['items'];

        $this->assertSame([1001, 1000], array_column($items, 'reference_card_id'));
        $this->assertSame([1, 2], array_column($items, 'position'));
        $this->assertSame('Declining scope creep', $items[0]['card']['title']);
        $this->assertSame([['id' => 100, 'name' => 'Important']], $items[1]['card']['tags']);

        $this->get('/api/cheat-sheets/1100/cards?limit=1&page=2');
        $this->assertSame([1000], array_column($this->payload()['data']['items'], 'reference_card_id'));
        $this->assertSame(2, $this->payload()['meta']['pagination']['total']);
    }

    public function testSheetPositionsHaveDeferredUniqueConstraint(): void
    {
        $constraint = TableRegistry::getTableLocator()->get('CheatSheetsReferenceCards')
            ->getConnection()
            ->execute(
                'SELECT condeferrable::text AS deferrable, condeferred::text AS deferred '
                . 'FROM pg_constraint WHERE conname = ?',
                ['cheat_sheets_reference_cards_sheet_position_unique'],
            )
            ->fetch('assoc');

        $this->assertSame(['deferrable' => 'true', 'deferred' => 'true'], $constraint);
    }

    public function testAddCardAppendsOrInsertsAndAllowsMultipleSheets(): void
    {
        $this->authenticate();
        $first = $this->createCard('Appended');
        $second = $this->createCard('Inserted');

        $this->postJson('/api/cheat-sheets/1100/cards', ['reference_card_id' => $first]);
        $this->assertResponseCode(201);
        $this->assertSame(3, $this->payload()['data']['membership']['position']);

        $this->postJson('/api/cheat-sheets/1100/cards', ['reference_card_id' => (string)$second, 'position' => 1]);
        $this->assertResponseCode(201);
        $this->assertSame([[$second, 1], [1001, 2], [1000, 3], [$first, 4]], $this->order());

        $this->postJson('/api/cheat-sheets', ['name' => 'Second sheet']);
        $otherSheet = (int)$this->payload()['data']['cheat_sheet']['id'];
        $this->postJson('/api/cheat-sheets/' . $otherSheet . '/cards', ['reference_card_id' => $first]);
        $this->assertResponseCode(201);
        $this->assertSame([[$first, 1]], $this->order($otherSheet));
        $this->assertSame(
            ['cheat_sheet.card_added', 'cheat_sheet.card_added', 'cheat_sheet.created', 'cheat_sheet.card_added'],
            $this->actions(),
        );
    }

    public function testAddCardRejectsForeignDuplicateAndInvalidInput(): void
    {
        $this->authenticate();
        $this->postJson('/api/cheat-sheets/1100/cards', ['reference_card_id' => 1002]);
        $this->assertResponseCode(404);
        $this->postJson('/api/cheat-sheets/1100/cards', ['reference_card_id' => 999999]);
        $this->assertResponseCode(404);
        $this->postJson('/api/cheat-sheets/1100/cards', ['reference_card_id' => 1000]);
        $this->assertResponseCode(409);
        $this->postJson('/api/cheat-sheets/1100/cards', []);
        $this->assertResponseCode(422);
        $this->postJson('/api/cheat-sheets/1100/cards', ['reference_card_id' => ['x']]);
        $this->assertResponseCode(422);

        $card = $this->createCard('Positioned');
        foreach ([0, 4, 'abc', -1, 1.5] as $position) {
            $this->postJson('/api/cheat-sheets/1100/cards', ['reference_card_id' => $card, 'position' => $position]);
            $this->assertResponseCode(400, (string)json_encode($position));
        }
        $this->assertSame([[1001, 1], [1000, 2]], $this->order());
        $this->assertSame([], $this->actions());
    }

    public function testMoveCardReordersAndValidates(): void
    {
        $this->authenticate();
        $this->patchJson('/api/cheat-sheets/1100/cards/1000', ['position' => 1]);
        $this->assertResponseOk();
        $this->assertSame(1, $this->payload()['data']['membership']['position']);
        $this->assertSame([[1000, 1], [1001, 2]], $this->order());

        foreach ([0, 3, 'x'] as $position) {
            $this->patchJson('/api/cheat-sheets/1100/cards/1000', ['position' => $position]);
            $this->assertResponseCode(400);
        }
        $this->patchJson('/api/cheat-sheets/1100/cards/1000', []);
        $this->assertResponseCode(400);
        $this->patchJson('/api/cheat-sheets/1100/cards/1002', ['position' => 1]);
        $this->assertResponseCode(404);
        $this->assertSame([[1000, 1], [1001, 2]], $this->order());
        $this->assertSame(['cheat_sheet.card_moved'], $this->actions());
    }

    public function testRemoveCardRenumbersAndKeepsCard(): void
    {
        $this->authenticate();
        $this->deleteWithCsrf('/api/cheat-sheets/1100/cards/1001');
        $this->assertResponseOk();
        $this->assertSame([[1000, 1]], $this->order());
        $this->assertTrue(TableRegistry::getTableLocator()->get('ReferenceCards')->exists(['id' => 1001]));

        $this->deleteWithCsrf('/api/cheat-sheets/1100/cards/1001');
        $this->assertResponseCode(404);
        $this->assertSame(['cheat_sheet.card_removed'], $this->actions());
    }

    public function testDeleteSheetKeepsCards(): void
    {
        $this->authenticate();
        $this->deleteWithCsrf('/api/cheat-sheets/1100');
        $this->assertResponseOk();

        $locator = TableRegistry::getTableLocator();
        $this->assertFalse($locator->get('CheatSheets')->exists(['id' => 1100]));
        $this->assertSame([], $this->order());
        $this->assertSame(2, $locator->get('ReferenceCards')->find()->where(['user_id' => 1])->count());
        $this->assertSame(['cheat_sheet.deleted'], $this->actions());
    }
}
