<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class NotebooksFixture extends TestFixture
{
    /**
     * Initialize fixture records.
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 400,
                'user_id' => 1,
                'name' => 'Owner notebook',
                'description' => 'owner notebook description',
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 401,
                'user_id' => 2,
                'name' => 'Other user notebook',
                'description' => null,
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
        ];

        parent::init();
    }
}
