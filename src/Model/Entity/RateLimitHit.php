<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * One counted attempt in a rate-limit bucket. The bucket is a keyed hash, never the raw key.
 *
 * @property int $id
 * @property string $scope
 * @property string $bucket
 * @property \Cake\I18n\DateTime $created
 */
class RateLimitHit extends Entity
{
    /**
     * Hits are only written by {@see \App\Service\RateLimiter}; nothing is mass assignable.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [];
}
