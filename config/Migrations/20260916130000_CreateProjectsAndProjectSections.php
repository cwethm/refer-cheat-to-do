<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateProjectsAndProjectSections extends BaseMigration
{
    /**
     * Create project organization tables and the ToDo assignment column.
     */
    public function up(): void
    {
        $this->table('projects')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id'], ['name' => 'projects_user_idx'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
            ])
            ->create();

        $this->table('project_sections')
            ->addColumn('project_id', 'integer', ['null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('position', 'integer', ['null' => false])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['project_id', 'position'], ['name' => 'project_sections_project_position_idx'])
            ->addForeignKey('project_id', 'projects', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
            ])
            ->create();

        $this->table('todos')
            ->addColumn('project_section_id', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['project_section_id'], ['name' => 'todos_project_section_idx'])
            ->addForeignKey('project_section_id', 'project_sections', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
            ])
            ->update();
    }

    /**
     * Revert project organization tables and the ToDo assignment column.
     */
    public function down(): void
    {
        $this->table('todos')
            ->dropForeignKey('project_section_id')
            ->removeIndexByName('todos_project_section_idx')
            ->removeColumn('project_section_id')
            ->update();

        $this->table('project_sections')->drop()->save();
        $this->table('projects')->drop()->save();
    }
}
