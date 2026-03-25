<?php

declare(strict_types=1);

use PachyBase\Config;
use PachyBase\Services\Platform\ProjectPlatformService;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(projectRestoreMain($argv, $basePath));
}

function projectRestoreMain(array $argv, string $basePath): int
{
    Config::load($basePath);
    $options = projectRestoreParseArguments(array_slice($argv, 1));
    $payload = (new ProjectPlatformService())->restoreBackup($options['project'], $options['backup_id']);

    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

    return 0;
}

/**
 * @param array<int, string> $arguments
 * @return array{project: string, backup_id: int}
 */
function projectRestoreParseArguments(array $arguments): array
{
    $project = null;
    $backupId = 0;

    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--project=')) {
            $project = trim(substr($argument, 10));
            continue;
        }

        if (str_starts_with($argument, '--backup-id=')) {
            $backupId = (int) substr($argument, 12);
        }
    }

    if ($project === null || $project === '' || $backupId < 1) {
        throw new RuntimeException('project:restore requires --project and --backup-id.');
    }

    return ['project' => $project, 'backup_id' => $backupId];
}
