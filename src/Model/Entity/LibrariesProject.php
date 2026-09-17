<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $library_id
 * @property int $project_id
 */
class LibrariesProject extends Entity
{
    /**
     * Fields that can be mass assigned.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'library_id' => true,
        'project_id' => true,
    ];
}
