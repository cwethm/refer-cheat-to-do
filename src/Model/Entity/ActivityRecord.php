<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property int $actor_user_id
 * @property string $action
 * @property string $subject_type
 * @property int $subject_id
 * @property string|null $context_type
 * @property int|null $context_id
 * @property string|null $metadata
 * @property \Cake\I18n\DateTime|null $created
 */
class ActivityRecord extends Entity
{
    /**
     * Activity records are written exclusively by the ActivityRecorder service.
     *
     * No field is mass assignable from client input.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'actor_user_id' => false,
        'action' => false,
        'subject_type' => false,
        'subject_id' => false,
        'context_type' => false,
        'context_id' => false,
        'metadata' => false,
    ];

    /**
     * Decode the stored metadata payload.
     *
     * @return array<string, mixed>
     */
    public function metadataArray(): array
    {
        if ($this->metadata === null || $this->metadata === '') {
            return [];
        }

        $decoded = json_decode($this->metadata, true);

        return is_array($decoded) ? $decoded : [];
    }
}
