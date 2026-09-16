<?php

declare(strict_types=1);

use function Cake\Core\env;

return [
    'debug' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN),
    'App' => [
        'fullBaseUrl' => env('APP_FULL_BASE_URL', 'https://cheatsheet.hellocrow.space'),
    ],
    'Security' => [
        'salt' => env('SECURITY_SALT', 'change-me-to-a-long-random-string'),
    ],
    'Datasources' => [
        'default' => [
            'host' => env('DB_HOST', 'localhost'),
            'port' => (int)env('DB_PORT', '5432'),
            'username' => env('DB_USERNAME', 'refer_cheat_to_do'),
            'password' => env('DB_PASSWORD', 'refer_cheat_to_do'),
            'database' => env('DB_DATABASE', 'refer_cheat_to_do'),
            'schema' => 'public',
            'url' => env('DATABASE_URL', null),
        ],
        'test' => [
            'host' => env('TEST_DB_HOST', env('DB_HOST', 'localhost')),
            'port' => (int)env('TEST_DB_PORT', (string)env('DB_PORT', '5432')),
            'username' => env('TEST_DB_USERNAME', env('DB_USERNAME', 'refer_cheat_to_do')),
            'password' => env('TEST_DB_PASSWORD', env('DB_PASSWORD', 'refer_cheat_to_do')),
            'database' => env('TEST_DB_DATABASE', 'refer_cheat_to_do_test'),
            'schema' => 'public',
            'url' => env('DATABASE_TEST_URL', null),
        ],
    ],
];
