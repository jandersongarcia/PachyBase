<?php

declare(strict_types=1);

use PachyBase\Config;
use PachyBase\Services\OpenApi\OpenApiDocumentBuilder;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(openapiGenerateMain($argv, $basePath));
}

function openapiGenerateMain(array $argv, string $basePath): int
{
    Config::load($basePath);

    $outputPath = openapiGenerateResolveOutputPath(
        array_slice($argv, 1),
        $basePath . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'openapi.json',
        $basePath
    );

    $payload = openapiGenerateWriteDocumentFile(
        (new OpenApiDocumentBuilder())->build(),
        $outputPath
    );

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    return 0;
}

/**
 * @param array<int, string> $arguments
 */
function openapiGenerateResolveOutputPath(array $arguments, string $defaultPath, string $basePath): string
{
    foreach ($arguments as $argument) {
        if (!str_starts_with($argument, '--output=')) {
            continue;
        }

        return openapiGenerateResolveProjectPath(substr($argument, 9), $basePath);
    }

    return $defaultPath;
}

/**
 * @param array<string, mixed> $document
 * @return array<string, mixed>
 */
function openapiGenerateWriteDocumentFile(array $document, string $outputPath): array
{
    $directory = dirname($outputPath);

    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException(sprintf('Unable to create directory "%s".', $directory));
    }

    $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        throw new RuntimeException('Failed to encode the OpenAPI document.');
    }

    file_put_contents($outputPath, $json . PHP_EOL);

    return [
        'output' => $outputPath,
        'paths' => count($document['paths'] ?? []),
        'schemas' => count($document['components']['schemas'] ?? []),
    ];
}

function openapiGenerateResolveProjectPath(string $path, string $basePath): string
{
    if ($path === '') {
        return $basePath;
    }

    if (preg_match('~^(?:[A-Za-z]:[\\\\/]|[\\\\/]{2}|/)~', $path) === 1) {
        return $path;
    }

    return $basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}
