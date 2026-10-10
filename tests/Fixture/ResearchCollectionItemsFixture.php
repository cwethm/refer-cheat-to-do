<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class ResearchCollectionItemsFixture extends TestFixture
{
    public string $table = 'research_collection_items';

    public function init(): void
    {
        $this->records = [
            [
                'id' => 990,
                'research_collection_id' => 900,
                'title' => 'Source item',
                'content' => 'Plain text source material.',
                'source_uri' => 'https://example.test/source',
                'related_resource_type' => 'todo',
                'related_resource_id' => 10,
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
        ];

        parent::init();
    }
}
