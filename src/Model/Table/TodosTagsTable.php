<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use RuntimeException;
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
     * Attempt to create a Todo/Tag relation, returning false when the pair already exists.
     *
     * The insert is attempted directly so the database unique constraint, rather than a
     * check-then-insert lookup, is the authoritative protection against concurrent duplicates.
     */
    public function attachIfMissing(int $todoId, int $tagId): bool
    {
        $join = $this->newEntity([
            'todo_id' => $todoId,
            'tag_id' => $tagId,
        ]);
        if ($join->hasErrors()) {
            throw new RuntimeException('Invalid tag attachment payload.');
        }

        try {
            $saved = $this->save($join, ['checkExisting' => false]);
        } catch (Throwable $exception) {
            if ($this->isUniqueViolationException($exception)) {
                return false;
            }

            throw $exception;
        }
        if ($saved === false) {
            if ($this->hasUniqueRuleError($join->getErrors())) {
                return false;
            }

            throw new RuntimeException('Unable to persist tag attachment.');
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

    /**
     * Detect ORM unique-rule failures from save() returning false.
     *
     * @param array<string, mixed> $errors
     */
    private function hasUniqueRuleError(array $errors): bool
    {
        foreach ($errors as $fieldErrors) {
            if (!is_array($fieldErrors)) {
                continue;
            }
            foreach ($fieldErrors as $key => $value) {
                if ((string)$key === '_isUnique') {
                    return true;
                }
                if (is_array($value) && $this->hasUniqueRuleError($value)) {
                    return true;
                }
            }
        }

        return false;
    }
}
