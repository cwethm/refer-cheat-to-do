<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\NotebookSectionsTable;
use Cake\Database\Exception\QueryException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

class NotebookSectionsTableTest extends TestCase
{
    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.ActivityRecords',
        'app.Notebooks',
        'app.NotebookSections',
        'app.Todos',
    ];

    protected NotebookSectionsTable $NotebookSections;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var \App\Model\Table\NotebookSectionsTable $sections */
        $sections = TableRegistry::getTableLocator()->get('NotebookSections');
        $this->NotebookSections = $sections;
    }

    protected function tearDown(): void
    {
        unset($this->NotebookSections);

        parent::tearDown();
    }

    /**
     * @return list<int>
     */
    private function orderedIds(int $notebookId): array
    {
        $ids = [];
        $query = $this->NotebookSections->find()
            ->where(['notebook_id' => $notebookId])
            ->orderBy(['position' => 'ASC', 'id' => 'ASC']);
        foreach ($query as $section) {
            $ids[] = (int)$section->id;
        }

        return $ids;
    }

    public function testNextPositionAppendsAfterExistingSections(): void
    {
        $this->assertSame(4, $this->NotebookSections->nextPosition(400));
    }

    public function testNextPositionStartsAtOneForEmptyNotebook(): void
    {
        $notebooks = TableRegistry::getTableLocator()->get('Notebooks');
        $notebook = $notebooks->newEmptyEntity();
        $notebook->set('user_id', 1);
        $notebook->set('name', 'Empty notebook');
        $notebooks->saveOrFail($notebook);

        $this->assertSame(1, $this->NotebookSections->nextPosition((int)$notebook->id));
    }

    public function testMoveToPositionMovesSectionUp(): void
    {
        $section = $this->NotebookSections->get(502);
        $this->NotebookSections->moveToPosition($section, 1);

        $this->assertSame([502, 500, 501], $this->orderedIds(400));
    }

    public function testMoveToPositionMovesSectionDown(): void
    {
        $section = $this->NotebookSections->get(500);
        $this->NotebookSections->moveToPosition($section, 3);

        $this->assertSame([501, 502, 500], $this->orderedIds(400));
    }

    public function testMoveToSamePositionIsANoOp(): void
    {
        $section = $this->NotebookSections->get(501);
        $this->NotebookSections->moveToPosition($section, 2);

        $this->assertSame([500, 501, 502], $this->orderedIds(400));
    }

    public function testMoveToPositionRejectsOutOfRangeValues(): void
    {
        $section = $this->NotebookSections->get(501);

        $this->expectException(InvalidArgumentException::class);
        $this->NotebookSections->moveToPosition($section, 4);
    }

    public function testMoveToPositionRejectsZero(): void
    {
        $section = $this->NotebookSections->get(501);

        $this->expectException(InvalidArgumentException::class);
        $this->NotebookSections->moveToPosition($section, 0);
    }

    public function testNotebookIdAndPositionAreNotMassAssignable(): void
    {
        $section = $this->NotebookSections->get(500);
        $this->NotebookSections->patchEntity($section, [
            'name' => 'Renamed',
            'notebook_id' => 401,
            'position' => 99,
        ]);

        $this->assertSame(400, (int)$section->notebook_id);
        $this->assertSame(1, (int)$section->position);
        $this->assertSame('Renamed', $section->name);
    }

    public function testDatabaseRejectsSectionDeletionWhileTodosReferenceIt(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->get(10);
        $todo->set('notebook_section_id', 500);
        $todos->saveOrFail($todo);

        $this->expectException(QueryException::class);
        $this->NotebookSections->getConnection()->delete('notebook_sections', ['id' => 500]);
    }
}
