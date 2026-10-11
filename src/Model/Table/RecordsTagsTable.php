<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class RecordsTagsTable extends Table
{
    /**
     * Initialize the record/tag join table.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('records_tags');
        $this->setPrimaryKey(['record_id', 'tag_id']);

        $this->belongsTo('Records', [
            'foreignKey' => 'record_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Tags', [
            'foreignKey' => 'tag_id',
            'joinType' => 'INNER',
        ]);
    }
}
