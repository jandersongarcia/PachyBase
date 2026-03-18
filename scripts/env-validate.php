<?php

declare(strict_types=1);

use PachyBase\Cli\EnvironmentFileManager;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(envValidateMain($argv, $basePath));
}

function envValidateMain(array $argv, string $basePath): int
{
    $payload = (new EnvironmentFileManager($basePath))->validate();

    if (in_array('--json', $argv, true)) {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        return $payload['errors'] === [] ? 0 : 1;
    }

    foreach ($payload['errors'] as $error) {
        fwrite(STDERR, '[ERROR] ' . $error . PHP_EOL);
    }

    foreach ($payload['warnings'] as $warning) {
        fwrite(STDOUT, '[WARN] ' . $warning . PHP_EOL);
    }

    if ($payload['errors'] === []) {
        fwrite(STDOUT, "Environment validation passed.\n");
    }

    return $payload['errors'] === [] ? 0 : 1;
}
