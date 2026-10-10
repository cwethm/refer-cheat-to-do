<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class ResearchCollectionsTable extends Table
{
    /**
     * Configure collection ownership and item associations.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('research_collections');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Owners', [
            'className' => 'Users',
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Creators', [
            'className' => 'Users',
            'foreignKey' => 'created_by_user_id',
            'joinType' => 'INNER',
        ]);
        $this->hasMany('ResearchCollectionItems', [
            'foreignKey' => 'research_collection_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
            'sort' => ['ResearchCollectionItems.id' => 'ASC'],
        ]);
    }

    /**
     * Validate collection fields.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('user_id')
            ->greaterThan('user_id', 0)
            ->requirePresence('user_id', 'create')
            ->notEmptyString('user_id');

        $validator
            ->integer('created_by_user_id')
            ->greaterThan('created_by_user_id', 0)
            ->requirePresence('created_by_user_id', 'create')
            ->notEmptyString('created_by_user_id');

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
     * Enforce valid owner and creator users.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['user_id'], 'Owners'), ['errorField' => 'user_id']);
        $rules->add($rules->existsIn(['created_by_user_id'], 'Creators'), ['errorField' => 'created_by_user_id']);

        return $rules;
    }
}
