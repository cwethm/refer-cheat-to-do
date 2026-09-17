<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class LibrariesProjectsFixture extends TestFixture
{
    /**
     * Database table backing this fixture.
     */
    public string $table = 'libraries_projects';

    /**
     * Initialize fixture records.
     */
    public function init(): void
    {
        $this->records = [];

        parent::init();
    }
}
