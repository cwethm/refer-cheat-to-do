<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateTodoRelationships extends BaseMigration
{
    /**
     * Add parent/child, terminal objective, and related ToDo structures.
     */
    public function up(): void
    {
        $this->table('todos')
            ->addColumn('parent_todo_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('terminal_objective', 'text', ['null' => true, 'default' => null])
            ->addColumn('objective_satisfied_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('result_summary', 'text', ['null' => true, 'default' => null])
            ->addIndex(['parent_todo_id'], ['name' => 'todos_parent_idx'])
            ->addForeignKey('parent_todo_id', 'todos', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
                'constraint' => 'todos_parent_fk',
            ])
            ->update();

        $this->execute('ALTER TABLE todos ADD CONSTRAINT todos_parent_self_chk CHECK (parent_todo_id <> id)');
        $this->execute(
            'ALTER TABLE todos ADD CONSTRAINT todos_result_requires_objective_chk '
            . 'CHECK (result_summary IS NULL OR objective_satisfied_at IS NOT NULL)',
        );

        $this->table('related_todos')
            ->addColumn('todo_id', 'integer', ['null' => false])
            ->addColumn('related_todo_id', 'integer', ['null' => false])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['todo_id', 'related_todo_id'], [
                'unique' => true,
                'name' => 'related_todos_pair_unique',
            ])
            ->addIndex(['related_todo_id'], ['name' => 'related_todos_related_idx'])
            ->addForeignKey('todo_id', 'todos', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'related_todos_todo_fk',
            ])
            ->addForeignKey('related_todo_id', 'todos', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'related_todos_related_fk',
            ])
            ->create();

        $this->execute(
            'ALTER TABLE related_todos ADD CONSTRAINT related_todos_order_chk CHECK (todo_id < related_todo_id)',
        );
    }

    /**
     * Remove parent/child, terminal objective, and related ToDo structures.
     */
    public function down(): void
    {
        $this->table('related_todos')->drop()->update();

        $this->execute('ALTER TABLE todos DROP CONSTRAINT IF EXISTS todos_parent_self_chk');
        $this->execute('ALTER TABLE todos DROP CONSTRAINT IF EXISTS todos_result_requires_objective_chk');

        $this->table('todos')
            ->dropForeignKey('parent_todo_id')
            ->removeIndexByName('todos_parent_idx')
            ->removeColumn('parent_todo_id')
            ->removeColumn('terminal_objective')
            ->removeColumn('objective_satisfied_at')
            ->removeColumn('result_summary')
            ->update();
    }
}
