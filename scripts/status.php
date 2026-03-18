<?php

declare(strict_types=1);

use PachyBase\Cli\EnvironmentFileManager;
use PachyBase\Cli\LocalRuntimeManager;
use PachyBase\Config;
use PachyBase\Config\AuthConfig;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(statusMain($argv, $basePath));
}

function statusMain(array $argv, string $basePath): int
{
    Config::load($basePath);
    $report = statusBuildReport($basePath, in_array('--inside-docker', $argv, true));

    if (in_array('--json', $argv, true)) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        return $report['healthy'] ? 0 : 1;
    }

    fwrite(STDOUT, sprintf("PachyBase status (%s)\n", $report['runtime']['mode']));
    fwrite(STDOUT, sprintf("- runtime: %s\n", $report['runtime']['running'] ? 'running' : 'stopped'));
    fwrite(STDOUT, sprintf("- url: %s\n", $report['runtime']['url']));
    fwrite(STDOUT, sprintf("- database: %s\n", $report['database']['connected'] ? 'connected' : 'not connected'));
    fwrite(STDOUT, sprintf("- auth: %s\n", $report['auth']['ready'] ? 'ready' : 'needs attention'));
    fwrite(STDOUT, sprintf("- docs: openapi=%s ai=%s\n", $report['docs']['openapi'] ? 'ready' : 'missing', $report['docs']['ai'] ? 'ready' : 'missing'));

    return $report['healthy'] ? 0 : 1;
}

/**
 * @return array<string, mixed>
 */
function statusBuildReport(string $basePath, bool $insideDocker = false): array
{
    $env = new EnvironmentFileManager($basePath);
    $runtimeMode = $env->runtimeMode();
    $runtime = $runtimeMode === 'docker'
        ? statusInspectDockerRuntime($basePath, $env->appUrl(), $insideDocker)
        : statusInspectLocalRuntime($basePath, $env);

    $database = [
        'connected' => false,
        'driver' => Config::get('DB_DRIVER'),
        'adapter' => null,
        'message' => null,
    ];

    try {
        Connection::reset();
        $connection = Connection::getInstance();
        $database['connected'] = true;
        $database['adapter'] = AdapterFactory::make($connection, new PdoQueryExecutor($connection->getPDO()))::class;
    } catch (Throwable $exception) {
        $database['message'] = $exception->getMessage();

        if ($runtimeMode === 'docker' && !$insideDocker) {
            $dockerProbe = statusProbeDatabaseThroughDocker($basePath);

            if ($dockerProbe['connected']) {
                $database['connected'] = true;
                $database['adapter'] = 'docker-runtime';
                $database['message'] = null;
            }
        }
    }

    $appKeyConfigured = trim((string) Config::get('APP_KEY', '')) !== '';
    $jwtSecretAvailable = true;

    try {
        AuthConfig::jwtSecret();
    } catch (Throwable) {
        $jwtSecretAvailable = false;
    }

    return [
        'healthy' => $runtime['running'] && $database['connected'] && $appKeyConfigured && $jwtSecretAvailable,
        'runtime' => $runtime,
        'database' => $database,
        'auth' => [
            'ready' => $appKeyConfigured && $jwtSecretAvailable,
            'app_key_configured' => $appKeyConfigured,
            'jwt_secret_available' => $jwtSecretAvailable,
            'providers' => ['jwt', 'api_token'],
        ],
        'docs' => [
            'openapi' => is_file($basePath . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'openapi.json'),
            'ai' => is_file($basePath . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'ai-schema.json'),
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function statusInspectDockerRuntime(string $basePath, string $url, bool $insideDocker = false): array
{
    if ($insideDocker) {
        return [
            'mode' => 'docker',
            'running' => true,
            'url' => $url,
            'details' => [
                'compose' => $basePath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'docker-compose.yml',
                'services' => ['php'],
            ],
        ];
    }

    $composePath = $basePath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'docker-compose.yml';

    if (!is_file($composePath)) {
        return [
            'mode' => 'docker',
            'running' => false,
            'url' => $url,
            'details' => ['compose' => 'missing'],
        ];
    }

    $command = sprintf(
        'docker compose -f %s ps --status running --services',
        escapeshellarg($composePath)
    );

    exec($command, $output, $exitCode);
    $services = array_values(array_filter(array_map('trim', $output)));

    return [
        'mode' => 'docker',
        'running' => $exitCode === 0 && $services !== [],
        'url' => $url,
        'details' => [
            'compose' => $composePath,
            'services' => $services,
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function statusInspectLocalRuntime(string $basePath, EnvironmentFileManager $env): array
{
    $host = trim((string) $env->getValue('APP_HOST', '127.0.0.1'));
    $port = max(1, (int) $env->getValue('APP_PORT', '8080'));

    return (new LocalRuntimeManager($basePath))->status($host, $port);
}

/**
 * @return array{connected: bool}
 */
function statusProbeDatabaseThroughDocker(string $basePath): array
{
    $composePath = $basePath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'docker-compose.yml';

    if (!is_file($composePath)) {
        return ['connected' => false];
    }

    $inlineScript = <<<'PHP'
require 'vendor/autoload.php';
PachyBase\Config::load(getcwd());
PachyBase\Database\Connection::reset();
PachyBase\Database\Connection::getInstance()->getPDO()->query('SELECT 1');
echo 'ok';
PHP;

    $command = sprintf(
        'docker compose -f %s run --rm php php -r %s',
        escapeshellarg($composePath),
        escapeshellarg($inlineScript)
    );

    exec($command, $output, $exitCode);

    return [
        'connected' => $exitCode === 0 && trim(implode('', $output)) === 'ok',
    ];
}
