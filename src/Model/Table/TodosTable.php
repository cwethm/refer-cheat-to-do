<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\Todo;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class TodosTable extends Table
{
    /**
     * Allowed ToDo statuses for Slice 2.
     *
     * @var list<string>
     */
    public const ALLOWED_STATUSES = ['inbox', 'active'];

    /**
     * Authoritative containment used whenever a ToDo is serialized with its Tags.
     *
     * @var array<string, mixed>
     */
    private const TAG_CONTAIN = [
        'Tags' => ['sort' => ['Tags.name' => 'ASC', 'Tags.id' => 'ASC']],
    ];

    /**
     * Initialize todos table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('todos');
        $this->setDisplayField('title');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('ProjectSections', [
            'foreignKey' => 'project_section_id',
            'joinType' => 'LEFT',
        ]);
        $this->belongsTo('NotebookSections', [
            'foreignKey' => 'notebook_section_id',
            'joinType' => 'LEFT',
        ]);
        $this->belongsToMany('Tags', [
            'foreignKey' => 'todo_id',
            'targetForeignKey' => 'tag_id',
            'joinTable' => 'todos_tags',
            'dependent' => false,
        ]);
    }

    /**
     * Finder applying the authoritative Tag containment for serialized ToDos.
     *
     * @param \Cake\ORM\Query\SelectQuery<\App\Model\Entity\Todo> $query Query to decorate.
     * @return \Cake\ORM\Query\SelectQuery<\App\Model\Entity\Todo>
     */
    public function findWithTags(SelectQuery $query): SelectQuery
    {
        return $query->contain(self::TAG_CONTAIN);
    }

    /**
     * Load the authoritative Tag association onto an already persisted ToDo.
     */
    public function loadTags(Todo $todo): Todo
    {
        /** @var \App\Model\Entity\Todo $loaded */
        $loaded = $this->loadInto($todo, self::TAG_CONTAIN);

        return $loaded;
    }

    /**
     * Define ToDo validators.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('user_id')
            ->greaterThan('user_id', 0)
            ->requirePresence('user_id', 'create')
            ->notEmptyString('user_id');

        $validator
            ->scalar('title')
            ->maxLength('title', 255)
            ->requirePresence('title', 'create')
            ->notEmptyString('title');

        $validator
            ->integer('project_section_id')
            ->greaterThan('project_section_id', 0)
            ->allowEmptyString('project_section_id');

        $validator
            ->integer('notebook_section_id')
            ->greaterThan('notebook_section_id', 0)
            ->allowEmptyString('notebook_section_id');

        $validator
            ->scalar('notes')
            ->allowEmptyString('notes');

        $validator
            ->scalar('status')
            ->maxLength('status', 20)
            ->requirePresence('status', 'create')
            ->notEmptyString('status')
            ->inList('status', self::ALLOWED_STATUSES);

        return $validator;
    }

    /**
     * Add todos table integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);
        $rules->add($rules->existsIn(['project_section_id'], 'ProjectSections', [
            'allowNullableNulls' => true,
        ]), ['errorField' => 'project_section_id']);
        $rules->add($rules->existsIn(['notebook_section_id'], 'NotebookSections', [
            'allowNullableNulls' => true,
        ]), ['errorField' => 'notebook_section_id']);
        // ADR 0001: a ToDo belongs to at most one organizational section.
        $rules->add(
            fn(EntityInterface $entity): bool => $entity->get('project_section_id') === null
                || $entity->get('notebook_section_id') === null,
            'singleOrganizationalSection',
            [
                'errorField' => 'notebook_section_id',
                'message' => 'A ToDo may belong to only one organizational section.',
            ],
        );

        return $rules;
    }
}
