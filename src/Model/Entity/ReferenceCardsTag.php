<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $reference_card_id
 * @property int $tag_id
 */
class ReferenceCardsTag extends Entity
{
    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'reference_card_id' => true,
        'tag_id' => true,
    ];
}
