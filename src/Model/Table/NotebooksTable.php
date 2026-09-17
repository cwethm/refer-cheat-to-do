<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class NotebooksTable extends Table
{
    /**
     * Initialize notebooks table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('notebooks');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);
        $this->hasMany('NotebookSections', [
            'foreignKey' => 'notebook_id',
            'sort' => ['NotebookSections.position' => 'ASC', 'NotebookSections.id' => 'ASC'],
            'dependent' => false,
        ]);
    }

    /**
     * Define notebook validators.
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
     * Add notebooks table integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);

        return $rules;
    }
}
