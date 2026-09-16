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
 * @property string|null $previous_status
 * @property \Cake\I18n\DateTime|null $archived_at
 * @property \Cake\I18n\DateTime|null $trashed_at
 * @property \Cake\I18n\DateTime|null $last_reviewed_at
 * @property \Cake\I18n\DateTime|null $next_review_at
 * @property int $review_interval_days
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
        'previous_status' => false,
        'archived_at' => false,
        'trashed_at' => false,
        'last_reviewed_at' => false,
        'next_review_at' => false,
        'review_interval_days' => false,
        'created' => true,
        'modified' => true,
        'user' => true,
        'tags' => true,
    ];
}
