<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class RecordsTagsFixture extends TestFixture
{
    public string $table = 'records_tags';

    public function init(): void
    {
        $this->records = [
            ['record_id' => 1200, 'tag_id' => 100],
            ['record_id' => 1202, 'tag_id' => 102],
        ];

        parent::init();
    }
}
