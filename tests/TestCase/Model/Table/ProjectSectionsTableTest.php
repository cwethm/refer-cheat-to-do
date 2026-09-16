<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\ProjectSectionsTable;
use Cake\Database\Exception\QueryException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

class ProjectSectionsTableTest extends TestCase
{
    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Projects',
        'app.ProjectSections',
        'app.Todos',
    ];

    protected ProjectSectionsTable $ProjectSections;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var \App\Model\Table\ProjectSectionsTable $sections */
        $sections = TableRegistry::getTableLocator()->get('ProjectSections');
        $this->ProjectSections = $sections;
    }

    protected function tearDown(): void
    {
        unset($this->ProjectSections);

        parent::tearDown();
    }

    /**
     * @return list<int>
     */
    private function orderedIds(int $projectId): array
    {
        $ids = [];
        $query = $this->ProjectSections->find()
            ->where(['project_id' => $projectId])
            ->orderBy(['position' => 'ASC', 'id' => 'ASC']);
        foreach ($query as $section) {
            $ids[] = (int)$section->id;
        }

        return $ids;
    }

    public function testNextPositionAppendsAfterExistingSections(): void
    {
        $this->assertSame(4, $this->ProjectSections->nextPosition(200));
    }

    public function testNextPositionStartsAtOneForEmptyProject(): void
    {
        $projects = TableRegistry::getTableLocator()->get('Projects');
        $project = $projects->newEmptyEntity();
        $project->set('user_id', 1);
        $project->set('name', 'Empty project');
        $projects->saveOrFail($project);

        $this->assertSame(1, $this->ProjectSections->nextPosition((int)$project->id));
    }

    public function testMoveToPositionMovesSectionUp(): void
    {
        $section = $this->ProjectSections->get(302);
        $this->ProjectSections->moveToPosition($section, 1);

        $this->assertSame([302, 300, 301], $this->orderedIds(200));
    }

    public function testMoveToPositionMovesSectionDown(): void
    {
        $section = $this->ProjectSections->get(300);
        $this->ProjectSections->moveToPosition($section, 3);

        $this->assertSame([301, 302, 300], $this->orderedIds(200));
    }

    public function testMoveToSamePositionIsANoOp(): void
    {
        $section = $this->ProjectSections->get(301);
        $this->ProjectSections->moveToPosition($section, 2);

        $this->assertSame([300, 301, 302], $this->orderedIds(200));
    }

    public function testMoveToPositionRejectsOutOfRangeValues(): void
    {
        $section = $this->ProjectSections->get(301);

        $this->expectException(InvalidArgumentException::class);
        $this->ProjectSections->moveToPosition($section, 4);
    }

    public function testMoveToPositionRejectsZero(): void
    {
        $section = $this->ProjectSections->get(301);

        $this->expectException(InvalidArgumentException::class);
        $this->ProjectSections->moveToPosition($section, 0);
    }

    public function testProjectIdAndPositionAreNotMassAssignable(): void
    {
        $section = $this->ProjectSections->get(300);
        $this->ProjectSections->patchEntity($section, [
            'name' => 'Renamed',
            'project_id' => 201,
            'position' => 99,
        ]);

        $this->assertSame(200, (int)$section->project_id);
        $this->assertSame(1, (int)$section->position);
        $this->assertSame('Renamed', $section->name);
    }

    public function testDatabaseRejectsSectionDeletionWhileTodosReferenceIt(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->get(10);
        $todo->set('project_section_id', 300);
        $todos->saveOrFail($todo);

        $this->expectException(QueryException::class);
        $this->ProjectSections->getConnection()->delete('project_sections', ['id' => 300]);
    }
}
