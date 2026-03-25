<?php

declare(strict_types=1);

use PachyBase\Config;
use PachyBase\Services\Platform\AsyncJobService;
use PachyBase\Services\Tenancy\TenantRepository;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(jobsWorkMain($argv, $basePath));
}

function jobsWorkMain(array $argv, string $basePath): int
{
    Config::load($basePath);
    $options = jobsWorkParseArguments(array_slice($argv, 1));
    $tenant = (new TenantRepository())->resolveReference($options['project']);
    $payload = (new AsyncJobService())->runDue((int) $tenant['id'], $options['limit']);

    fwrite(STDOUT, json_encode(['items' => $payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

    return 0;
}

/**
 * @param array<int, string> $arguments
 * @return array{project: string, limit: int}
 */
function jobsWorkParseArguments(array $arguments): array
{
    $project = 'default';
    $limit = 10;

    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--project=')) {
            $project = trim(substr($argument, 10)) ?: 'default';
            continue;
        }

        if (str_starts_with($argument, '--limit=')) {
            $limit = max(1, (int) substr($argument, 8));
        }
    }

    return ['project' => $project, 'limit' => $limit];
}
