<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $cheat_sheet_id
 * @property int $reference_card_id
 * @property int $position
 * @property \Cake\I18n\DateTime|null $created
 */
class CheatSheetsReferenceCard extends Entity
{
    /**
     * Membership keys and positions are set by the server, never mass-assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'cheat_sheet_id' => false,
        'reference_card_id' => false,
        'position' => false,
        'created' => false,
    ];
}
