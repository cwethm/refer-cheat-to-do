<?php
declare(strict_types=1);

use App\Error\ApiExceptionRenderer;
use Cake\Cache\Engine\FileEngine;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Log\Engine\FileLog;
use Cake\Mailer\Transport\MailTransport;
use function Cake\Core\env;

$debug = filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN);
$appName = env('APP_NAME', 'refer-cheat-to-do');
$appEnv = env('APP_ENV', 'production');
$dbHost = env('DB_HOST', 'localhost');
$dbPort = (int)env('DB_PORT', '5432');
$dbName = env('DB_DATABASE', 'refer_cheat_to_do');
$dbUser = env('DB_USERNAME', 'refer_cheat_to_do');
$dbPassword = env('DB_PASSWORD', 'refer_cheat_to_do');
$testDbHost = env('TEST_DB_HOST', $dbHost);
$testDbPort = (int)env('TEST_DB_PORT', (string)$dbPort);
$testDbUser = env('TEST_DB_USERNAME', $dbUser);
$testDbPassword = env('TEST_DB_PASSWORD', $dbPassword);
$testDbName = env('TEST_DB_DATABASE', $dbName . '_test');

return [
    'debug' => $debug,
    'App' => [
        'namespace' => 'App',
        'encoding' => env('APP_ENCODING', 'UTF-8'),
        'defaultLocale' => env('APP_DEFAULT_LOCALE', 'en_US'),
        'defaultTimezone' => env('APP_DEFAULT_TIMEZONE', 'UTC'),
        'fullBaseUrl' => env('APP_FULL_BASE_URL', null),
        'name' => $appName,
        'env' => $appEnv,
    ],
    'Security' => [
        'salt' => env('SECURITY_SALT', 'change-me-to-a-long-random-string'),
    ],
    'Asset' => [
        'cacheTime' => '+1 year',
    ],
    'Error' => [
        'errorLevel' => E_ALL,
        'skipLog' => [],
        'log' => true,
        'trace' => $debug,
        'traceFormat' => null,
        'exceptionRenderer' => ApiExceptionRenderer::class,
    ],
    'Cache' => [
        'default' => [
            'className' => FileEngine::class,
            'path' => CACHE,
            'url' => env('CACHE_DEFAULT_URL', null),
        ],
        '_cake_core_' => [
            'className' => FileEngine::class,
            'prefix' => $appName . '_cake_core_',
            'path' => CACHE . 'persistent' . DS,
            'serialize' => true,
            'duration' => '+2 minutes',
            'url' => env('CACHE_CAKECORE_URL', null),
        ],
        '_cake_model_' => [
            'className' => FileEngine::class,
            'prefix' => $appName . '_cake_model_',
            'path' => CACHE . 'models' . DS,
            'serialize' => true,
            'duration' => '+2 minutes',
            'url' => env('CACHE_CAKEMODEL_URL', null),
        ],
        '_cake_translations_' => [
            'className' => FileEngine::class,
            'prefix' => $appName . '_cake_translations_',
            'path' => CACHE . 'persistent' . DS,
            'serialize' => true,
            'duration' => '+2 minutes',
        ],
    ],
    'EmailTransport' => [
        'default' => [
            'className' => MailTransport::class,
            'host' => 'localhost',
            'port' => 25,
            'timeout' => 30,
            'client' => null,
            'tls' => false,
            'url' => env('EMAIL_TRANSPORT_DEFAULT_URL', null),
        ],
    ],
    'Email' => [
        'default' => [
            'transport' => 'default',
            'from' => 'noreply@localhost',
        ],
    ],
    'Datasources' => [
        'default' => [
            'className' => Connection::class,
            'driver' => Postgres::class,
            'host' => $dbHost,
            'port' => $dbPort,
            'username' => $dbUser,
            'password' => $dbPassword,
            'database' => $dbName,
            'schema' => 'public',
            'encoding' => 'utf8',
            'timezone' => 'UTC',
            'persistent' => false,
            'cacheMetadata' => true,
            'quoteIdentifiers' => false,
            'log' => false,
            'url' => env('DATABASE_URL', null),
        ],
        'test' => [
            'className' => Connection::class,
            'driver' => Postgres::class,
            'host' => $testDbHost,
            'port' => $testDbPort,
            'username' => $testDbUser,
            'password' => $testDbPassword,
            'database' => $testDbName,
            'schema' => 'public',
            'encoding' => 'utf8',
            'timezone' => 'UTC',
            'persistent' => false,
            'cacheMetadata' => true,
            'quoteIdentifiers' => false,
            'log' => false,
            'url' => env('DATABASE_TEST_URL', null),
        ],
    ],
    'Log' => [
        'debug' => [
            'className' => FileLog::class,
            'path' => LOGS,
            'file' => 'debug',
            'url' => env('LOG_DEBUG_URL', null),
            'levels' => ['notice', 'info', 'debug'],
        ],
        'error' => [
            'className' => FileLog::class,
            'path' => LOGS,
            'file' => 'error',
            'url' => env('LOG_ERROR_URL', null),
            'levels' => ['warning', 'error', 'critical', 'alert', 'emergency'],
        ],
    ],
];
