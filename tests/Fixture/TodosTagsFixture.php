<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class TodosTagsFixture extends TestFixture
{
    /**
     * Initialize fixture records.
     */
    public function init(): void
    {
        $this->records = [
            [
                'todo_id' => 10,
                'tag_id' => 100,
            ],
            [
                'todo_id' => 11,
                'tag_id' => 101,
            ],
            [
                'todo_id' => 12,
                'tag_id' => 102,
            ],
        ];

        parent::init();
    }
}
