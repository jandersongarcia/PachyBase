<?php

declare(strict_types=1);

use PachyBase\Config;
use PachyBase\Services\Platform\ProjectPlatformService;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(projectBackupMain($argv, $basePath));
}

function projectBackupMain(array $argv, string $basePath): int
{
    Config::load($basePath);
    $options = projectBackupParseArguments(array_slice($argv, 1));
    $payload = (new ProjectPlatformService())->createBackup($options['project'], null, $options['label']);

    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

    return 0;
}

/**
 * @param array<int, string> $arguments
 * @return array{project: string, label: string|null}
 */
function projectBackupParseArguments(array $arguments): array
{
    $project = null;
    $label = null;

    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--project=')) {
            $project = trim(substr($argument, 10));
            continue;
        }

        if (str_starts_with($argument, '--label=')) {
            $label = trim(substr($argument, 8)) ?: null;
        }
    }

    if ($project === null || $project === '') {
        throw new RuntimeException('project:backup requires --project.');
    }

    return ['project' => $project, 'label' => $label];
}
