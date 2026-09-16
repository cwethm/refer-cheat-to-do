<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $notes
 * @property string $status
 * @property int|null $project_section_id
 * @property int|null $notebook_section_id
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property list<\App\Model\Entity\Tag> $tags
 */
class Todo extends Entity
{
    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => true,
        'title' => true,
        'notes' => true,
        'status' => true,
        'project_section_id' => false,
        'notebook_section_id' => false,
        'created' => true,
        'modified' => true,
        'user' => true,
        'tags' => true,
    ];
}
