<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $todo_id
 * @property int $tag_id
 */
class TodosTag extends Entity
{
    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'todo_id' => true,
        'tag_id' => true,
    ];
}
