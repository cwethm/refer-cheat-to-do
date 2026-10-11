<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class AccountTokensTable extends Table
{
    /**
     * Initialize account token table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('account_tokens');
        $this->setPrimaryKey('id');
        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
        ]);
    }
}
