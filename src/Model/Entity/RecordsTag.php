<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $record_id
 * @property int $tag_id
 */
class RecordsTag extends Entity
{
    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'record_id' => true,
        'tag_id' => true,
    ];
}
