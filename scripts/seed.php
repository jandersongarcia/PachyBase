<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Seeds\FilesystemSeedLoader;
use PachyBase\Database\Seeds\SeedRunner;

Config::load(dirname(__DIR__));

$command = $argv[1] ?? 'status';
$directory = dirname(__DIR__) . '/database/seed-files';
$force = false;

foreach (array_slice($argv, 2) as $argument) {
    if (str_starts_with($argument, '--path=')) {
        $directory = substr($argument, 7);
        continue;
    }

    if ($argument === '--force') {
        $force = true;
    }
}

$connection = Connection::getInstance();
$adapter = AdapterFactory::make($connection);
$queryExecutor = new PdoQueryExecutor($connection->getPDO());
$loader = new FilesystemSeedLoader();
$runner = new SeedRunner($queryExecutor, $adapter);
$seeders = $loader->load($directory);

$payload = match ($command) {
    'run', 'seed' => [
        'command' => 'run',
        'driver' => $adapter->driver(),
        'directory' => $directory,
        'result' => $runner->run($seeders, $force),
    ],
    'status' => [
        'command' => 'status',
        'driver' => $adapter->driver(),
        'directory' => $directory,
        'seeders' => $runner->status($seeders),
    ],
    default => [
        'command' => 'help',
        'driver' => $adapter->driver(),
        'directory' => $directory,
        'error' => sprintf('Unsupported seed command "%s". Use status or run.', $command),
    ],
};

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
