<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class UsersTable extends Table
{
    /**
     * Initialize users table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('users');
        $this->setDisplayField('email');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->hasMany('Todos', [
            'foreignKey' => 'user_id',
            'dependent' => false,
        ]);
    }

    /**
     * Define default validators.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('email')
            ->maxLength('email', 191)
            ->requirePresence('email', 'create')
            ->notEmptyString('email')
            ->email('email');

        $validator
            ->scalar('password')
            ->maxLength('password', 255)
            ->requirePresence('password', 'create')
            ->notEmptyString('password')
            ->minLength('password', 8);

        return $validator;
    }

    /**
     * Add users table integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['email']), ['errorField' => 'email']);

        return $rules;
    }
}
