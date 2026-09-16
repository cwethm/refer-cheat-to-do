<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateTagsAndTodosTags extends BaseMigration
{
    /**
     * Create tags and todos_tags tables for slice 3.
     */
    public function up(): void
    {
        $this->table('tags')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id'], ['name' => 'tags_user_idx'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
            ])
            ->create();

        $adapterClass = strtolower(get_class($this->getAdapter()));
        if (str_contains($adapterClass, 'postgres')) {
            $this->execute(
                "CREATE UNIQUE INDEX tags_user_name_normalized_unique ON tags (user_id, lower(regexp_replace(trim(name), '\\s+', ' ', 'g')))",
            );
        } else {
            $this->execute(
                'CREATE UNIQUE INDEX tags_user_name_normalized_unique ON tags (user_id, lower(trim(name)))',
            );
        }

        $this->table('todos_tags')
            ->addColumn('todo_id', 'integer', ['null' => false])
            ->addColumn('tag_id', 'integer', ['null' => false])
            ->addIndex(['todo_id', 'tag_id'], ['unique' => true, 'name' => 'todos_tags_unique'])
            ->addIndex(['tag_id', 'todo_id'], ['name' => 'todos_tags_tag_todo_idx'])
            ->addForeignKey('todo_id', 'todos', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('tag_id', 'tags', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();
    }

    /**
     * Revert tags and todos_tags tables.
     */
    public function down(): void
    {
        $this->execute('DROP INDEX IF EXISTS tags_user_name_normalized_unique');
        $this->table('todos_tags')->drop()->save();
        $this->table('tags')->drop()->save();
    }
}
