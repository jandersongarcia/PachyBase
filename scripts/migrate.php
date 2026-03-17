<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Migrations\FilesystemMigrationLoader;
use PachyBase\Database\Migrations\MigrationRunner;
use PachyBase\Database\Query\PdoQueryExecutor;

Config::load(dirname(__DIR__));

$command = $argv[1] ?? 'status';
$directory = dirname(__DIR__) . '/database/migration-files';
$steps = 1;

foreach (array_slice($argv, 2) as $argument) {
    if (str_starts_with($argument, '--path=')) {
        $directory = substr($argument, 7);
        continue;
    }

    if (str_starts_with($argument, '--steps=')) {
        $steps = max(1, (int) substr($argument, 8));
    }
}

$connection = Connection::getInstance();
$adapter = AdapterFactory::make($connection);
$queryExecutor = new PdoQueryExecutor($connection->getPDO());
$loader = new FilesystemMigrationLoader();
$runner = new MigrationRunner($queryExecutor, $adapter);
$migrations = $loader->load($directory);

$payload = match ($command) {
    'up', 'migrate' => [
        'command' => 'up',
        'driver' => $adapter->driver(),
        'directory' => $directory,
        'result' => $runner->migrate($migrations),
    ],
    'down', 'rollback' => [
        'command' => 'down',
        'driver' => $adapter->driver(),
        'directory' => $directory,
        'steps' => $steps,
        'result' => $runner->rollback($migrations, $steps),
    ],
    'status' => [
        'command' => 'status',
        'driver' => $adapter->driver(),
        'directory' => $directory,
        'migrations' => $runner->status($migrations),
    ],
    default => [
        'command' => 'help',
        'driver' => $adapter->driver(),
        'directory' => $directory,
        'error' => sprintf('Unsupported migration command "%s". Use status, up, or down.', $command),
    ],
};

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
