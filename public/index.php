<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PachyBase\Config\Bootstrap;

Bootstrap::boot(dirname(__DIR__))->handle();
