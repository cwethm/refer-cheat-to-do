<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property list<\App\Model\Entity\NotebookSection> $notebook_sections
 */
class Notebook extends Entity
{
    /**
     * Fields that can be mass assigned. Ownership is server-controlled.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => false,
        'name' => true,
        'description' => true,
        'created' => true,
        'modified' => true,
        'user' => true,
        'notebook_sections' => false,
    ];
}
