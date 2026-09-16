<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateActivityRecords extends BaseMigration
{
    /**
     * Create the activity record table.
     */
    public function up(): void
    {
        $this->table('activity_records')
            ->addColumn('actor_user_id', 'integer', ['null' => false])
            ->addColumn('action', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('subject_type', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('subject_id', 'integer', ['null' => false])
            ->addColumn('context_type', 'string', ['limit' => 32, 'null' => true, 'default' => null])
            ->addColumn('context_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('metadata', 'text', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['actor_user_id', 'created'], ['name' => 'activity_records_actor_idx'])
            ->addIndex(['subject_type', 'subject_id'], ['name' => 'activity_records_subject_idx'])
            ->addIndex(['context_type', 'context_id', 'created'], ['name' => 'activity_records_context_idx'])
            ->addForeignKey('actor_user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();

        $this->execute(
            'ALTER TABLE activity_records ADD CONSTRAINT activity_records_subject_type_chk '
            . "CHECK (subject_type IN ('todo', 'tag', 'project', 'notebook', 'library', "
            . "'capability_grant', 'cross_context_request'))",
        );
        $this->execute(
            'ALTER TABLE activity_records ADD CONSTRAINT activity_records_context_chk '
            . 'CHECK ((context_type IS NULL AND context_id IS NULL) '
            . 'OR (context_type IS NOT NULL AND context_id IS NOT NULL))',
        );
    }

    /**
     * Drop the activity record table.
     */
    public function down(): void
    {
        $this->table('activity_records')->drop()->save();
    }
}
