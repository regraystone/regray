<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Env;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Apache's thread-safe mod_php (Laragon, XAMPP) serves requests as threads of
// one process. putenv() is shared by them and undone when each request ends,
// so a request could find .env already loaded, then lose it mid-request: a
// random 500 for a missing APP_KEY. Keep each request's .env to itself.
if (PHP_ZTS && PHP_SAPI === 'apache2handler') {
    Env::disablePutenv();
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
