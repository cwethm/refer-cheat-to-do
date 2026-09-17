<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class LibrariesNotebooksFixture extends TestFixture
{
    /**
     * Database table backing this fixture.
     */
    public string $table = 'libraries_notebooks';

    /**
     * Initialize fixture records.
     */
    public function init(): void
    {
        $this->records = [];

        parent::init();
    }
}
