<?php
declare(strict_types=1);

use App\Error\ApiExceptionRenderer;
use App\Error\SafeErrorLogger;
use App\Log\Engine\SafeFileLog;
use Cake\Cache\Engine\FileEngine;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Mailer\Transport\MailTransport;
use function Cake\Core\env;

$debug = filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN);
$appName = env('APP_NAME', 'refer-cheat-to-do');
$appEnv = env('APP_ENV', 'production');
$dbHost = env('DB_HOST', '127.0.0.1');
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
        'paths' => [
            'templates' => [ROOT . DS . 'templates' . DS],
        ],
    ],
    'Security' => [
        'salt' => env('SECURITY_SALT', 'change-me-to-a-long-random-string'),
    ],
    /*
     * Sessions (Slice 19). `timeout` is the idle expiry in minutes, enforced by the server-side
     * session; `Accounts.sessionLifetime` is the absolute expiry checked on every API request.
     * The cookie is HTTP-only, SameSite=Strict and, on HTTPS, Secure; strict mode refuses
     * session ids the server did not issue.
     */
    'Session' => [
        'defaults' => 'php',
        'timeout' => 120,
        'ini' => [
            'session.cookie_httponly' => true,
            'session.cookie_samesite' => 'Strict',
            'session.use_strict_mode' => true,
        ],
    ],
    'Accounts' => [
        // Absolute session lifetime in seconds, counted from sign-in.
        'sessionLifetime' => 12 * 3600,
        'invitationLifetime' => 7 * 24 * 3600,
        'passwordResetLifetime' => 3600,
    ],
    /*
     * Rate limits for authentication-related operations, stored in `rate_limit_hits`.
     * `limit` attempts are allowed per `window` seconds for each bucket.
     */
    'RateLimit' => [
        // Failed sign-ins for one email address, and from one client address.
        'login_account' => ['limit' => 5, 'window' => 900],
        'login_client' => ['limit' => 20, 'window' => 900],
        // Reset requests for one address, and from one client address, counted whether known or not.
        'password_reset_account' => ['limit' => 3, 'window' => 3600],
        'password_reset_client' => ['limit' => 10, 'window' => 3600],
        // Failed invitation or reset redemptions from one client address.
        'token_redeem_client' => ['limit' => 10, 'window' => 900],
        // Wrong current passwords for one signed-in account.
        'password_change_account' => ['limit' => 5, 'window' => 900],
        // Invitations issued by one account.
        'invitation_issue_account' => ['limit' => 10, 'window' => 86400],
    ],
    'Asset' => [
        'cacheTime' => '+1 year',
    ],
    'Error' => [
        'errorLevel' => E_ALL,
        'skipLog' => [],
        'log' => true,
        'logger' => SafeErrorLogger::class,
        'trace' => false,
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
            'from' => env('EMAIL_FROM', 'noreply@localhost.localdomain'),
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
            'className' => SafeFileLog::class,
            'path' => LOGS,
            'file' => 'debug',
            'levels' => ['notice', 'info', 'debug'],
        ],
        'error' => [
            'className' => SafeFileLog::class,
            'path' => LOGS,
            'file' => 'error',
            'levels' => ['warning', 'error', 'critical', 'alert', 'emergency'],
        ],
    ],
];
