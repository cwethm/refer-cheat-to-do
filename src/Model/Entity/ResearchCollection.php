<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property int $user_id
 * @property int $created_by_user_id
 * @property string $name
 * @property string|null $description
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property list<\App\Model\Entity\ResearchCollectionItem> $research_collection_items
 */
class ResearchCollection extends Entity
{
    /**
     * Ownership and creator identity are assigned by the server.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => false,
        'created_by_user_id' => false,
        'name' => true,
        'description' => true,
        'created' => false,
        'modified' => false,
        'research_collection_items' => false,
    ];
}
