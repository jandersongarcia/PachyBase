<?php

require __DIR__ . '/../vendor/autoload.php';

use PachyBase\Config;

Config::load();

header('Content-Type: application/json');

echo json_encode([
    "name" => Config::get('APP_NAME', 'PachyBase'),
    "status" => "running",
    "db_driver" => Config::get('DB_DRIVER'),
    "db_host" => Config::get('DB_HOST'),
    "db_port" => Config::get('DB_PORT'),
    "db_database" => Config::get('DB_DATABASE')
]);