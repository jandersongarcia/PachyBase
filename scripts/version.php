<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Release/ProjectMetadata.php';

use PachyBase\Release\ProjectMetadata;

$basePath = dirname(__DIR__);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(versionMain($basePath));
}

function versionMain(string $basePath): int
{
    fwrite(STDOUT, ProjectMetadata::version($basePath) . PHP_EOL);

    return 0;
}
