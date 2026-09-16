<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\TodosTagsTable;
use Cake\Database\Exception\QueryException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class TodosTagsTableTest extends TestCase
{
    /**
     * @var list<string>
     */
    protected array $fixtures = [
        'app.Users',
        'app.Todos',
        'app.Tags',
        'app.TodosTags',
    ];

    protected TodosTagsTable $TodosTags;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var \App\Model\Table\TodosTagsTable $todosTags */
        $todosTags = TableRegistry::getTableLocator()->get('TodosTags');
        $this->TodosTags = $todosTags;
    }

    protected function tearDown(): void
    {
        unset($this->TodosTags);

        parent::tearDown();
    }

    public function testAttachIfMissingCreatesRelationship(): void
    {
        $this->assertTrue($this->TodosTags->attachIfMissing(10, 101));
        $this->assertTrue($this->TodosTags->exists(['todo_id' => 10, 'tag_id' => 101]));
    }

    public function testAttachIfMissingReportsExistingRelationship(): void
    {
        $this->assertFalse($this->TodosTags->attachIfMissing(10, 100));
        $this->assertSame(1, $this->TodosTags->find()->where(['todo_id' => 10, 'tag_id' => 100])->count());
    }

    public function testAttachIfMissingIsIdempotentAcrossRepeatedCalls(): void
    {
        $this->assertTrue($this->TodosTags->attachIfMissing(11, 100));
        $this->assertFalse($this->TodosTags->attachIfMissing(11, 100));
        $this->assertSame(1, $this->TodosTags->find()->where(['todo_id' => 11, 'tag_id' => 100])->count());
    }

    public function testDatabaseRejectsDuplicateRelationshipRow(): void
    {
        $this->expectException(QueryException::class);
        $this->TodosTags->getConnection()->insert('todos_tags', [
            'todo_id' => 10,
            'tag_id' => 100,
        ]);
    }

    public function testDatabaseRejectsUnknownForeignKeys(): void
    {
        $this->expectException(QueryException::class);
        $this->TodosTags->getConnection()->insert('todos_tags', [
            'todo_id' => 10,
            'tag_id' => 99999,
        ]);
    }

    public function testDeletingTagCascadesOnlyToRelationship(): void
    {
        $tags = TableRegistry::getTableLocator()->get('Tags');
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $tags->deleteOrFail($tags->get(100));

        $this->assertFalse($this->TodosTags->exists(['tag_id' => 100]));
        $this->assertTrue($todos->exists(['id' => 10]));
    }

    public function testRelationshipSurvivesTodoUpdates(): void
    {
        $todos = TableRegistry::getTableLocator()->get('Todos');
        $todo = $todos->get(10);
        $todos->saveOrFail($todos->patchEntity($todo, ['title' => 'Updated title']));

        $this->assertTrue($this->TodosTags->exists(['todo_id' => 10, 'tag_id' => 100]));
    }
}
