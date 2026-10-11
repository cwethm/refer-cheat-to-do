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
 */
class CheatSheet extends Entity
{
    /**
     * Ownership is assigned from the session and never mass-assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => false,
        'name' => true,
        'description' => true,
        'created' => false,
        'modified' => false,
    ];
}
