<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class LibrariesNotebooksTable extends Table
{
    /**
     * Initialize libraries_notebooks table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('libraries_notebooks');
        $this->setPrimaryKey(['library_id', 'notebook_id']);

        $this->belongsTo('Libraries', [
            'foreignKey' => 'library_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Notebooks', [
            'foreignKey' => 'notebook_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * Define libraries_notebooks validators.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('library_id')
            ->greaterThan('library_id', 0)
            ->requirePresence('library_id', 'create')
            ->notEmptyString('library_id');

        $validator
            ->integer('notebook_id')
            ->greaterThan('notebook_id', 0)
            ->requirePresence('notebook_id', 'create')
            ->notEmptyString('notebook_id');

        return $validator;
    }

    /**
     * Add libraries_notebooks integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['library_id'], 'Libraries'), ['errorField' => 'library_id']);
        $rules->add($rules->existsIn(['notebook_id'], 'Notebooks'), ['errorField' => 'notebook_id']);

        return $rules;
    }
}
