<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddTodoLifecycleFields extends BaseMigration
{
    /**
     * Add archive/trash lifecycle bookkeeping to todos.
     */
    public function up(): void
    {
        $this->table('todos')
            ->addColumn('archived_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('trashed_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('previous_status', 'string', ['limit' => 20, 'null' => true, 'default' => null])
            ->addIndex(['user_id', 'status', 'id'], ['name' => 'todos_user_status_id_idx'])
            ->update();

        $this->execute(
            "ALTER TABLE todos ADD CONSTRAINT todos_status_chk "
            . "CHECK (status IN ('inbox', 'active', 'done', 'archived', 'trashed'))",
        );
        $this->execute(
            'ALTER TABLE todos ADD CONSTRAINT todos_archived_at_chk '
            . "CHECK ((status = 'archived') = (archived_at IS NOT NULL))",
        );
        $this->execute(
            'ALTER TABLE todos ADD CONSTRAINT todos_trashed_at_chk '
            . "CHECK ((status = 'trashed') = (trashed_at IS NOT NULL))",
        );
    }

    /**
     * Remove archive/trash lifecycle bookkeeping from todos.
     */
    public function down(): void
    {
        $this->execute("UPDATE todos SET status = 'inbox' WHERE status IN ('done', 'archived', 'trashed')");
        $this->execute('ALTER TABLE todos DROP CONSTRAINT IF EXISTS todos_status_chk');
        $this->execute('ALTER TABLE todos DROP CONSTRAINT IF EXISTS todos_archived_at_chk');
        $this->execute('ALTER TABLE todos DROP CONSTRAINT IF EXISTS todos_trashed_at_chk');

        $this->table('todos')
            ->removeIndexByName('todos_user_status_id_idx')
            ->removeColumn('archived_at')
            ->removeColumn('trashed_at')
            ->removeColumn('previous_status')
            ->update();
    }
}
