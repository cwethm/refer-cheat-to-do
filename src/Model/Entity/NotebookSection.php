<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property int $notebook_id
 * @property string $name
 * @property int $position
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property \App\Model\Entity\Notebook|null $notebook
 * @property list<\App\Model\Entity\Todo> $todos
 */
class NotebookSection extends Entity
{
    /**
     * Fields that can be mass assigned. Notebook membership and ordering are server-controlled.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'notebook_id' => false,
        'name' => true,
        'position' => false,
        'created' => true,
        'modified' => true,
        'notebook' => false,
        'todos' => false,
    ];
}
