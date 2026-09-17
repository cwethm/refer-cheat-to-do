<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateCapabilityGrants extends BaseMigration
{
    /**
     * Create the explicit capability grant table.
     */
    public function up(): void
    {
        $this->table('capability_grants')
            ->addColumn('subject_user_id', 'integer', ['null' => false])
            ->addColumn('grantor_user_id', 'integer', ['null' => false])
            ->addColumn('resource_type', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('resource_id', 'integer', ['null' => false])
            ->addColumn('capability', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('revoked_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['resource_type', 'resource_id'], ['name' => 'capability_grants_resource_idx'])
            ->addIndex(['subject_user_id'], ['name' => 'capability_grants_subject_idx'])
            ->addIndex(
                ['subject_user_id', 'resource_type', 'resource_id', 'capability'],
                ['unique' => true, 'name' => 'capability_grants_unique_idx'],
            )
            ->addForeignKey('subject_user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('grantor_user_id', 'users', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
            ])
            ->create();

        $this->execute(
            "ALTER TABLE capability_grants ADD CONSTRAINT capability_grants_resource_type_chk "
            . "CHECK (resource_type IN ('project', 'notebook', 'library'))",
        );
        $this->execute(
            "ALTER TABLE capability_grants ADD CONSTRAINT capability_grants_capability_chk "
            . "CHECK (capability IN ('discover', 'read', 'reference', 'query', 'suggest', 'send_task', "
            . "'callback', 'contribute', 'edit', 'archive', 'delete', 'manage_permissions'))",
        );
        $this->execute(
            'ALTER TABLE capability_grants ADD CONSTRAINT capability_grants_subject_not_grantor_chk '
            . 'CHECK (subject_user_id <> grantor_user_id)',
        );
    }

    /**
     * Drop the capability grant table.
     */
    public function down(): void
    {
        $this->table('capability_grants')->drop()->save();
    }
}
