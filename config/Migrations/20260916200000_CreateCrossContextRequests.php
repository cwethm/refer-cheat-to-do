<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateCrossContextRequests extends BaseMigration
{
    /**
     * Create the cross-context request table.
     */
    public function up(): void
    {
        $this->table('cross_context_requests')
            ->addColumn('source_type', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('source_id', 'integer', ['null' => false])
            ->addColumn('target_type', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('target_id', 'integer', ['null' => false])
            ->addColumn('request_type', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('body', 'text', ['null' => true])
            ->addColumn('status', 'string', ['limit' => 32, 'null' => false, 'default' => 'pending'])
            ->addColumn('created_by_user_id', 'integer', ['null' => false])
            ->addColumn('resolved_by_user_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('resolved_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('resolution_note', 'text', ['null' => true, 'default' => null])
            ->addColumn('resulting_todo_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('callback_summary', 'text', ['null' => true, 'default' => null])
            ->addColumn('callback_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['target_type', 'target_id', 'status'], ['name' => 'cross_context_requests_target_idx'])
            ->addIndex(['source_type', 'source_id'], ['name' => 'cross_context_requests_source_idx'])
            ->addIndex(['created_by_user_id'], ['name' => 'cross_context_requests_creator_idx'])
            ->addForeignKey('created_by_user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('resolved_by_user_id', 'users', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('resulting_todo_id', 'todos', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
            ])
            ->create();

        $this->execute(
            'ALTER TABLE cross_context_requests ADD CONSTRAINT cross_context_requests_source_type_chk '
            . "CHECK (source_type IN ('project', 'notebook'))",
        );
        $this->execute(
            'ALTER TABLE cross_context_requests ADD CONSTRAINT cross_context_requests_target_type_chk '
            . "CHECK (target_type IN ('project', 'notebook'))",
        );
        $this->execute(
            'ALTER TABLE cross_context_requests ADD CONSTRAINT cross_context_requests_request_type_chk '
            . "CHECK (request_type IN ('task', 'question', 'suggestion'))",
        );
        $this->execute(
            'ALTER TABLE cross_context_requests ADD CONSTRAINT cross_context_requests_status_chk '
            . "CHECK (status IN ('pending', 'accepted', 'rejected'))",
        );
        $this->execute(
            'ALTER TABLE cross_context_requests ADD CONSTRAINT cross_context_requests_resolution_chk '
            . "CHECK ((status = 'pending' AND resolved_at IS NULL AND resolved_by_user_id IS NULL) "
            . "OR (status <> 'pending' AND resolved_at IS NOT NULL AND resolved_by_user_id IS NOT NULL))",
        );
        $this->execute(
            'ALTER TABLE cross_context_requests ADD CONSTRAINT cross_context_requests_self_target_chk '
            . 'CHECK (NOT (source_type = target_type AND source_id = target_id))',
        );
    }

    /**
     * Drop the cross-context request table.
     */
    public function down(): void
    {
        $this->table('cross_context_requests')->drop()->save();
    }
}
