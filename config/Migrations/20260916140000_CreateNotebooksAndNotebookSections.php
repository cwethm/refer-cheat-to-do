<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateNotebooksAndNotebookSections extends BaseMigration
{
    /**
     * Create notebook organization tables and the ToDo assignment column.
     */
    public function up(): void
    {
        $this->table('notebooks')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id'], ['name' => 'notebooks_user_idx'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
            ])
            ->create();

        $this->table('notebook_sections')
            ->addColumn('notebook_id', 'integer', ['null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('position', 'integer', ['null' => false])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['notebook_id', 'position'], ['name' => 'notebook_sections_notebook_position_idx'])
            ->addForeignKey('notebook_id', 'notebooks', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
            ])
            ->create();

        $this->table('todos')
            ->addColumn('notebook_section_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['notebook_section_id'], ['name' => 'todos_notebook_section_idx'])
            ->addForeignKey('notebook_section_id', 'notebook_sections', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
            ])
            ->update();

        // ADR 0001: a ToDo belongs to at most one organizational section.
        $this->execute(
            'ALTER TABLE todos ADD CONSTRAINT todos_single_section_chk '
            . 'CHECK (project_section_id IS NULL OR notebook_section_id IS NULL)',
        );
    }

    /**
     * Revert notebook organization tables and the ToDo assignment column.
     */
    public function down(): void
    {
        $this->execute('ALTER TABLE todos DROP CONSTRAINT IF EXISTS todos_single_section_chk');

        $this->table('todos')
            ->dropForeignKey('notebook_section_id')
            ->removeIndexByName('todos_notebook_section_idx')
            ->removeColumn('notebook_section_id')
            ->update();

        $this->table('notebook_sections')->drop()->save();
        $this->table('notebooks')->drop()->save();
    }
}
