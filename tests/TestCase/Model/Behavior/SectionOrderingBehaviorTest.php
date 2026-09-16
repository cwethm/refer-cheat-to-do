<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Behavior;

use App\Model\Behavior\SectionOrderingBehavior;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Proves the extracted ordering component behaves identically for both section domains.
 */
class SectionOrderingBehaviorTest extends TestCase
{
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
    ];

    /**
     * @return list<array{0: string, 1: string, 2: int, 3: list<int>}>
     */
    public static function sectionDomainProvider(): array
    {
        return [
            ['ProjectSections', 'project_id', 200, [300, 301, 302]],
            ['NotebookSections', 'notebook_id', 400, [500, 501, 502]],
        ];
    }

    /**
     * @return list<int>
     */
    private function orderedIds(Table $table, string $parentField, int $parentId): array
    {
        $ids = [];
        $query = $table->find()
            ->where([$parentField => $parentId])
            ->orderBy(['position' => 'ASC', 'id' => 'ASC']);
        foreach ($query as $section) {
            $ids[] = (int)$section->get('id');
        }

        return $ids;
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('sectionDomainProvider')]
    public function testBehaviorIsConfiguredForEachSectionDomain(
        string $tableName,
        string $parentField,
        int $parentId,
        array $expected,
    ): void {
        $table = TableRegistry::getTableLocator()->get($tableName);
        $behavior = $table->getBehavior('SectionOrdering');

        $this->assertInstanceOf(SectionOrderingBehavior::class, $behavior);
        $this->assertSame($parentField, $behavior->getConfig('parentField'));
        $this->assertSame($expected, $this->orderedIds($table, $parentField, $parentId));
    }

    /**
     * @param list<int> $ids
     */
    #[DataProvider('sectionDomainProvider')]
    public function testMoveToPositionBehavesIdenticallyForBothDomains(
        string $tableName,
        string $parentField,
        int $parentId,
        array $ids,
    ): void {
        $table = TableRegistry::getTableLocator()->get($tableName);
        /** @var \App\Model\Behavior\SectionOrderingBehavior $behavior */
        $behavior = $table->getBehavior('SectionOrdering');

        $behavior->moveToPosition($table->get($ids[2]), 1);

        $this->assertSame(
            [$ids[2], $ids[0], $ids[1]],
            $this->orderedIds($table, $parentField, $parentId),
        );
    }

    /**
     * @param list<int> $ids
     */
    #[DataProvider('sectionDomainProvider')]
    public function testInvalidPositionIsRejectedForBothDomains(
        string $tableName,
        string $parentField,
        int $parentId,
        array $ids,
    ): void {
        $table = TableRegistry::getTableLocator()->get($tableName);
        /** @var \App\Model\Behavior\SectionOrderingBehavior $behavior */
        $behavior = $table->getBehavior('SectionOrdering');

        $this->expectException(InvalidArgumentException::class);
        $behavior->moveToPosition($table->get($ids[0]), 99);
    }

    /**
     * @param list<int> $ids
     */
    #[DataProvider('sectionDomainProvider')]
    public function testRenumberPositionsClosesGapsForBothDomains(
        string $tableName,
        string $parentField,
        int $parentId,
        array $ids,
    ): void {
        $table = TableRegistry::getTableLocator()->get($tableName);
        /** @var \App\Model\Behavior\SectionOrderingBehavior $behavior */
        $behavior = $table->getBehavior('SectionOrdering');

        $table->deleteOrFail($table->get($ids[0]));
        $behavior->renumberPositions($parentId);

        $this->assertSame(1, (int)$table->get($ids[1])->get('position'));
        $this->assertSame(2, (int)$table->get($ids[2])->get('position'));
    }

    /**
     * @param list<int> $ids
     */
    #[DataProvider('sectionDomainProvider')]
    public function testOrderingIsScopedToItsOwnParent(
        string $tableName,
        string $parentField,
        int $parentId,
        array $ids,
    ): void {
        $table = TableRegistry::getTableLocator()->get($tableName);
        /** @var \App\Model\Behavior\SectionOrderingBehavior $behavior */
        $behavior = $table->getBehavior('SectionOrdering');

        $behavior->moveToPosition($table->get($ids[0]), 3);

        $otherParentSections = $table->find()->where([$parentField . ' !=' => $parentId])->all();
        foreach ($otherParentSections as $section) {
            $this->assertSame(1, (int)$section->get('position'));
        }
    }
}
