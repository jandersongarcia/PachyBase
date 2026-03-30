<?php

declare(strict_types=1);

use PachyBase\Config;
use PachyBase\Services\Platform\ProjectPlatformService;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(projectProvisionMain($argv, $basePath));
}

function projectProvisionMain(array $argv, string $basePath): int
{
    Config::load($basePath);
    $options = projectProvisionParseArguments(array_slice($argv, 1));
    $payload = (new ProjectPlatformService())->provisionProject($options);

    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

    return 0;
}

/**
 * @param array<int, string> $arguments
 * @return array<string, mixed>
 */
function projectProvisionParseArguments(array $arguments): array
{
    $payload = ['settings' => [], 'quotas' => []];

    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--name=')) {
            $payload['name'] = trim(substr($argument, 7));
            continue;
        }

        if (str_starts_with($argument, '--slug=')) {
            $payload['slug'] = trim(substr($argument, 7));
            continue;
        }

        if (str_starts_with($argument, '--admin-email=')) {
            $payload['admin_email'] = trim(substr($argument, 14));
            continue;
        }

        if (str_starts_with($argument, '--admin-password=')) {
            $payload['admin_password'] = (string) substr($argument, 17);
            continue;
        }

        if (str_starts_with($argument, '--quota-requests=')) {
            $payload['quotas']['max_requests_per_month'] = (int) substr($argument, 17);
            continue;
        }

        if (str_starts_with($argument, '--quota-storage=')) {
            $payload['quotas']['max_storage_bytes'] = (int) substr($argument, 16);
            continue;
        }

        if (str_starts_with($argument, '--setting=')) {
            $pair = explode('=', substr($argument, 10), 2);

            if (($pair[0] ?? '') !== '' && array_key_exists(1, $pair)) {
                $payload['settings'][$pair[0]] = $pair[1];
            }
        }
    }

    if (trim((string) ($payload['name'] ?? '')) === '' || trim((string) ($payload['slug'] ?? '')) === '') {
        throw new RuntimeException('project:provision requires --name and --slug.');
    }

    return $payload;
}
