<?php
declare(strict_types=1);

use Cake\Chronos\Chronos;
use Cake\Core\Configure;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';

// Integration tests always request `localhost`, so the base URL is pinned here instead of being
// inherited from deployment configuration, which would make HostHeaderMiddleware reject every
// request with a 400 once `debug` is disabled.
if (empty($_SERVER['HTTP_HOST'])) {
    Configure::write('App.fullBaseUrl', 'http://localhost');
}

Chronos::setTestNow(Chronos::now());
session_id('cli');
