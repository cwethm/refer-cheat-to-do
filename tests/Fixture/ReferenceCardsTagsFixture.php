<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class ReferenceCardsTagsFixture extends TestFixture
{
    public string $table = 'reference_cards_tags';

    public function init(): void
    {
        $this->records = [
            ['reference_card_id' => 1000, 'tag_id' => 100],
            ['reference_card_id' => 1002, 'tag_id' => 102],
        ];

        parent::init();
    }
}
