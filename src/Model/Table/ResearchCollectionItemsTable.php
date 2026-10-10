<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class ResearchCollectionItemsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('research_collection_items');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('ResearchCollections', [
            'foreignKey' => 'research_collection_id',
            'joinType' => 'INNER',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('research_collection_id')
            ->greaterThan('research_collection_id', 0)
            ->requirePresence('research_collection_id', 'create')
            ->notEmptyString('research_collection_id');

        $validator
            ->scalar('title')
            ->maxLength('title', 255)
            ->requirePresence('title', 'create')
            ->notEmptyString('title');

        $validator
            ->scalar('content')
            ->maxLength('content', 65536)
            ->allowEmptyString('content');

        $validator
            ->scalar('source_uri')
            ->maxLength('source_uri', 2048)
            ->allowEmptyString('source_uri');

        $validator
            ->scalar('related_resource_type')
            ->inList('related_resource_type', ['todo', 'project', 'notebook'])
            ->allowEmptyString('related_resource_type');

        $validator
            ->integer('related_resource_id')
            ->greaterThan('related_resource_id', 0)
            ->allowEmptyString('related_resource_id');

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add(
            $rules->existsIn(['research_collection_id'], 'ResearchCollections'),
            ['errorField' => 'research_collection_id'],
        );

        return $rules;
    }
}
