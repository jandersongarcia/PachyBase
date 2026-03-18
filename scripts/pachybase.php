<?php

declare(strict_types=1);

use PachyBase\Cli\PachybaseCli;

require __DIR__ . '/../vendor/autoload.php';

$cli = new PachybaseCli(dirname(__DIR__));

exit($cli->run(array_slice($argv, 1)));
