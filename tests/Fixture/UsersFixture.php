<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\Auth\DefaultPasswordHasher;
use Cake\TestSuite\Fixture\TestFixture;

class UsersFixture extends TestFixture
{
    public string $table = 'users';

    /**
     * @var array<string, mixed>
     */
    public array $fields = [
        'id' => ['type' => 'integer'],
        'email' => ['type' => 'string', 'length' => 255, 'null' => false],
        'password' => ['type' => 'string', 'length' => 255, 'null' => false],
        'created' => ['type' => 'datetime', 'null' => false],
        'modified' => ['type' => 'datetime', 'null' => false],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'users_email_unique' => ['type' => 'unique', 'columns' => ['email']],
        ],
    ];

    public function init(): void
    {
        $hasher = new DefaultPasswordHasher();

        $this->records = [
            [
                'id' => 1,
                'email' => 'owner@example.com',
                'password' => $hasher->hash('Password123!'),
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
            [
                'id' => 2,
                'email' => 'other@example.com',
                'password' => $hasher->hash('Password123!'),
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
        ];

        parent::init();
    }
}
