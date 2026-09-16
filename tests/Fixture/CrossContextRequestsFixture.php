<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class CrossContextRequestsFixture extends TestFixture
{
    /**
     * Database table backing this fixture.
     */
    public string $table = 'cross_context_requests';

    /**
     * Initialize fixture records.
     */
    public function init(): void
    {
        $this->records = [];

        parent::init();
    }
}
