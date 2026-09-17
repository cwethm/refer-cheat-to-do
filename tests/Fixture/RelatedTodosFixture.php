<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class RelatedTodosFixture extends TestFixture
{
    /**
     * Database table backing this fixture.
     */
    public string $table = 'related_todos';

    /**
     * Initialize fixture records.
     */
    public function init(): void
    {
        $this->records = [];

        parent::init();
    }
}
