<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class CheatSheetsFixture extends TestFixture
{
    public string $table = 'cheat_sheets';

    public function init(): void
    {
        $this->records = [
            [
                'id' => 1100,
                'user_id' => 1,
                'name' => 'Meeting sheet',
                'description' => 'Cards to keep in view during meetings.',
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 1101,
                'user_id' => 2,
                'name' => 'Other sheet',
                'description' => null,
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
        ];

        parent::init();
    }
}
