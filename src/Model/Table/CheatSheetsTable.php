<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class CheatSheetsTable extends Table
{
    /**
     * Configure ownership and ordered card memberships (ADR 0004).
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('cheat_sheets');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);
        $this->hasMany('CheatSheetsReferenceCards', [
            'foreignKey' => 'cheat_sheet_id',
            'dependent' => false,
        ]);
    }

    /**
     * Validate sheet fields.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('description')
            ->maxLength('description', 5000)
            ->allowEmptyString('description');

        return $validator;
    }

    /**
     * Require an existing owner.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);

        return $rules;
    }
}
