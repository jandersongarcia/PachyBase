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

$basePath = dirname(__DIR__);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(dbFreshMain($argv, $basePath));
}

function dbFreshMain(array $argv, string $basePath): int
{
    Config::load($basePath);

    $skipSeeds = in_array('--skip-seeds', $argv, true);
    $connection = Connection::getInstance();
    $adapter = AdapterFactory::make($connection);
    $executor = new PdoQueryExecutor($connection->getPDO());

    $droppedTables = dbFreshDropTables($adapter, $executor);

    $migrationLoader = new FilesystemMigrationLoader();
    $migrationRunner = new MigrationRunner($executor, $adapter);
    $migrations = $migrationLoader->load($basePath . '/database/migration-files');
    $migrationResult = $migrationRunner->migrate($migrations);

    $seedResult = null;

    if (!$skipSeeds) {
        $seedLoader = new FilesystemSeedLoader();
        $seedRunner = new SeedRunner($executor, $adapter);
        $seeders = $seedLoader->load($basePath . '/database/seed-files');
        $seedResult = $seedRunner->run($seeders, true);
    }

    echo json_encode([
        'driver' => $adapter->driver(),
        'dropped_tables' => $droppedTables,
        'migrations' => $migrationResult,
        'seeds' => $seedResult,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    return 0;
}

function dbFreshDropTables($adapter, PdoQueryExecutor $executor): array
{
    $tables = array_filter(
        $adapter->listTables(),
        static fn($table): bool => strtoupper($table->type) === 'BASE TABLE'
    );

    if ($tables === []) {
        return [];
    }

    $tableNames = array_map(static fn($table): string => $table->name, $tables);

    if ($adapter->driver() === 'mysql') {
        $executor->execute('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tables as $table) {
            $executor->execute('DROP TABLE IF EXISTS ' . $adapter->quoteIdentifier($table->name));
        }

        $executor->execute('SET FOREIGN_KEY_CHECKS = 1');

        return $tableNames;
    }

    foreach ($tables as $table) {
        $executor->execute('DROP TABLE IF EXISTS ' . $adapter->quoteIdentifier($table->name) . ' CASCADE');
    }

    return $tableNames;
}
