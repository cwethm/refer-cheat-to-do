<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $library_id
 * @property int $notebook_id
 */
class LibrariesNotebook extends Entity
{
    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'library_id' => true,
        'notebook_id' => true,
    ];
}
