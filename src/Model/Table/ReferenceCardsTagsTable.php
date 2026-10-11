<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class ReferenceCardsTagsTable extends Table
{
    /**
     * Initialize the card/tag join table.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('reference_cards_tags');
        $this->setPrimaryKey(['reference_card_id', 'tag_id']);

        $this->belongsTo('ReferenceCards', [
            'foreignKey' => 'reference_card_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Tags', [
            'foreignKey' => 'tag_id',
            'joinType' => 'INNER',
        ]);
    }
}
