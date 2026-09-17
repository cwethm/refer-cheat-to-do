<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class RelatedTodosTable extends Table
{
    /**
     * Initialize related_todos table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('related_todos');
        $this->setPrimaryKey(['todo_id', 'related_todo_id']);
        $this->addBehavior('Timestamp', [
            'events' => ['Model.beforeSave' => ['created' => 'new']],
        ]);

        $this->belongsTo('Todos', [
            'foreignKey' => 'todo_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('RelatedTodo', [
            'className' => 'Todos',
            'foreignKey' => 'related_todo_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * Define related_todos validators.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('todo_id')
            ->greaterThan('todo_id', 0)
            ->requirePresence('todo_id', 'create')
            ->notEmptyString('todo_id');

        $validator
            ->integer('related_todo_id')
            ->greaterThan('related_todo_id', 0)
            ->requirePresence('related_todo_id', 'create')
            ->notEmptyString('related_todo_id');

        return $validator;
    }

    /**
     * Add related_todos integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['todo_id'], 'Todos'), ['errorField' => 'todo_id']);
        $rules->add($rules->existsIn(['related_todo_id'], 'RelatedTodo'), ['errorField' => 'related_todo_id']);
        $rules->add(
            fn($entity): bool => (int)$entity->get('todo_id') < (int)$entity->get('related_todo_id'),
            'canonicalPairOrder',
            [
                'errorField' => 'related_todo_id',
                'message' => 'Related ToDo pairs must be stored in canonical order.',
            ],
        );

        return $rules;
    }
}
