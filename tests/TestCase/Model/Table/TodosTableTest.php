<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\TodosTable;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class TodosTableTest extends TestCase
{
    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.ActivityRecords',
    ];

    protected TodosTable $Todos;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var \App\Model\Table\TodosTable $todos */
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $this->Todos = $todos;
    }

    protected function tearDown(): void
    {
        unset($this->Todos);

        parent::tearDown();
    }

    public function testValidationRejectsBlankTitle(): void
    {
        $todo = $this->Todos->newEntity([
            'user_id' => 1,
            'title' => '',
            'status' => 'inbox',
        ]);

        $this->assertTrue($todo->hasErrors());
        $this->assertArrayHasKey('title', $todo->getErrors());
    }

    public function testValidationRejectsInvalidStatus(): void
    {
        $todo = $this->Todos->newEntity([
            'user_id' => 1,
            'title' => 'Valid title',
            'status' => 'trash',
        ]);

        $this->assertTrue($todo->hasErrors());
        $this->assertArrayHasKey('status', $todo->getErrors());
    }

    public function testValidTodoSaves(): void
    {
        $todo = $this->Todos->newEntity([
            'user_id' => 1,
            'title' => 'Persisted todo',
            'notes' => 'context',
            'status' => 'inbox',
        ]);

        $this->assertFalse($todo->hasErrors());
        $saved = $this->Todos->save($todo);
        $this->assertNotFalse($saved);
        $this->assertNotNull($saved?->id);
    }
}
