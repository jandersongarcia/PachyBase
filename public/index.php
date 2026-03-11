<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PachyBase\Config;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\ErrorHandler;

Config::load();
ErrorHandler::register();

ApiResponse::success([
    'name' => Config::get('APP_NAME', 'PachyBase'),
    'status' => 'running',
    'database' => [
        'driver' => Config::get('DB_DRIVER'),
        'host' => Config::get('DB_HOST'),
        'port' => Config::get('DB_PORT'),
        'database' => Config::get('DB_DATABASE'),
    ],
], [
    'resource' => 'system.status',
]);
