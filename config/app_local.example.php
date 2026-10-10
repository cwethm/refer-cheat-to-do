<?php
declare(strict_types=1);

use function Cake\Core\env;

return [
    'debug' => filter_var(env('DEBUG', true), FILTER_VALIDATE_BOOLEAN),
    'App' => [
        'fullBaseUrl' => env('APP_FULL_BASE_URL', 'https//localhost:8765'),
    ],
    'Security' => [
        'salt' => env('SECURITY_SALT', '7ce9b09428bc8777b1c9d569590a21286ead05627bb438737d577b98c97dc360'),
    ],
    'Datasources' => [
        'default' => [
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int)env('DB_PORT', '5432'),
            'username' => env('DB_USERNAME', 'refer_cheat_to_do'),
            'password' => env('DB_PASSWORD', 'C4wc4wc4w_$ong'),
            'database' => env('DB_DATABASE', 'refer_cheat_to_do'),
            'schema' => 'public',
            'url' => env('DATABASE_URL', null),
        ],
    ],
];
