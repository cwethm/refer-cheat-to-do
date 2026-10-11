<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class CheatSheetsReferenceCardsFixture extends TestFixture
{
    public string $table = 'cheat_sheets_reference_cards';

    public function init(): void
    {
        $this->records = [
            ['cheat_sheet_id' => 1100, 'reference_card_id' => 1001, 'position' => 1, 'created' => '2026-01-01 00:00:00'],
            ['cheat_sheet_id' => 1100, 'reference_card_id' => 1000, 'position' => 2, 'created' => '2026-01-01 00:00:00'],
            ['cheat_sheet_id' => 1101, 'reference_card_id' => 1002, 'position' => 1, 'created' => '2026-01-01 00:00:00'],
        ];

        parent::init();
    }
}
