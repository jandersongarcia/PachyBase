<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PachyBase\Config;
use PachyBase\Http\ErrorHandler;
use PachyBase\Http\Request;
use PachyBase\Http\Router;
use PachyBase\Controllers\SystemController;

Config::load();
ErrorHandler::register();

$request = Request::capture();

$router = new Router();

$router->get('/', [SystemController::class, 'status']);

$router->dispatch($request);
