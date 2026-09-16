<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $todo_id
 * @property int $related_todo_id
 * @property \Cake\I18n\DateTime|null $created
 */
class RelatedTodo extends Entity
{
    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'todo_id' => true,
        'related_todo_id' => true,
        'created' => true,
    ];
}
