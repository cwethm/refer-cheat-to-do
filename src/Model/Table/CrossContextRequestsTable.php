<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Service\CrossContextRequestService;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class CrossContextRequestsTable extends Table
{
    /**
     * Initialize cross_context_requests table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('cross_context_requests');
        $this->setPrimaryKey('id');
        $this->setDisplayField('title');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Creators', [
            'className' => 'Users',
            'foreignKey' => 'created_by_user_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * Define cross-context request validators.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('source_type')
            ->inList('source_type', CrossContextRequestService::CONTEXT_TYPES)
            ->requirePresence('source_type', 'create')
            ->notEmptyString('source_type');

        $validator
            ->integer('source_id')
            ->greaterThan('source_id', 0)
            ->requirePresence('source_id', 'create')
            ->notEmptyString('source_id');

        $validator
            ->scalar('target_type')
            ->inList('target_type', CrossContextRequestService::CONTEXT_TYPES)
            ->requirePresence('target_type', 'create')
            ->notEmptyString('target_type');

        $validator
            ->integer('target_id')
            ->greaterThan('target_id', 0)
            ->requirePresence('target_id', 'create')
            ->notEmptyString('target_id');

        $validator
            ->scalar('request_type')
            ->inList('request_type', CrossContextRequestService::REQUEST_TYPES)
            ->requirePresence('request_type', 'create')
            ->notEmptyString('request_type');

        $validator
            ->scalar('title')
            ->maxLength('title', 255)
            ->requirePresence('title', 'create')
            ->notEmptyString('title');

        $validator
            ->scalar('body')
            ->maxLength('body', 10000)
            ->allowEmptyString('body');

        $validator
            ->scalar('status')
            ->inList('status', CrossContextRequestService::STATUSES)
            ->notEmptyString('status');

        $validator
            ->scalar('resolution_note')
            ->maxLength('resolution_note', 10000)
            ->allowEmptyString('resolution_note');

        $validator
            ->scalar('callback_summary')
            ->maxLength('callback_summary', 10000)
            ->allowEmptyString('callback_summary');

        return $validator;
    }

    /**
     * Add cross-context request integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['created_by_user_id'], 'Creators'), ['errorField' => 'created_by_user_id']);

        return $rules;
    }
}
