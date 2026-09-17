<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class LibrariesTable extends Table
{
    /**
     * Initialize libraries table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('libraries');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsToMany('Projects', [
            'foreignKey' => 'library_id',
            'targetForeignKey' => 'project_id',
            'joinTable' => 'libraries_projects',
            'dependent' => false,
        ]);
        $this->belongsToMany('Notebooks', [
            'foreignKey' => 'library_id',
            'targetForeignKey' => 'notebook_id',
            'joinTable' => 'libraries_notebooks',
            'dependent' => false,
        ]);
    }

    /**
     * Define library validators.
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
     * Add libraries table integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);

        return $rules;
    }
}
