<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Model\Entity\Todo;
use App\Model\Table\RelatedTodosTable;
use App\Model\Table\TodosTable;
use App\Service\TodoLifecycleService;
use App\Service\TodoRelationshipService;
use Cake\Database\Exception\QueryException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use DomainException;

class TodoRelationshipServiceTest extends TestCase
{
    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Todos',
        'app.Tags',
        'app.TodosTags',
        'app.RelatedTodos',
    ];

    protected TodosTable $Todos;

    protected RelatedTodosTable $RelatedTodos;

    protected TodoRelationshipService $relationships;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var \App\Model\Table\TodosTable $todos */
        $todos = TableRegistry::getTableLocator()->get('Todos');
        /** @var \App\Model\Table\RelatedTodosTable $relatedTodos */
        $relatedTodos = TableRegistry::getTableLocator()->get('RelatedTodos');
        $this->Todos = $todos;
        $this->RelatedTodos = $relatedTodos;
        $this->relationships = new TodoRelationshipService($todos, $relatedTodos);
    }

    protected function tearDown(): void
    {
        unset($this->Todos, $this->RelatedTodos, $this->relationships);

        parent::tearDown();
    }

    private function newTodo(int $userId, string $title): Todo
    {
        $todo = $this->Todos->newEntity([
            'user_id' => $userId,
            'title' => $title,
            'status' => 'inbox',
        ]);

        return $this->Todos->saveOrFail($todo);
    }

    public function testRelateStoresCanonicalPair(): void
    {
        $this->relationships->relate($this->Todos->get(11), $this->Todos->get(10));

        $this->assertTrue($this->RelatedTodos->exists(['todo_id' => 10, 'related_todo_id' => 11]));
        $this->assertSame([11], $this->relationships->relatedIds($this->Todos->get(10)));
        $this->assertSame([10], $this->relationships->relatedIds($this->Todos->get(11)));
    }

    public function testRelateRejectsSelfReference(): void
    {
        $todo = $this->Todos->get(10);

        $this->expectException(DomainException::class);
        $this->relationships->relate($todo, $todo);
    }

    public function testRelateRejectsCrossUserTodos(): void
    {
        $this->expectException(DomainException::class);
        $this->relationships->relate($this->Todos->get(10), $this->Todos->get(12));
    }

    public function testRelateRejectsDuplicateInEitherDirection(): void
    {
        $this->relationships->relate($this->Todos->get(10), $this->Todos->get(11));

        $this->expectException(DomainException::class);
        $this->relationships->relate($this->Todos->get(11), $this->Todos->get(10));
    }

    public function testUnrelateRemovesOnlyTheLink(): void
    {
        $this->relationships->relate($this->Todos->get(10), $this->Todos->get(11));
        $this->relationships->unrelate($this->Todos->get(11), $this->Todos->get(10));

        $this->assertSame([], $this->relationships->relatedIds($this->Todos->get(10)));
        $this->assertTrue($this->Todos->exists(['id' => 10]));
        $this->assertTrue($this->Todos->exists(['id' => 11]));
    }

    public function testUnrelateRejectsMissingLink(): void
    {
        $this->expectException(DomainException::class);
        $this->relationships->unrelate($this->Todos->get(10), $this->Todos->get(11));
    }

    public function testDatabaseRejectsSelfRelation(): void
    {
        $this->expectException(QueryException::class);
        $this->RelatedTodos->getConnection()
            ->insert('related_todos', ['todo_id' => 10, 'related_todo_id' => 10]);
    }

    public function testDatabaseRejectsNonCanonicalPairOrder(): void
    {
        $this->expectException(QueryException::class);
        $this->RelatedTodos->getConnection()
            ->insert('related_todos', ['todo_id' => 11, 'related_todo_id' => 10]);
    }

    public function testDatabaseRejectsDuplicatePair(): void
    {
        $this->RelatedTodos->getConnection()
            ->insert('related_todos', ['todo_id' => 10, 'related_todo_id' => 11]);

        $this->expectException(QueryException::class);
        $this->RelatedTodos->getConnection()
            ->insert('related_todos', ['todo_id' => 10, 'related_todo_id' => 11]);
    }

    public function testSetParentCreatesHierarchy(): void
    {
        $child = $this->relationships->setParent($this->Todos->get(11), $this->Todos->get(10));

        $this->assertSame(10, (int)$child->parent_todo_id);
        $this->assertSame([11], $this->relationships->childIds($this->Todos->get(10)));
    }

    public function testSetParentAcceptsNullToDetach(): void
    {
        $this->relationships->setParent($this->Todos->get(11), $this->Todos->get(10));
        $detached = $this->relationships->setParent($this->Todos->get(11), null);

        $this->assertNull($detached->parent_todo_id);
        $this->assertSame([], $this->relationships->childIds($this->Todos->get(10)));
    }

    public function testSetParentRejectsSelfParent(): void
    {
        $todo = $this->Todos->get(10);

        $this->expectException(DomainException::class);
        $this->relationships->setParent($todo, $todo);
    }

    public function testSetParentRejectsCrossUserParent(): void
    {
        $this->expectException(DomainException::class);
        $this->relationships->setParent($this->Todos->get(10), $this->Todos->get(12));
    }

    public function testSetParentRejectsDirectCycle(): void
    {
        $this->relationships->setParent($this->Todos->get(11), $this->Todos->get(10));

        $this->expectException(DomainException::class);
        $this->relationships->setParent($this->Todos->get(10), $this->Todos->get(11));
    }

    public function testSetParentRejectsIndirectCycle(): void
    {
        $grandchild = $this->newTodo(1, 'Grandchild');
        $this->relationships->setParent($this->Todos->get(11), $this->Todos->get(10));
        $this->relationships->setParent($grandchild, $this->Todos->get(11));

        $this->expectException(DomainException::class);
        $this->relationships->setParent($this->Todos->get(10), $this->Todos->get((int)$grandchild->id));
    }

    public function testWouldCreateCycleDetectsSelfAndAncestors(): void
    {
        $this->relationships->setParent($this->Todos->get(11), $this->Todos->get(10));

        $this->assertTrue($this->relationships->wouldCreateCycle(10, 10));
        $this->assertTrue($this->relationships->wouldCreateCycle(10, 11));
        $this->assertFalse($this->relationships->wouldCreateCycle(11, 10));
    }

    public function testDatabaseRejectsSelfParent(): void
    {
        $this->expectException(QueryException::class);
        $this->Todos->getConnection()->update('todos', ['parent_todo_id' => 10], ['id' => 10]);
    }

    public function testSetTerminalObjectiveTrimsAndStores(): void
    {
        $todo = $this->relationships->setTerminalObjective($this->Todos->get(10), '  Answer the question  ');

        $this->assertSame('Answer the question', $todo->terminal_objective);
        $this->assertSame('Answer the question', $this->Todos->get(10)->terminal_objective);
    }

    public function testBlankTerminalObjectiveIsStoredAsNull(): void
    {
        $todo = $this->relationships->setTerminalObjective($this->Todos->get(10), '   ');

        $this->assertNull($todo->terminal_objective);
    }

    public function testTerminalObjectiveLengthIsValidated(): void
    {
        $this->expectException(DomainException::class);
        $this->relationships->setTerminalObjective(
            $this->Todos->get(10),
            str_repeat('a', TodoRelationshipService::MAX_OBJECTIVE_LENGTH + 1),
        );
    }

    public function testReportResultRecordsObjectiveSatisfaction(): void
    {
        $child = $this->relationships->setParent($this->Todos->get(11), $this->Todos->get(10));
        $parentBefore = json_encode($this->Todos->get(10)->toArray());

        $child = $this->relationships->reportResult($child, 'Found the answer');

        $this->assertSame('Found the answer', $child->result_summary);
        $this->assertNotNull($child->objective_satisfied_at);
        $this->assertSame($parentBefore, json_encode($this->Todos->get(10)->toArray()));
    }

    public function testReportResultRequiresParent(): void
    {
        $this->expectException(DomainException::class);
        $this->relationships->reportResult($this->Todos->get(10), 'No parent');
    }

    public function testReportResultRequiresNonEmptySummary(): void
    {
        $child = $this->relationships->setParent($this->Todos->get(11), $this->Todos->get(10));

        $this->expectException(DomainException::class);
        $this->relationships->reportResult($child, '   ');
    }

    public function testReportResultIsNotRepeatable(): void
    {
        $child = $this->relationships->setParent($this->Todos->get(11), $this->Todos->get(10));
        $child = $this->relationships->reportResult($child, 'First');

        $this->expectException(DomainException::class);
        $this->relationships->reportResult($child, 'Second');
    }

    public function testTrashedChildCannotReportResult(): void
    {
        $child = $this->relationships->setParent($this->Todos->get(11), $this->Todos->get(10));
        $lifecycle = new TodoLifecycleService($this->Todos);
        $child = $lifecycle->transition($child, 'trashed');

        $this->expectException(DomainException::class);
        $this->relationships->reportResult($child, 'Too late');
    }

    public function testDatabaseRejectsResultWithoutSatisfiedObjective(): void
    {
        $this->expectException(QueryException::class);
        $this->Todos->getConnection()->update('todos', ['result_summary' => 'orphan result'], ['id' => 10]);
    }

    public function testPermanentDeleteIsBlockedWhileChildrenExist(): void
    {
        $this->relationships->setParent($this->Todos->get(11), $this->Todos->get(10));
        $lifecycle = new TodoLifecycleService($this->Todos);
        $parent = $lifecycle->transition($this->Todos->get(10), 'trashed');

        $this->expectException(DomainException::class);
        $lifecycle->permanentlyDelete($parent);
    }

    public function testHasChildrenReflectsHierarchy(): void
    {
        $this->assertFalse($this->relationships->hasChildren($this->Todos->get(10)));
        $this->relationships->setParent($this->Todos->get(11), $this->Todos->get(10));
        $this->assertTrue($this->relationships->hasChildren($this->Todos->get(10)));
    }

    public function testDeletingTodoRemovesOnlyItsRelationshipRows(): void
    {
        $extra = $this->newTodo(1, 'Third');
        $this->relationships->relate($this->Todos->get(10), $this->Todos->get(11));
        $this->relationships->relate($this->Todos->get(11), $extra);

        $lifecycle = new TodoLifecycleService($this->Todos);
        $trashed = $lifecycle->transition($this->Todos->get(10), 'trashed');
        $lifecycle->permanentlyDelete($trashed);

        $this->assertTrue($this->Todos->exists(['id' => 11]));
        $this->assertSame([(int)$extra->id], $this->relationships->relatedIds($this->Todos->get(11)));
    }

    public function testRelationshipsSurviveArchiveAndRestore(): void
    {
        $this->relationships->relate($this->Todos->get(10), $this->Todos->get(11));
        $lifecycle = new TodoLifecycleService($this->Todos);
        $todo = $lifecycle->transition($this->Todos->get(10), 'archived');
        $todo = $lifecycle->transition($todo, 'trashed');
        $lifecycle->restore($todo);

        $this->assertSame([11], $this->relationships->relatedIds($this->Todos->get(10)));
    }
}
