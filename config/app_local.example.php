<?php

declare(strict_types=1);

use function Cake\Core\env;

return [
    'debug' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN),
    'App' => [
        'fullBaseUrl' => env('APP_FULL_BASE_URL', 'https://cheatsheet.hellocrow.space'),
    ],
    'Security' => [
        'salt' => env('SECURITY_SALT', '02c6da731c5edbac677347d1b1b27c4ad9bbc6ed6d1f414c637a337b3a0d7eb0'),
    ],
    'Datasources' => [
        'default' => [
            'host' => env('DB_HOST', 'a527437-akamai-prod-2345607-default.g2a.akamaidb.net'),
            'port' => (int)env('DB_PORT', '26719'),
            'username' => env('DB_USERNAME', 'akmadmin'),
            'password' => env('DB_PASSWORD', 'AVNS_9LWQMSo2JS7WjnfEkKh'),
            'database' => env('DB_DATABASE', 'refer_cheat_to_do'),
            'schema' => 'public',
            'url' => env('DATABASE_URL', null),
        ],
        'test' => [
            'host' => env('TEST_DB_HOST', env('DB_HOST', 'a527437-akamai-prod-2345607-default.g2a.akamaidb.net')),
            'port' => (int)env('TEST_DB_PORT', (string)env('DB_PORT', '26719')),
            'username' => env('TEST_DB_USERNAME', env('DB_USERNAME', 'refer_cheat_to_do')),
            'password' => env('TEST_DB_PASSWORD', env('DB_PASSWORD', 'AVNS_9LWQMSo2JS7WjnfEkKh')),
            'database' => env('TEST_DB_DATABASE', 'refer_cheat_to_do_test'),
            'schema' => 'public',
            'url' => env('DATABASE_TEST_URL', null),
        ],
    ],
];
