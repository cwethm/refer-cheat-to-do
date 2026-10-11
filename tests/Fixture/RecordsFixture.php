<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class RecordsFixture extends TestFixture
{
    public string $table = 'records';

    public function init(): void
    {
        $this->records = [
            [
                'id' => 1200,
                'user_id' => 1,
                'kind' => 'discussion',
                'title' => 'Roadmap check-in',
                'occurred_at' => '2026-03-01 10:00:00',
                'participants' => 'Team lead (TL), me',
                'location' => null,
                'summary' => 'Agreed to move the export work earlier.',
                'details' => 'TL raised the backup risk; export now precedes sharing.',
                'created' => '2026-03-01 11:00:00',
                'modified' => '2026-03-01 11:00:00',
            ],
            [
                'id' => 1201,
                'user_id' => 1,
                'kind' => 'event',
                'title' => 'Accessibility meetup',
                'occurred_at' => '2026-03-02 18:30:00',
                'participants' => null,
                'location' => 'Community hall',
                'summary' => null,
                'details' => 'Talk on focus order in single-page apps.',
                'created' => '2026-03-02 20:00:00',
                'modified' => '2026-03-02 20:00:00',
            ],
            [
                'id' => 1202,
                'user_id' => 2,
                'kind' => 'discussion',
                'title' => 'Other user record',
                'occurred_at' => '2026-03-01 10:00:00',
                'participants' => 'Private to user two',
                'location' => null,
                'summary' => null,
                'details' => null,
                'created' => '2026-03-01 11:00:00',
                'modified' => '2026-03-01 11:00:00',
            ],
        ];

        parent::init();
    }
}
