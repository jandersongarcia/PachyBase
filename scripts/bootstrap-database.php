<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Migrations\FilesystemMigrationLoader;
use PachyBase\Database\Migrations\MigrationRunner;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Seeds\FilesystemSeedLoader;
use PachyBase\Database\Seeds\SeedRunner;

Config::load(dirname(__DIR__));

$timeout = 60;
$intervalMs = 2000;
$migrationDirectory = dirname(__DIR__) . '/database/migration-files';
$seedDirectory = dirname(__DIR__) . '/database/seed-files';
$skipSeeds = false;

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--timeout=')) {
        $timeout = max(1, (int) substr($argument, 10));
        continue;
    }

    if (str_starts_with($argument, '--interval-ms=')) {
        $intervalMs = max(100, (int) substr($argument, 14));
        continue;
    }

    if (str_starts_with($argument, '--migrations-path=')) {
        $migrationDirectory = substr($argument, 18);
        continue;
    }

    if (str_starts_with($argument, '--seeds-path=')) {
        $seedDirectory = substr($argument, 13);
        continue;
    }

    if ($argument === '--skip-seeds') {
        $skipSeeds = true;
    }
}

$attempts = waitUntilDatabaseReady($timeout, $intervalMs);
$connection = Connection::getInstance();
$adapter = AdapterFactory::make($connection);
$queryExecutor = new PdoQueryExecutor($connection->getPDO());

$migrationLoader = new FilesystemMigrationLoader();
$migrationRunner = new MigrationRunner($queryExecutor, $adapter);
$migrations = $migrationLoader->load($migrationDirectory);
$migrationResult = $migrationRunner->migrate($migrations);

$seedResult = null;

if (!$skipSeeds) {
    $seedLoader = new FilesystemSeedLoader();
    $seedRunner = new SeedRunner($queryExecutor, $adapter);
    $seeders = $seedLoader->load($seedDirectory);
    $seedResult = $seedRunner->run($seeders);
}

echo json_encode([
    'driver' => $adapter->driver(),
    'attempts' => $attempts,
    'migrations' => $migrationResult,
    'seeds' => $seedResult,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

/**
 * @throws RuntimeException
 */
function waitUntilDatabaseReady(int $timeoutSeconds, int $intervalMs): int
{
    $deadline = microtime(true) + $timeoutSeconds;
    $attempts = 0;
    $lastMessage = 'Database connection failed.';

    while (microtime(true) < $deadline) {
        $attempts++;

        try {
            Connection::reset();
            $pdo = Connection::getInstance()->getPDO();
            $pdo->query('SELECT 1');

            return $attempts;
        } catch (Throwable $exception) {
            $lastMessage = $exception->getMessage();
            usleep($intervalMs * 1000);
        }
    }

    throw new RuntimeException(
        sprintf('Database did not become ready within %d seconds. Last error: %s', $timeoutSeconds, $lastMessage)
    );
}
