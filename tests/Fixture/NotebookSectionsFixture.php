<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class NotebookSectionsFixture extends TestFixture
{
    /**
     * Initialize fixture records.
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 500,
                'notebook_id' => 400,
                'name' => 'Reference',
                'position' => 1,
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 501,
                'notebook_id' => 400,
                'name' => 'Ideas',
                'position' => 2,
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 502,
                'notebook_id' => 400,
                'name' => 'Archive notes',
                'position' => 3,
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 503,
                'notebook_id' => 401,
                'name' => 'Other user notebook section',
                'position' => 1,
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
        ];

        parent::init();
    }
}
