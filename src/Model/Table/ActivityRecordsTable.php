<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Service\ActivityRecorder;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class ActivityRecordsTable extends Table
{
    /**
     * Initialize activity_records table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('activity_records');
        $this->setPrimaryKey('id');
        $this->setDisplayField('action');
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => ['created' => 'new'],
            ],
        ]);

        $this->belongsTo('Actors', [
            'className' => 'Users',
            'foreignKey' => 'actor_user_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * Define activity record validators.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('actor_user_id')
            ->greaterThan('actor_user_id', 0)
            ->requirePresence('actor_user_id', 'create')
            ->notEmptyString('actor_user_id');

        $validator
            ->scalar('action')
            ->maxLength('action', 64)
            ->requirePresence('action', 'create')
            ->notEmptyString('action');

        $validator
            ->scalar('subject_type')
            ->inList('subject_type', ActivityRecorder::SUBJECT_TYPES)
            ->requirePresence('subject_type', 'create')
            ->notEmptyString('subject_type');

        $validator
            ->integer('subject_id')
            ->greaterThan('subject_id', 0)
            ->requirePresence('subject_id', 'create')
            ->notEmptyString('subject_id');

        $validator
            ->scalar('context_type')
            ->inList('context_type', ActivityRecorder::SUBJECT_TYPES)
            ->allowEmptyString('context_type');

        $validator
            ->integer('context_id')
            ->greaterThan('context_id', 0)
            ->allowEmptyString('context_id');

        return $validator;
    }
}
