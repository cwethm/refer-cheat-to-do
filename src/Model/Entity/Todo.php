<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Todo extends Entity
{
    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'title' => true,
        'notes' => true,
        'status' => true,
        'created' => true,
        'modified' => true,
        'user' => true,
    ];
}
