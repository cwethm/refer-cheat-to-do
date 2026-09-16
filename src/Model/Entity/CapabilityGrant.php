<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\I18n\DateTime;
use Cake\ORM\Entity;

/**
 * @property int $id
 * @property int $subject_user_id
 * @property int $grantor_user_id
 * @property string $resource_type
 * @property int $resource_id
 * @property string $capability
 * @property \Cake\I18n\DateTime|null $revoked_at
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class CapabilityGrant extends Entity
{
    /**
     * Fields that can be mass assigned.
     *
     * Grantor identity and revocation state are server controlled.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'subject_user_id' => true,
        'resource_type' => true,
        'resource_id' => true,
        'capability' => true,
        'grantor_user_id' => false,
        'revoked_at' => false,
    ];

    /**
     * Whether this grant is currently active.
     */
    public function isActive(): bool
    {
        return !$this->revoked_at instanceof DateTime;
    }
}
