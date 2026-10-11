<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Behavior\SectionOrderingBehavior;
use App\Model\Entity\CheatSheetsReferenceCard;
use Cake\ORM\Table;

/**
 * Ordered card membership of a cheat sheet (ADR 0004).
 */
class CheatSheetsReferenceCardsTable extends Table
{
    /**
     * Configure the composite key and the shared ordering component.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('cheat_sheets_reference_cards');
        $this->setPrimaryKey(['cheat_sheet_id', 'reference_card_id']);
        $this->addBehavior('SectionOrdering', ['parentField' => 'cheat_sheet_id']);

        $this->belongsTo('CheatSheets', [
            'foreignKey' => 'cheat_sheet_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('ReferenceCards', [
            'foreignKey' => 'reference_card_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * Resolve the shared ordering component.
     */
    private function ordering(): SectionOrderingBehavior
    {
        /** @var \App\Model\Behavior\SectionOrderingBehavior $behavior */
        $behavior = $this->getBehavior('SectionOrdering');

        return $behavior;
    }

    /**
     * Next available position within a cheat sheet.
     */
    public function nextPosition(int $cheatSheetId): int
    {
        return $this->ordering()->nextPosition($cheatSheetId);
    }

    /**
     * Move a membership to an explicit 1-based position within its cheat sheet.
     */
    public function moveToPosition(CheatSheetsReferenceCard $membership, int $position): void
    {
        $this->ordering()->moveToPosition($membership, $position);
    }

    /**
     * Keep positions contiguous within a cheat sheet.
     */
    public function renumberPositions(int $cheatSheetId): void
    {
        $this->ordering()->renumberPositions($cheatSheetId);
    }
}
