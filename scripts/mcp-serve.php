<?php

declare(strict_types=1);

use PachyBase\Config;
use PachyBase\Services\Mcp\HttpMcpBackendClient;
use PachyBase\Services\Mcp\McpServer;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(mcpServeMain($argv, $basePath));
}

function mcpServeMain(array $argv, string $basePath): int
{
    Config::load($basePath);

    $options = mcpServeResolveOptions(array_slice($argv, 1));
    $server = new McpServer(
        new HttpMcpBackendClient($options['base_url'], $options['token'])
    );

    return $server->run(STDIN, STDOUT, STDERR);
}

/**
 * @param array<int, string> $arguments
 * @return array{base_url: string, token: string}
 */
function mcpServeResolveOptions(array $arguments): array
{
    $baseUrl = trim((string) getenv('PACHYBASE_MCP_BASE_URL'));
    $token = (string) getenv('PACHYBASE_MCP_TOKEN');

    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--base-url=')) {
            $baseUrl = trim(substr($argument, 11));
            continue;
        }

        if (str_starts_with($argument, '--token=')) {
            $token = (string) substr($argument, 8);
        }
    }

    if ($baseUrl === '') {
        $baseUrl = trim((string) Config::get('APP_URL', 'http://localhost:8080'));
    }

    return [
        'base_url' => rtrim($baseUrl, '/'),
        'token' => $token,
    ];
}
