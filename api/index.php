<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Vercel serverless entrypoint: api/index.php is served from /api,
// so project root is one level up.
$root = __DIR__ . '/..';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $root . '/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $root . '/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $root . '/bootstrap/app.php';

$app->handleRequest(Request::capture());
