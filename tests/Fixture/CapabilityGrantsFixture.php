<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class CapabilityGrantsFixture extends TestFixture
{
    /**
     * Database table backing this fixture.
     */
    public string $table = 'capability_grants';

    /**
     * Initialize fixture records.
     */
    public function init(): void
    {
        $this->records = [];

        parent::init();
    }
}
