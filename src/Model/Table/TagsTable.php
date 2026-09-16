<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use ArrayObject;

class TagsTable extends Table
{
    /**
     * Initialize tags table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('tags');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsToMany('Todos', [
            'foreignKey' => 'tag_id',
            'targetForeignKey' => 'todo_id',
            'joinTable' => 'todos_tags',
            'dependent' => false,
        ]);
    }

    /**
     * Normalize incoming tag payload values.
     */
    public function beforeMarshal(EventInterface $event, ArrayObject $data, ArrayObject $options): void
    {
        if (!isset($data['name']) || !is_string($data['name'])) {
            return;
        }
        $name = preg_replace('/\s+/u', ' ', trim($data['name']));
        $data['name'] = $name === null ? trim($data['name']) : $name;
    }

    /**
     * Define tag validators.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('user_id')
            ->greaterThan('user_id', 0)
            ->requirePresence('user_id', 'create')
            ->notEmptyString('user_id');

        $validator
            ->scalar('name')
            ->maxLength('name', 100)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        return $validator;
    }

    /**
     * Add tags table integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);
        $rules->add(function (EntityInterface $entity): bool {
            $userId = $entity->get('user_id');
            $name = $entity->get('name');
            if (!is_int($userId) || $userId < 1 || !is_string($name) || $name === '') {
                return true;
            }

            $normalizedName = self::normalizeName($name);
            $id = $entity->get('id');
            $query = $this->find()->where(['user_id' => $userId]);
            if (is_int($id) && $id > 0) {
                $query->where(['id !=' => $id]);
            }
            $query->where(function ($exp, $query) use ($normalizedName) {
                return $exp->eq(
                    $query->newExpr("lower(regexp_replace(trim(name), '\\s+', ' ', 'g'))"),
                    $normalizedName,
                    'string',
                );
            });

            return !$query->limit(1)->count();
        }, 'uniqueNormalizedName', [
            'errorField' => 'name',
            'message' => 'Tag name must be unique per user.',
        ]);

        return $rules;
    }

    private static function normalizeName(string $name): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($name));

        return mb_strtolower($normalized === null ? trim($name) : $normalized);
    }
}
