<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class TodosFixture extends TestFixture
{
    /**
     * Initialize fixture records.
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'user_id' => 1,
                'title' => 'Owner inbox todo',
                'notes' => 'owner note',
                'status' => 'inbox',
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'title' => 'Owner active todo',
                'notes' => 'owner active',
                'status' => 'active',
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 3,
                'user_id' => 2,
                'title' => 'Other user todo',
                'notes' => 'other note',
                'status' => 'inbox',
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
        ];
        parent::init();
    }
}
