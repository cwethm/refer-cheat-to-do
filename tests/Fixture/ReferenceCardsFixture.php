<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class ReferenceCardsFixture extends TestFixture
{
    public string $table = 'reference_cards';

    public function init(): void
    {
        $empty = ['situation' => null, 'aim' => null, 'approach' => null, 'avoid' => null, 'rationale' => null];
        $this->records = [
            [
                'id' => 1000,
                'user_id' => 1,
                'card_type' => 'concept',
                'title' => 'Active listening',
                'summary' => 'Reflect back before responding.',
                'body' => 'Paraphrase what you heard and check it.',
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ] + $empty,
            [
                'id' => 1001,
                'user_id' => 1,
                'card_type' => 'dialogue_pattern',
                'title' => 'Declining scope creep',
                'summary' => null,
                'body' => null,
                'situation' => 'A stakeholder adds work late in the sprint.',
                'aim' => 'Protect the commitment without damaging trust.',
                'approach' => 'Acknowledge, name the trade-off, offer the next slot.',
                'avoid' => 'Silent acceptance.',
                'rationale' => 'Visible trade-offs keep priorities honest.',
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 1002,
                'user_id' => 2,
                'card_type' => 'concept',
                'title' => 'Other user card',
                'summary' => 'Private to user two.',
                'body' => null,
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ] + $empty,
        ];

        parent::init();
    }
}
