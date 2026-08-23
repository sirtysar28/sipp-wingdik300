<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// MAINTENANCE MODE
if (file_exists($maintenance = __DIR__.'/../penilaian.dovlenseventy.com/storage/framework/maintenance.php')) {
    require $maintenance;
}

// AUTOLOAD
require __DIR__.'/../penilaian.dovlenseventy.com/vendor/autoload.php';

// BOOTSTRAP
$app = require_once __DIR__.'/../penilaian.dovlenseventy.com/bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);