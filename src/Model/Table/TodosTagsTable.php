<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Throwable;

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

    /**
     * Create a Todo/Tag relation and return false when it already exists.
     */
    public function attachIfMissing(int $todoId, int $tagId): bool
    {
        try {
            $this->getConnection()->insert($this->getTable(), [
                'todo_id' => $todoId,
                'tag_id' => $tagId,
            ], [
                'todo_id' => 'integer',
                'tag_id' => 'integer',
            ]);
        } catch (Throwable $exception) {
            if ($this->isUniqueViolationException($exception)) {
                return false;
            }

            throw $exception;
        }

        return true;
    }

    /**
     * Detect unique-constraint DB errors for race-safe conflict handling.
     */
    private function isUniqueViolationException(Throwable $exception): bool
    {
        $code = (string)$exception->getCode();
        if ($code === '23505') {
            return true;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof Throwable) {
            return $this->isUniqueViolationException($previous);
        }

        return false;
    }
}
