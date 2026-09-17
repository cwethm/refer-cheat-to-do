<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Behavior\SectionOrderingBehavior;
use App\Model\Entity\NotebookSection;
use ArrayObject;
use Cake\Event\EventInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class NotebookSectionsTable extends Table
{
    /**
     * Initialize notebook_sections table configuration.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('notebook_sections');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->addBehavior('SectionOrdering', ['parentField' => 'notebook_id']);

        $this->belongsTo('Notebooks', [
            'foreignKey' => 'notebook_id',
            'joinType' => 'INNER',
        ]);
        $this->hasMany('Todos', [
            'foreignKey' => 'notebook_section_id',
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
     * Define notebook section validators.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('notebook_id')
            ->greaterThan('notebook_id', 0)
            ->requirePresence('notebook_id', 'create')
            ->notEmptyString('notebook_id');

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
     * Add notebook section integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['notebook_id'], 'Notebooks'), ['errorField' => 'notebook_id']);

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
     * Next available ordering position within a notebook.
     */
    public function nextPosition(int $notebookId): int
    {
        return $this->ordering()->nextPosition($notebookId);
    }

    /**
     * Move a section to an explicit 1-based position within its notebook.
     */
    public function moveToPosition(NotebookSection $section, int $position): void
    {
        $this->ordering()->moveToPosition($section, $position);
    }

    /**
     * Keep section positions contiguous within a notebook.
     */
    public function renumberPositions(int $notebookId): void
    {
        $this->ordering()->renumberPositions($notebookId);
    }
}
