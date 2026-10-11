<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateRecords extends BaseMigration
{
    /**
     * Create discussion and event records and their tags (Slice 27).
     */
    public function up(): void
    {
        $this->table('records')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('kind', 'string', ['limit' => 32, 'null' => false, 'default' => 'discussion'])
            ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('occurred_at', 'datetime', ['null' => false])
            ->addColumn('participants', 'string', ['limit' => 2000, 'null' => true, 'default' => null])
            ->addColumn('location', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('summary', 'string', ['limit' => 500, 'null' => true, 'default' => null])
            ->addColumn('details', 'text', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id', 'occurred_at', 'id'], ['name' => 'records_owner_timeline_idx'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
            ])
            ->create();
        $this->execute(
            'ALTER TABLE records ADD CONSTRAINT records_kind_chk '
            . "CHECK (kind IN ('discussion', 'event'))",
        );
        $this->execute(
            'ALTER TABLE records ADD CONSTRAINT records_location_chk '
            . "CHECK (kind = 'event' OR location IS NULL)",
        );
        $this->execute(
            'ALTER TABLE records ADD CONSTRAINT records_details_size_chk '
            . 'CHECK (details IS NULL OR octet_length(details) <= 65536)',
        );

        $this->table('records_tags', ['id' => false, 'primary_key' => ['record_id', 'tag_id']])
            ->addColumn('record_id', 'integer', ['null' => false])
            ->addColumn('tag_id', 'integer', ['null' => false])
            ->addIndex(['tag_id', 'record_id'], ['name' => 'records_tags_tag_record_idx'])
            ->addForeignKey('record_id', 'records', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('tag_id', 'tags', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();

        $this->execute('ALTER TABLE activity_records DROP CONSTRAINT activity_records_subject_type_chk');
        $this->execute(
            'ALTER TABLE activity_records ADD CONSTRAINT activity_records_subject_type_chk '
            . "CHECK (subject_type IN ('todo', 'tag', 'project', 'notebook', 'library', "
            . "'capability_grant', 'cross_context_request', 'research_collection', "
            . "'research_collection_item', 'reference_card', 'cheat_sheet', 'record'))",
        );
    }

    /**
     * Drop the Slice 27 tables and restore the previous activity subject set.
     */
    public function down(): void
    {
        $this->execute(
            "DELETE FROM activity_records WHERE subject_type = 'record' OR context_type = 'record'",
        );
        $this->execute('ALTER TABLE activity_records DROP CONSTRAINT activity_records_subject_type_chk');
        $this->execute(
            'ALTER TABLE activity_records ADD CONSTRAINT activity_records_subject_type_chk '
            . "CHECK (subject_type IN ('todo', 'tag', 'project', 'notebook', 'library', "
            . "'capability_grant', 'cross_context_request', 'research_collection', "
            . "'research_collection_item', 'reference_card', 'cheat_sheet'))",
        );

        $this->table('records_tags')->drop()->update();
        $this->table('records')->drop()->update();
    }
}
