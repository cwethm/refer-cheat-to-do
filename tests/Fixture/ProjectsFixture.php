<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class ProjectsFixture extends TestFixture
{
    /**
     * Initialize fixture records.
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 200,
                'user_id' => 1,
                'name' => 'Owner project',
                'description' => 'owner project description',
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 201,
                'user_id' => 2,
                'name' => 'Other user project',
                'description' => null,
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
        ];

        parent::init();
    }
}
