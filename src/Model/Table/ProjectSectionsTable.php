<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\ProjectSection;
use ArrayObject;
use Cake\Event\EventInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use InvalidArgumentException;

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
     * Next available ordering position within a project.
     */
    public function nextPosition(int $projectId): int
    {
        $max = $this->find()
            ->where(['project_id' => $projectId])
            ->select(['max' => $this->find()->func()->max('position')])
            ->first();
        $current = $max === null ? null : $max->get('max');

        return is_numeric($current) ? (int)$current + 1 : 1;
    }

    /**
     * Keep section positions contiguous, e.g. after a deletion.
     */
    public function renumberPositions(int $projectId): void
    {
        $position = 0;
        $query = $this->find()
            ->where(['project_id' => $projectId])
            ->orderBy(['position' => 'ASC', 'id' => 'ASC']);
        foreach ($query as $section) {
            /** @var \App\Model\Entity\ProjectSection $section */
            $position++;
            if ((int)$section->position === $position) {
                continue;
            }
            $section->set('position', $position);
            $this->saveOrFail($section);
        }
    }

    /**
     * Move a section to an explicit 1-based position and renumber its siblings.
     *
     * @throws \InvalidArgumentException When the requested position is outside the project range.
     */
    public function moveToPosition(ProjectSection $section, int $position): void
    {
        $projectId = (int)$section->project_id;
        $this->getConnection()->transactional(function () use ($section, $position, $projectId): void {
            /** @var list<\App\Model\Entity\ProjectSection> $siblings */
            $siblings = $this->find()
                ->where(['project_id' => $projectId])
                ->orderBy(['position' => 'ASC', 'id' => 'ASC'])
                ->all()
                ->toList();

            if ($position < 1 || $position > count($siblings)) {
                throw new InvalidArgumentException('Invalid section position.');
            }

            $ordered = [];
            foreach ($siblings as $sibling) {
                if ((int)$sibling->id !== (int)$section->id) {
                    $ordered[] = $sibling;
                }
            }
            array_splice($ordered, $position - 1, 0, [$section]);

            foreach ($ordered as $index => $sibling) {
                $expected = $index + 1;
                if ((int)$sibling->position === $expected) {
                    continue;
                }
                $sibling->set('position', $expected);
                $this->saveOrFail($sibling);
            }
        });
    }
}
