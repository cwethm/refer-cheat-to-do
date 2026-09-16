<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Model\Entity\Todo;
use App\Model\Table\TodosTable;
use App\Service\TodoLifecycleService;
use Cake\Database\Exception\QueryException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;

class TodoLifecycleServiceTest extends TestCase
{
    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Todos',
        'app.Tags',
        'app.TodosTags',
        'app.Projects',
        'app.ProjectSections',
    ];

    protected TodosTable $Todos;

    protected TodoLifecycleService $lifecycle;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var \App\Model\Table\TodosTable $todos */
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $this->Todos = $todos;
        $this->lifecycle = new TodoLifecycleService($todos);
    }

    protected function tearDown(): void
    {
        unset($this->Todos, $this->lifecycle);

        parent::tearDown();
    }

    /**
     * Bring the fixture ToDo into the requested starting status.
     */
    private function todoWithStatus(string $status): Todo
    {
        /** @var \App\Model\Entity\Todo $todo */
        $todo = $this->Todos->get(10);
        if ($status === TodoLifecycleService::STATUS_INBOX) {
            return $todo;
        }

        return $this->lifecycle->transition($todo, $status);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function allowedTransitionProvider(): array
    {
        return [
            ['inbox', 'active'],
            ['inbox', 'done'],
            ['inbox', 'archived'],
            ['inbox', 'trashed'],
            ['active', 'done'],
            ['active', 'archived'],
            ['active', 'trashed'],
            ['done', 'active'],
            ['done', 'archived'],
            ['done', 'trashed'],
            ['archived', 'active'],
            ['archived', 'trashed'],
        ];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function disallowedTransitionProvider(): array
    {
        return [
            ['inbox', 'inbox'],
            ['active', 'active'],
            ['active', 'inbox'],
            ['done', 'inbox'],
            ['done', 'done'],
            ['archived', 'inbox'],
            ['archived', 'done'],
            ['archived', 'archived'],
            ['trashed', 'active'],
            ['trashed', 'done'],
            ['trashed', 'archived'],
            ['trashed', 'inbox'],
            ['trashed', 'trashed'],
        ];
    }

    #[DataProvider('allowedTransitionProvider')]
    public function testAllowedTransitions(string $from, string $to): void
    {
        $todo = $this->todoWithStatus($from);

        $updated = $this->lifecycle->transition($todo, $to);

        $this->assertSame($to, $updated->status);
        $this->assertSame($to, $this->Todos->get(10)->status);
    }

    #[DataProvider('disallowedTransitionProvider')]
    public function testDisallowedTransitions(string $from, string $to): void
    {
        $todo = $this->todoWithStatus($from);

        $this->expectException(DomainException::class);
        $this->lifecycle->transition($todo, $to);
    }

    public function testArchiveSetsArchivedTimestampOnly(): void
    {
        $todo = $this->lifecycle->transition($this->Todos->get(10), 'archived');

        $this->assertNotNull($todo->archived_at);
        $this->assertNull($todo->trashed_at);
    }

    public function testTrashSetsTrashedTimestampAndRemembersPreviousStatus(): void
    {
        $todo = $this->lifecycle->transition($this->Todos->get(10), 'active');
        $todo = $this->lifecycle->transition($todo, 'trashed');

        $this->assertNotNull($todo->trashed_at);
        $this->assertNull($todo->archived_at);
        $this->assertSame('active', $todo->previous_status);
    }

    public function testRestoreReturnsToPreviousStatus(): void
    {
        $todo = $this->lifecycle->transition($this->Todos->get(10), 'done');
        $todo = $this->lifecycle->transition($todo, 'trashed');

        $restored = $this->lifecycle->restore($todo);

        $this->assertSame('done', $restored->status);
        $this->assertNull($restored->trashed_at);
        $this->assertNull($restored->previous_status);
    }

    public function testRestoreFromArchivedTrashRestoresArchivedState(): void
    {
        $todo = $this->lifecycle->transition($this->Todos->get(10), 'archived');
        $todo = $this->lifecycle->transition($todo, 'trashed');

        $restored = $this->lifecycle->restore($todo);

        $this->assertSame('archived', $restored->status);
        $this->assertNotNull($restored->archived_at);
        $this->assertNull($restored->trashed_at);
    }

    public function testRestoreRejectsNonTrashedTodo(): void
    {
        $this->expectException(DomainException::class);
        $this->lifecycle->restore($this->Todos->get(10));
    }

    public function testPermanentDeleteRequiresTrashedTodo(): void
    {
        $todo = $this->lifecycle->transition($this->Todos->get(10), 'archived');

        $this->expectException(DomainException::class);
        $this->lifecycle->permanentlyDelete($todo);
    }

    public function testPermanentDeleteRemovesOnlyTheTargetTodoAndItsJoinRows(): void
    {
        $todo = $this->lifecycle->transition($this->Todos->get(10), 'trashed');
        $this->lifecycle->permanentlyDelete($todo);

        $this->assertFalse($this->Todos->exists(['id' => 10]));
        $this->assertTrue($this->Todos->exists(['id' => 11]));

        $todosTags = TableRegistry::getTableLocator()->get('TodosTags');
        $this->assertFalse($todosTags->exists(['todo_id' => 10]));
        $this->assertTrue($todosTags->exists(['todo_id' => 11]));

        $tags = TableRegistry::getTableLocator()->get('Tags');
        $this->assertTrue($tags->exists(['id' => 100]));
    }

    public function testRelationshipsSurviveTrashAndRestore(): void
    {
        $todo = $this->Todos->get(10);
        $todo->set('project_section_id', 300);
        $this->Todos->saveOrFail($todo);

        $todo = $this->lifecycle->transition($todo, 'trashed');
        $restored = $this->lifecycle->restore($todo);

        $this->assertSame(300, (int)$restored->project_section_id);

        $todosTags = TableRegistry::getTableLocator()->get('TodosTags');
        $this->assertTrue($todosTags->exists(['todo_id' => 10, 'tag_id' => 100]));
    }

    public function testCanTransitionExposesTheMatrix(): void
    {
        $this->assertTrue($this->lifecycle->canTransition('inbox', 'active'));
        $this->assertFalse($this->lifecycle->canTransition('trashed', 'active'));
    }

    public function testDatabaseRejectsArchivedStatusWithoutTimestamp(): void
    {
        $this->expectException(QueryException::class);
        $this->Todos->getConnection()->update('todos', ['status' => 'archived'], ['id' => 10]);
    }

    public function testDatabaseRejectsUnknownStatus(): void
    {
        $this->expectException(QueryException::class);
        $this->Todos->getConnection()->update('todos', ['status' => 'bogus'], ['id' => 10]);
    }
}
