<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Behavior\SectionOrderingBehavior;
use App\Model\Entity\ProjectSection;
use ArrayObject;
use Cake\Event\EventInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class ProjectSectionsTable extends Table
{
    /**
     * Initialize project_sections table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('project_sections');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->addBehavior('SectionOrdering', ['parentField' => 'project_id']);

        $this->belongsTo('Projects', [
            'foreignKey' => 'project_id',
            'joinType' => 'INNER',
        ]);
        $this->hasMany('Todos', [
            'foreignKey' => 'project_section_id',
            'sort' => ['Todos.id' => 'ASC'],
            'dependent' => false,
        ]);
    }

    /**
     * Trim incoming section names.
     *
     * @param \Cake\Event\EventInterface<\Cake\ORM\Table> $event beforeMarshal event.
     * @param \ArrayObject<string, mixed> $data Incoming request data.
     * @param \ArrayObject<string, mixed> $options Marshal options.
     */
    public function beforeMarshal(EventInterface $event, ArrayObject $data, ArrayObject $options): void
    {
        if (isset($data['name']) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }
    }

    /**
     * Define project section validators.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('project_id')
            ->greaterThan('project_id', 0)
            ->requirePresence('project_id', 'create')
            ->notEmptyString('project_id');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->integer('position')
            ->greaterThan('position', 0)
            ->requirePresence('position', 'create')
            ->notEmptyString('position');

        return $validator;
    }

    /**
     * Add project section integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['project_id'], 'Projects'), ['errorField' => 'project_id']);

        return $rules;
    }

    /**
     * Ordering helper for this table's sections.
     */
    private function ordering(): SectionOrderingBehavior
    {
        /** @var \App\Model\Behavior\SectionOrderingBehavior $behavior */
        $behavior = $this->getBehavior('SectionOrdering');

        return $behavior;
    }

    /**
     * Next available ordering position within a project.
     */
    public function nextPosition(int $projectId): int
    {
        return $this->ordering()->nextPosition($projectId);
    }

    /**
     * Move a section to an explicit 1-based position within its project.
     */
    public function moveToPosition(ProjectSection $section, int $position): void
    {
        $this->ordering()->moveToPosition($section, $position);
    }

    /**
     * Keep section positions contiguous within a project.
     */
    public function renumberPositions(int $projectId): void
    {
        $this->ordering()->renumberPositions($projectId);
    }
}
