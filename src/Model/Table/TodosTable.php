<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\Todo;
use App\Service\ReviewSchedulingService;
use App\Service\TodoRelationshipService;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class TodosTable extends Table
{
    /**
     * Statuses a ToDo may hold. Transitions between them are owned by TodoLifecycleService.
     *
     * @var list<string>
     */
    public const ALLOWED_STATUSES = ['inbox', 'active', 'done', 'archived', 'trashed'];

    /**
     * Statuses a client may set directly through ToDo create/update payloads.
     *
     * Archive and trash are only reachable through explicit lifecycle actions.
     *
     * @var list<string>
     */
    public const DIRECTLY_ASSIGNABLE_STATUSES = ['inbox', 'active'];

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
        $this->belongsTo('ParentTodo', [
            'className' => 'Todos',
            'foreignKey' => 'parent_todo_id',
            'joinType' => 'LEFT',
        ]);
        $this->hasMany('ChildTodos', [
            'className' => 'Todos',
            'foreignKey' => 'parent_todo_id',
            'dependent' => false,
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

        $validator
            ->dateTime('archived_at')
            ->allowEmptyDateTime('archived_at');

        $validator
            ->dateTime('trashed_at')
            ->allowEmptyDateTime('trashed_at');

        $validator
            ->dateTime('last_reviewed_at')
            ->allowEmptyDateTime('last_reviewed_at');

        $validator
            ->dateTime('next_review_at')
            ->allowEmptyDateTime('next_review_at');

        $validator
            ->integer('review_interval_days')
            ->range('review_interval_days', [
                ReviewSchedulingService::MIN_INTERVAL_DAYS,
                ReviewSchedulingService::MAX_INTERVAL_DAYS,
            ])
            ->notEmptyString('review_interval_days');

        $validator
            ->integer('parent_todo_id')
            ->greaterThan('parent_todo_id', 0)
            ->allowEmptyString('parent_todo_id');

        $validator
            ->scalar('terminal_objective')
            ->maxLength('terminal_objective', TodoRelationshipService::MAX_OBJECTIVE_LENGTH)
            ->allowEmptyString('terminal_objective');

        $validator
            ->scalar('result_summary')
            ->maxLength('result_summary', TodoRelationshipService::MAX_OBJECTIVE_LENGTH)
            ->allowEmptyString('result_summary');

        $validator
            ->dateTime('objective_satisfied_at')
            ->allowEmptyDateTime('objective_satisfied_at');

        $validator
            ->scalar('previous_status')
            ->inList('previous_status', self::ALLOWED_STATUSES)
            ->allowEmptyString('previous_status');

        return $validator;
    }

    /**
     * Add todos table integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);
        $rules->add($rules->existsIn(['parent_todo_id'], 'ParentTodo', [
            'allowNullableNulls' => true,
        ]), ['errorField' => 'parent_todo_id']);
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
