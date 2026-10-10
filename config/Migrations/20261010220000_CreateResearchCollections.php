<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateResearchCollections extends BaseMigration
{
    public function up(): void
    {
        $this->table('research_collections')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('created_by_user_id', 'integer', ['null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('description', 'text', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id', 'id'], ['name' => 'research_collections_owner_idx'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('created_by_user_id', 'users', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
            ])
            ->create();

        $this->table('research_collection_items')
            ->addColumn('research_collection_id', 'integer', ['null' => false])
            ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('content', 'text', ['null' => true, 'default' => null])
            ->addColumn('source_uri', 'string', ['limit' => 2048, 'null' => true, 'default' => null])
            ->addColumn('related_resource_type', 'string', ['limit' => 16, 'null' => true, 'default' => null])
            ->addColumn('related_resource_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['research_collection_id', 'id'], ['name' => 'research_collection_items_collection_idx'])
            ->addIndex(['related_resource_type', 'related_resource_id'], ['name' => 'research_collection_items_related_idx'])
            ->addForeignKey('research_collection_id', 'research_collections', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();
        $this->execute(
            'ALTER TABLE research_collection_items ADD CONSTRAINT research_collection_items_related_chk '
            . "CHECK ((related_resource_type IS NULL AND related_resource_id IS NULL) OR "
            . "(related_resource_type IN ('todo', 'project', 'notebook') AND related_resource_id > 0))",
        );

        $this->execute('ALTER TABLE capability_grants DROP CONSTRAINT capability_grants_resource_type_chk');
        $this->execute(
            'ALTER TABLE capability_grants ADD CONSTRAINT capability_grants_resource_type_chk '
            . "CHECK (resource_type IN ('project', 'notebook', 'library', 'research_collection'))",
        );

        $this->execute('ALTER TABLE activity_records DROP CONSTRAINT activity_records_subject_type_chk');
        $this->execute(
            'ALTER TABLE activity_records ADD CONSTRAINT activity_records_subject_type_chk '
            . "CHECK (subject_type IN ('todo', 'tag', 'project', 'notebook', 'library', "
            . "'capability_grant', 'cross_context_request', 'research_collection', "
            . "'research_collection_item'))",
        );
    }

    public function down(): void
    {
        $this->execute(
            "DELETE FROM capability_grants WHERE resource_type = 'research_collection'",
        );
        $this->execute(
            "DELETE FROM activity_records WHERE subject_type IN ('research_collection', 'research_collection_item')",
        );

        $this->execute('ALTER TABLE activity_records DROP CONSTRAINT activity_records_subject_type_chk');
        $this->execute(
            'ALTER TABLE activity_records ADD CONSTRAINT activity_records_subject_type_chk '
            . "CHECK (subject_type IN ('todo', 'tag', 'project', 'notebook', 'library', "
            . "'capability_grant', 'cross_context_request'))",
        );
        $this->execute('ALTER TABLE capability_grants DROP CONSTRAINT capability_grants_resource_type_chk');
        $this->execute(
            'ALTER TABLE capability_grants ADD CONSTRAINT capability_grants_resource_type_chk '
            . "CHECK (resource_type IN ('project', 'notebook', 'library'))",
        );

        $this->table('research_collection_items')->drop()->update();
        $this->table('research_collections')->drop()->update();
    }
}
