<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Service\CapabilityService;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class CapabilityGrantsTable extends Table
{
    /**
     * Initialize capability_grants table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('capability_grants');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Subjects', [
            'className' => 'Users',
            'foreignKey' => 'subject_user_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Grantors', [
            'className' => 'Users',
            'foreignKey' => 'grantor_user_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * Define capability grant validators.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('subject_user_id')
            ->greaterThan('subject_user_id', 0)
            ->requirePresence('subject_user_id', 'create')
            ->notEmptyString('subject_user_id');

        $validator
            ->integer('grantor_user_id')
            ->greaterThan('grantor_user_id', 0)
            ->requirePresence('grantor_user_id', 'create')
            ->notEmptyString('grantor_user_id');

        $validator
            ->scalar('resource_type')
            ->inList('resource_type', [
                CapabilityService::RESOURCE_PROJECT,
                CapabilityService::RESOURCE_NOTEBOOK,
                CapabilityService::RESOURCE_LIBRARY,
            ])
            ->requirePresence('resource_type', 'create')
            ->notEmptyString('resource_type');

        $validator
            ->integer('resource_id')
            ->greaterThan('resource_id', 0)
            ->requirePresence('resource_id', 'create')
            ->notEmptyString('resource_id');

        $validator
            ->scalar('capability')
            ->inList('capability', CapabilityService::CAPABILITIES)
            ->requirePresence('capability', 'create')
            ->notEmptyString('capability');

        $validator
            ->dateTime('revoked_at')
            ->allowEmptyDateTime('revoked_at');

        return $validator;
    }

    /**
     * Add capability grant integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['subject_user_id'], 'Subjects'), ['errorField' => 'subject_user_id']);
        $rules->add($rules->existsIn(['grantor_user_id'], 'Grantors'), ['errorField' => 'grantor_user_id']);

        return $rules;
    }
}
