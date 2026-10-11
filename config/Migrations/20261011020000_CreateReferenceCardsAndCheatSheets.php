<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateReferenceCardsAndCheatSheets extends BaseMigration
{
    /**
     * Dialogue-pattern columns, valid only on `dialogue_pattern` cards.
     *
     * @var list<string>
     */
    private const DIALOGUE_FIELDS = ['situation', 'aim', 'approach', 'avoid', 'rationale'];

    /**
     * Create reference cards, their tags, cheat sheets and ordered sheet membership (Slice 26).
     */
    public function up(): void
    {
        $cards = $this->table('reference_cards')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('card_type', 'string', ['limit' => 32, 'null' => false, 'default' => 'concept'])
            ->addColumn('title', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('summary', 'string', ['limit' => 500, 'null' => true, 'default' => null])
            ->addColumn('body', 'text', ['null' => true, 'default' => null]);
        foreach (self::DIALOGUE_FIELDS as $field) {
            $cards->addColumn($field, 'string', ['limit' => 2000, 'null' => true, 'default' => null]);
        }
        $cards
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id', 'id'], ['name' => 'reference_cards_owner_idx'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
            ])
            ->create();
        $this->execute(
            'ALTER TABLE reference_cards ADD CONSTRAINT reference_cards_card_type_chk '
            . "CHECK (card_type IN ('concept', 'dialogue_pattern'))",
        );
        $this->execute(
            'ALTER TABLE reference_cards ADD CONSTRAINT reference_cards_dialogue_fields_chk '
            . "CHECK (card_type = 'dialogue_pattern' OR ("
            . implode(' AND ', array_map(
                static fn(string $field): string => $field . ' IS NULL',
                self::DIALOGUE_FIELDS,
            ))
            . '))',
        );
        $this->execute(
            'ALTER TABLE reference_cards ADD CONSTRAINT reference_cards_body_size_chk '
            . 'CHECK (body IS NULL OR octet_length(body) <= 65536)',
        );

        $this->table('reference_cards_tags', ['id' => false, 'primary_key' => ['reference_card_id', 'tag_id']])
            ->addColumn('reference_card_id', 'integer', ['null' => false])
            ->addColumn('tag_id', 'integer', ['null' => false])
            ->addIndex(['tag_id', 'reference_card_id'], ['name' => 'reference_cards_tags_tag_card_idx'])
            ->addForeignKey('reference_card_id', 'reference_cards', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('tag_id', 'tags', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();

        $this->table('cheat_sheets')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('description', 'text', ['null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id', 'id'], ['name' => 'cheat_sheets_owner_idx'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'NO_ACTION',
            ])
            ->create();

        $this->table(
            'cheat_sheets_reference_cards',
            ['id' => false, 'primary_key' => ['cheat_sheet_id', 'reference_card_id']],
        )
            ->addColumn('cheat_sheet_id', 'integer', ['null' => false])
            ->addColumn('reference_card_id', 'integer', ['null' => false])
            ->addColumn('position', 'integer', ['null' => false])
            ->addColumn('created', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['cheat_sheet_id', 'position'], ['name' => 'cheat_sheets_reference_cards_position_idx'])
            ->addIndex(['reference_card_id'], ['name' => 'cheat_sheets_reference_cards_card_idx'])
            ->addForeignKey('cheat_sheet_id', 'cheat_sheets', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->addForeignKey('reference_card_id', 'reference_cards', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();
        $this->execute(
            'ALTER TABLE cheat_sheets_reference_cards ADD CONSTRAINT cheat_sheets_reference_cards_position_chk '
            . 'CHECK (position > 0)',
        );

        $this->execute('ALTER TABLE activity_records DROP CONSTRAINT activity_records_subject_type_chk');
        $this->execute(
            'ALTER TABLE activity_records ADD CONSTRAINT activity_records_subject_type_chk '
            . "CHECK (subject_type IN ('todo', 'tag', 'project', 'notebook', 'library', "
            . "'capability_grant', 'cross_context_request', 'research_collection', "
            . "'research_collection_item', 'reference_card', 'cheat_sheet'))",
        );
    }

    /**
     * Drop the Slice 26 tables and restore the previous activity subject set.
     */
    public function down(): void
    {
        $this->execute(
            "DELETE FROM activity_records WHERE subject_type IN ('reference_card', 'cheat_sheet') "
            . "OR context_type IN ('reference_card', 'cheat_sheet')",
        );
        $this->execute('ALTER TABLE activity_records DROP CONSTRAINT activity_records_subject_type_chk');
        $this->execute(
            'ALTER TABLE activity_records ADD CONSTRAINT activity_records_subject_type_chk '
            . "CHECK (subject_type IN ('todo', 'tag', 'project', 'notebook', 'library', "
            . "'capability_grant', 'cross_context_request', 'research_collection', "
            . "'research_collection_item'))",
        );

        $this->table('cheat_sheets_reference_cards')->drop()->update();
        $this->table('cheat_sheets')->drop()->update();
        $this->table('reference_cards_tags')->drop()->update();
        $this->table('reference_cards')->drop()->update();
    }
}
