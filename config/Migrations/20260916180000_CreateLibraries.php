<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateLibraries extends BaseMigration
{
    /**
     * Create libraries and their Project/Notebook membership tables.
     */
    public function up(): void
    {
        $this->table('libraries')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id'], ['name' => 'libraries_user_idx'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
            ])
            ->create();

        $this->table('libraries_projects', ['id' => false, 'primary_key' => ['library_id', 'project_id']])
            ->addColumn('library_id', 'integer', ['null' => false])
            ->addColumn('project_id', 'integer', ['null' => false])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['project_id'], ['name' => 'libraries_projects_project_idx'])
            ->addForeignKey('library_id', 'libraries', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('project_id', 'projects', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();

        $this->table('libraries_notebooks', ['id' => false, 'primary_key' => ['library_id', 'notebook_id']])
            ->addColumn('library_id', 'integer', ['null' => false])
            ->addColumn('notebook_id', 'integer', ['null' => false])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['notebook_id'], ['name' => 'libraries_notebooks_notebook_idx'])
            ->addForeignKey('library_id', 'libraries', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('notebook_id', 'notebooks', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();
    }

    /**
     * Drop libraries and their membership tables.
     */
    public function down(): void
    {
        $this->table('libraries_projects')->drop()->update();
        $this->table('libraries_notebooks')->drop()->update();
        $this->table('libraries')->drop()->update();
    }
}
