<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property string $source_type
 * @property int $source_id
 * @property string $target_type
 * @property int $target_id
 * @property string $request_type
 * @property string $title
 * @property string|null $body
 * @property string $status
 * @property int $created_by_user_id
 * @property int|null $resolved_by_user_id
 * @property \Cake\I18n\DateTime|null $resolved_at
 * @property string|null $resolution_note
 * @property int|null $resulting_todo_id
 * @property string|null $callback_summary
 * @property \Cake\I18n\DateTime|null $callback_at
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class CrossContextRequest extends Entity
{
    /**
     * Fields that can be mass assigned.
     *
     * Status, resolution and callback state are server controlled transitions.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'source_type' => true,
        'source_id' => true,
        'target_type' => true,
        'target_id' => true,
        'request_type' => true,
        'title' => true,
        'body' => true,
        'status' => false,
        'created_by_user_id' => false,
        'resolved_by_user_id' => false,
        'resolved_at' => false,
        'resolution_note' => false,
        'resulting_todo_id' => false,
        'callback_summary' => false,
        'callback_at' => false,
    ];

    /**
     * Whether the request is still awaiting resolution.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
