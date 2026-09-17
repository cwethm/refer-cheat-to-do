<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class LibrariesFixture extends TestFixture
{
    /**
     * Database table backing this fixture.
     */
    public string $table = 'libraries';

    /**
     * Initialize fixture records.
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 600,
                'user_id' => 1,
                'name' => 'Owner library',
                'description' => 'owner library description',
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 601,
                'user_id' => 1,
                'name' => 'Second owner library',
                'description' => null,
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 602,
                'user_id' => 2,
                'name' => 'Other user library',
                'description' => null,
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
        ];

        parent::init();
    }
}
