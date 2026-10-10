<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class ResearchCollectionsFixture extends TestFixture
{
    public string $table = 'research_collections';

    public function init(): void
    {
        $this->records = [
            [
                'id' => 900,
                'user_id' => 1,
                'created_by_user_id' => 1,
                'name' => 'Owner collection',
                'description' => 'Private notes',
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 901,
                'user_id' => 2,
                'created_by_user_id' => 2,
                'name' => 'Other collection',
                'description' => null,
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 902,
                'user_id' => 3,
                'created_by_user_id' => 3,
                'name' => 'Restricted owner collection',
                'description' => null,
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
        ];

        parent::init();
    }
}
