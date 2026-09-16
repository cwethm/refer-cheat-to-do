<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\TagsTable;
use Cake\Database\Exception\QueryException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class TagsTableTest extends TestCase
{
    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Tags',
    ];

    protected TagsTable $Tags;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var \App\Model\Table\TagsTable $tags */
        $tags = TableRegistry::getTableLocator()->get('Tags');
        $this->Tags = $tags;
    }

    protected function tearDown(): void
    {
        unset($this->Tags);

        parent::tearDown();
    }

    public function testValidationRejectsBlankName(): void
    {
        $tag = $this->Tags->newEntity([
            'user_id' => 1,
            'name' => '',
        ], ['accessibleFields' => ['user_id' => true]]);

        $this->assertTrue($tag->hasErrors());
        $this->assertArrayHasKey('name', $tag->getErrors());
    }

    public function testNameNormalizationCollapsesWhitespace(): void
    {
        $tag = $this->Tags->newEntity([
            'user_id' => 1,
            'name' => '  Research   Queue  ',
        ], ['accessibleFields' => ['user_id' => true]]);

        $this->assertFalse($tag->hasErrors());
        $this->assertSame('Research Queue', $tag->name);
    }

    public function testValidationRejectsDuplicateNormalizedNamePerUser(): void
    {
        $tag = $this->Tags->newEntity([
            'user_id' => 1,
            'name' => '  important  ',
        ], ['accessibleFields' => ['user_id' => true]]);

        $saved = $this->Tags->save($tag);
        $this->assertFalse($saved);
        $this->assertArrayHasKey('name', $tag->getErrors());
    }

    public function testAllowsSameTagNameForDifferentUser(): void
    {
        $tag = $this->Tags->newEntity([
            'user_id' => 2,
            'name' => 'Important',
        ], ['accessibleFields' => ['user_id' => true]]);

        $saved = $this->Tags->save($tag);
        $this->assertNotFalse($saved);
    }

    /**
     * @return list<array{0: string}>
     */
    public static function equivalentNameProvider(): array
    {
        return [
            ['Research Queue'],
            [' Research Queue '],
            ['Research   Queue'],
            ['Research     Queue'],
            ['research queue'],
        ];
    }

    #[DataProvider('equivalentNameProvider')]
    public function testEquivalentNamesCollideForSameUser(string $name): void
    {
        $first = $this->Tags->newEntity([
            'user_id' => 1,
            'name' => 'Research Queue',
        ], ['accessibleFields' => ['user_id' => true]]);
        $this->assertNotFalse($this->Tags->save($first));

        $second = $this->Tags->newEntity([
            'user_id' => 1,
            'name' => $name,
        ], ['accessibleFields' => ['user_id' => true]]);

        $this->assertFalse($this->Tags->save($second));
        $this->assertArrayHasKey('name', $second->getErrors());
    }

    #[DataProvider('equivalentNameProvider')]
    public function testDatabaseRejectsEquivalentNamesForSameUser(string $name): void
    {
        $connection = $this->Tags->getConnection();
        $connection->insert('tags', [
            'user_id' => 1,
            'name' => 'Research Queue',
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ]);

        $this->expectException(QueryException::class);
        $connection->insert('tags', [
            'user_id' => 1,
            'name' => $name,
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ]);
    }

    public function testDatabaseAllowsEquivalentNamesForDifferentUsers(): void
    {
        $connection = $this->Tags->getConnection();
        $connection->insert('tags', [
            'user_id' => 1,
            'name' => 'Research Queue',
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ]);
        $connection->insert('tags', [
            'user_id' => 2,
            'name' => 'research   queue',
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ]);

        $this->assertSame(2, $this->Tags->find()->where(['name LIKE' => '%esearch%'])->count());
    }

    public function testNormalizeNameIsTheAuthoritativeRule(): void
    {
        $this->assertSame('research queue', TagsTable::normalizeName('  Research   Queue  '));
        $this->assertSame('Research Queue', TagsTable::normalizeWhitespace('  Research   Queue  '));
    }

    public function testUserIdIsNotMassAssignable(): void
    {
        $tag = $this->Tags->get(100);
        $this->Tags->patchEntity($tag, ['name' => 'Renamed', 'user_id' => 2]);

        $this->assertSame(1, (int)$tag->user_id);
        $this->assertSame('Renamed', $tag->name);
    }
}
