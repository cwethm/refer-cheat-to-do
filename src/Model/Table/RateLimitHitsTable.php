<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class RateLimitHitsTable extends Table
{
    /**
     * Initialize rate-limit hit table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('rate_limit_hits');
        $this->setPrimaryKey('id');
    }
}
