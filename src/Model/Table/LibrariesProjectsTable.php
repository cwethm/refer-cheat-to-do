<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class LibrariesProjectsTable extends Table
{
    /**
     * Initialize libraries_projects table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('libraries_projects');
        $this->setPrimaryKey(['library_id', 'project_id']);

        $this->belongsTo('Libraries', [
            'foreignKey' => 'library_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Projects', [
            'foreignKey' => 'project_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * Define libraries_projects validators.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('library_id')
            ->greaterThan('library_id', 0)
            ->requirePresence('library_id', 'create')
            ->notEmptyString('library_id');

        $validator
            ->integer('project_id')
            ->greaterThan('project_id', 0)
            ->requirePresence('project_id', 'create')
            ->notEmptyString('project_id');

        return $validator;
    }

    /**
     * Add libraries_projects integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['library_id'], 'Libraries'), ['errorField' => 'library_id']);
        $rules->add($rules->existsIn(['project_id'], 'Projects'), ['errorField' => 'project_id']);

        return $rules;
    }
}
