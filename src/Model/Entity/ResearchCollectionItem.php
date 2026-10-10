<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property int $research_collection_id
 * @property string $title
 * @property string|null $content
 * @property string|null $source_uri
 * @property string|null $related_resource_type
 * @property int|null $related_resource_id
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class ResearchCollectionItem extends Entity
{
    /**
     * The parent collection is assigned by the server.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'research_collection_id' => false,
        'title' => true,
        'content' => true,
        'source_uri' => true,
        'related_resource_type' => true,
        'related_resource_id' => true,
        'created' => false,
        'modified' => false,
    ];
}
