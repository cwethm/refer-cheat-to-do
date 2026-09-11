<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class TodosTagsTable extends Table
{
    /**
     * Initialize todos_tags table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('todos_tags');
        $this->setPrimaryKey(['todo_id', 'tag_id']);

        $this->belongsTo('Todos', [
            'foreignKey' => 'todo_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Tags', [
            'foreignKey' => 'tag_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * Define todos_tags validators.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('todo_id')
            ->greaterThan('todo_id', 0)
            ->requirePresence('todo_id', 'create')
            ->notEmptyString('todo_id');

        $validator
            ->integer('tag_id')
            ->greaterThan('tag_id', 0)
            ->requirePresence('tag_id', 'create')
            ->notEmptyString('tag_id');

        return $validator;
    }

    /**
     * Add todos_tags table integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['todo_id'], 'Todos'), ['errorField' => 'todo_id']);
        $rules->add($rules->existsIn(['tag_id'], 'Tags'), ['errorField' => 'tag_id']);
        $rules->add($rules->isUnique(['todo_id', 'tag_id']), ['errorField' => 'tag_id']);

        return $rules;
    }
}
