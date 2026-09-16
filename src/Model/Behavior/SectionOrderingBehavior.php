<?php
declare(strict_types=1);

namespace App\Model\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Behavior;
use InvalidArgumentException;

/**
 * Contiguous 1-based ordering for section rows that belong to a parent container.
 *
 * Extracted once ProjectSections and NotebookSections proved to need identical ordering
 * mechanics. Domain validation, ownership, and assignment rules stay in each table class.
 *
 * Configuration:
 * - `parentField`: the foreign key grouping siblings, e.g. `project_id` or `notebook_id`.
 */
class SectionOrderingBehavior extends Behavior
{
    /**
     * Default behavior configuration.
     *
     * @var array<string, mixed>
     */
    protected array $_defaultConfig = [
        'parentField' => null,
        'positionField' => 'position',
    ];

    /**
     * Next available ordering position within a parent container.
     */
    public function nextPosition(int $parentId): int
    {
        $table = $this->table();
        $positionField = (string)$this->getConfig('positionField');
        $row = $table->find()
            ->where([$this->parentField() => $parentId])
            ->select(['max' => $table->find()->func()->max($positionField)])
            ->first();
        $current = $row === null ? null : $row->get('max');

        return is_numeric($current) ? (int)$current + 1 : 1;
    }

    /**
     * Move a section to an explicit 1-based position and renumber its siblings.
     *
     * @throws \InvalidArgumentException When the requested position is outside the sibling range.
     */
    public function moveToPosition(EntityInterface $section, int $position): void
    {
        $table = $this->table();
        $parentId = (int)$section->get($this->parentField());

        $table->getConnection()->transactional(function () use ($table, $section, $position, $parentId): void {
            $siblings = $this->orderedSiblings($parentId);
            if ($position < 1 || $position > count($siblings)) {
                throw new InvalidArgumentException('Invalid section position.');
            }

            $ordered = [];
            foreach ($siblings as $sibling) {
                if ((int)$sibling->get('id') !== (int)$section->get('id')) {
                    $ordered[] = $sibling;
                }
            }
            array_splice($ordered, $position - 1, 0, [$section]);

            $this->writePositions($ordered);
            unset($table);
        });
    }

    /**
     * Keep sibling positions contiguous, e.g. after a deletion.
     */
    public function renumberPositions(int $parentId): void
    {
        $this->writePositions($this->orderedSiblings($parentId));
    }

    /**
     * Load siblings of a parent container in current display order.
     *
     * @return list<\Cake\Datasource\EntityInterface>
     */
    private function orderedSiblings(int $parentId): array
    {
        $positionField = (string)$this->getConfig('positionField');

        /** @var list<\Cake\Datasource\EntityInterface> $siblings */
        $siblings = $this->table()->find()
            ->where([$this->parentField() => $parentId])
            ->orderBy([$positionField => 'ASC', 'id' => 'ASC'])
            ->all()
            ->toList();

        return $siblings;
    }

    /**
     * Persist contiguous positions for the given ordered siblings.
     *
     * @param list<\Cake\Datasource\EntityInterface> $ordered
     */
    private function writePositions(array $ordered): void
    {
        $table = $this->table();
        $positionField = (string)$this->getConfig('positionField');
        foreach ($ordered as $index => $sibling) {
            $expected = $index + 1;
            if ((int)$sibling->get($positionField) === $expected) {
                continue;
            }
            $sibling->set($positionField, $expected);
            $table->saveOrFail($sibling);
        }
    }

    /**
     * Resolve the configured parent foreign key.
     */
    private function parentField(): string
    {
        $field = $this->getConfig('parentField');
        if (!is_string($field) || $field === '') {
            throw new InvalidArgumentException('SectionOrdering behavior requires a parentField.');
        }

        return $field;
    }
}
