<?php

declare(strict_types=1);

use PachyBase\Config;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(acceptanceCheckMain($argv, $basePath));
}

function acceptanceCheckMain(array $argv, string $basePath): int
{
    Config::load($basePath);

    $options = acceptanceCheckParseArguments(array_slice($argv, 1), $basePath);
    $report = acceptanceCheckBuildReport($options);

    if ($options['json']) {
        fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        return $report['status'] === 'fail' ? 1 : 0;
    }

    acceptanceCheckWriteHumanReport($report);

    return $report['status'] === 'fail' ? 1 : 0;
}

/**
 * @param array<int, string> $arguments
 * @return array{
 *   base_url: string,
 *   token: string,
 *   entity: string,
 *   json: bool,
 *   mcp_command: string
 * }
 */
function acceptanceCheckParseArguments(array $arguments, string $basePath): array
{
    $baseUrl = trim((string) getenv('PACHYBASE_ACCEPTANCE_BASE_URL'));
    $token = (string) getenv('PACHYBASE_ACCEPTANCE_TOKEN');
    $entity = trim((string) getenv('PACHYBASE_ACCEPTANCE_ENTITY'));
    $json = false;
    $mcpCommand = trim((string) getenv('PACHYBASE_ACCEPTANCE_MCP_COMMAND'));

    foreach ($arguments as $argument) {
        if ($argument === '--json') {
            $json = true;
            continue;
        }

        if (str_starts_with($argument, '--base-url=')) {
            $baseUrl = trim(substr($argument, 11));
            continue;
        }

        if (str_starts_with($argument, '--token=')) {
            $token = (string) substr($argument, 8);
            continue;
        }

        if (str_starts_with($argument, '--entity=')) {
            $entity = trim(substr($argument, 9));
            continue;
        }

        if (str_starts_with($argument, '--mcp-command=')) {
            $mcpCommand = trim(substr($argument, 14));
        }
    }

    if ($baseUrl === '') {
        $baseUrl = trim((string) Config::get('APP_URL', 'http://localhost:8080'));
    }

    if ($entity === '') {
        $entity = 'system-settings';
    }

    if ($mcpCommand === '') {
        $mcpCommand = acceptanceCheckDefaultMcpCommand($basePath, $baseUrl, $token);
    }

    return [
        'base_url' => rtrim($baseUrl, '/'),
        'token' => $token,
        'entity' => $entity,
        'json' => $json,
        'mcp_command' => $mcpCommand,
    ];
}

/**
 * @param array{
 *   base_url: string,
 *   token: string,
 *   entity: string,
 *   json?: bool,
 *   mcp_command: string
 * } $options
 * @return array{
 *   status: string,
 *   target: string,
 *   entity: string,
 *   checks: array<int, array{status: string, code: string, message: string, hint: string|null}>,
 *   summary: array{passed: int, warnings: int, errors: int}
 * }
 */
function acceptanceCheckBuildReport(
    array $options,
    ?callable $httpRequester = null,
    ?callable $mcpRequester = null
): array {
    $requester = $httpRequester ?? static fn(string $method, string $url, ?string $token = null): array => acceptanceCheckHttpRequest($method, $url, $token);
    $mcp = $mcpRequester ?? static fn(string $command, string $entity): array => acceptanceCheckMcpHandshake($command, $entity);
    $checks = [];

    $openapi = acceptanceCheckTry(
        static fn(): array => $requester('GET', $options['base_url'] . '/openapi.json', null)
    );
    $checks[] = $openapi['ok']
        ? acceptanceCheckResult(
            'pass',
            'OPENAPI_REACHABLE',
            'OpenAPI document is reachable and returned JSON.',
            null
        )
        : acceptanceCheckResult(
            'error',
            'OPENAPI_UNAVAILABLE',
            'OpenAPI document could not be read from the target runtime.',
            $openapi['message']
        );

    if ($openapi['ok']) {
        $paths = $openapi['payload']['paths'] ?? [];
        $checks[] = is_array($paths) && array_key_exists('/api/' . $options['entity'], $paths)
            ? acceptanceCheckResult('pass', 'OPENAPI_ENTITY_PRESENT', sprintf('OpenAPI includes the "/api/%s" collection path.', $options['entity']), null)
            : acceptanceCheckResult('warning', 'OPENAPI_ENTITY_MISSING', sprintf('OpenAPI does not expose "/api/%s".', $options['entity']), 'Review config/CrudEntities.php or choose another entity with --entity=' . $options['entity'] . '.');
    }

    $schema = acceptanceCheckTry(
        static fn(): array => $requester('GET', $options['base_url'] . '/ai/schema', null)
    );
    $checks[] = $schema['ok']
        ? acceptanceCheckResult('pass', 'AI_SCHEMA_REACHABLE', 'AI schema endpoint is reachable.', null)
        : acceptanceCheckResult('error', 'AI_SCHEMA_UNAVAILABLE', 'AI schema endpoint could not be read from the target runtime.', $schema['message']);

    $entities = acceptanceCheckTry(
        static fn(): array => $requester('GET', $options['base_url'] . '/ai/entities', null)
    );
    $checks[] = $entities['ok']
        ? acceptanceCheckResult('pass', 'AI_ENTITIES_REACHABLE', 'AI entities endpoint is reachable.', null)
        : acceptanceCheckResult('error', 'AI_ENTITIES_UNAVAILABLE', 'AI entities endpoint could not be read from the target runtime.', $entities['message']);

    $token = trim((string) $options['token']);
    if ($token === '') {
        $checks[] = acceptanceCheckResult(
            'warning',
            'CRUD_SMOKE_SKIPPED',
            'Protected CRUD smoke checks were skipped because no acceptance token was provided.',
            'Export PACHYBASE_ACCEPTANCE_TOKEN or pass --token=... to validate protected entity access.'
        );
    } else {
        $crudList = acceptanceCheckTry(
            static fn(): array => $requester('GET', $options['base_url'] . '/api/' . $options['entity'] . '?per_page=1', $token)
        );
        $checks[] = $crudList['ok']
            ? acceptanceCheckResult('pass', 'CRUD_COLLECTION_REACHABLE', sprintf('Protected CRUD list for "%s" succeeded.', $options['entity']), null)
            : acceptanceCheckResult('error', 'CRUD_COLLECTION_FAILED', sprintf('Protected CRUD list for "%s" failed.', $options['entity']), $crudList['message']);
    }

    $mcpSmoke = acceptanceCheckTry(
        static fn(): array => $mcp($options['mcp_command'], $options['entity'])
    );
    $checks[] = $mcpSmoke['ok']
        ? acceptanceCheckResult('pass', 'MCP_SMOKE_PASSED', 'MCP initialize, tools/list, and tools/call smoke checks passed.', null)
        : acceptanceCheckResult('error', 'MCP_SMOKE_FAILED', 'MCP smoke check failed.', $mcpSmoke['message']);

    $summary = [
        'passed' => count(array_filter($checks, static fn(array $check): bool => $check['status'] === 'pass')),
        'warnings' => count(array_filter($checks, static fn(array $check): bool => $check['status'] === 'warning')),
        'errors' => count(array_filter($checks, static fn(array $check): bool => $check['status'] === 'error')),
    ];

    return [
        'status' => $summary['errors'] > 0 ? 'fail' : 'pass',
        'target' => $options['base_url'],
        'entity' => $options['entity'],
        'checks' => $checks,
        'summary' => $summary,
    ];
}

/**
 * @return array{ok: bool, payload?: array<string, mixed>, message?: string}
 */
function acceptanceCheckTry(callable $operation): array
{
    try {
        $payload = $operation();

        if (!is_array($payload)) {
            return [
                'ok' => false,
                'message' => 'The target operation did not return an object payload.',
            ];
        }

        return [
            'ok' => true,
            'payload' => $payload,
        ];
    } catch (Throwable $exception) {
        return [
            'ok' => false,
            'message' => $exception->getMessage(),
        ];
    }
}

/**
 * @return array{status: string, code: string, message: string, hint: string|null}
 */
function acceptanceCheckResult(string $status, string $code, string $message, ?string $hint): array
{
    return [
        'status' => $status,
        'code' => $code,
        'message' => $message,
        'hint' => $hint,
    ];
}

/**
 * @return array<string, mixed>
 */
function acceptanceCheckHttpRequest(string $method, string $url, ?string $token = null): array
{
    $headers = [
        'Accept: application/json',
        'User-Agent: PachyBase-Acceptance/1.0',
    ];

    $token = trim((string) $token);
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => 20,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $responseHeaders = is_array($http_response_header) ? $http_response_header : [];
    $statusCode = acceptanceCheckStatusCode($responseHeaders);

    if ($body === false) {
        throw new RuntimeException(sprintf('Request failed for "%s".', $url));
    }

    $decoded = json_decode($body, true);

    if (!is_array($decoded)) {
        throw new RuntimeException(sprintf('The target "%s" returned a non-JSON response.', $url));
    }

    if ($statusCode >= 400) {
        throw new RuntimeException((string) ($decoded['error']['message'] ?? ('HTTP ' . $statusCode)));
    }

    return $decoded;
}

/**
 * @param array<int, string> $headers
 */
function acceptanceCheckStatusCode(array $headers): int
{
    foreach ($headers as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
            return (int) $matches[1];
        }
    }

    return 200;
}

/**
 * @return array<string, mixed>
 */
function acceptanceCheckMcpHandshake(string $command, string $entity): array
{
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptor, $pipes, dirname(__DIR__));

    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start the PachyBase MCP adapter process.');
    }

    try {
        fwrite($pipes[0], json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
            ],
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
        fwrite($pipes[0], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
        fwrite($pipes[0], json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
        fwrite($pipes[0], json_encode([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'pachybase_describe_entity',
                'arguments' => [
                    'entity' => $entity,
                ],
            ],
        ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
        fflush($pipes[0]);

        $initialize = acceptanceCheckReadJsonLine($pipes[1], 'initialize');
        $toolsList = acceptanceCheckReadJsonLine($pipes[1], 'tools/list');
        $toolsCall = acceptanceCheckReadJsonLine($pipes[1], 'tools/call');

        $toolNames = array_column($toolsList['result']['tools'] ?? [], 'name');

        if (($initialize['result']['capabilities']['tools']['listChanged'] ?? null) !== false) {
            throw new RuntimeException('The MCP adapter did not expose the expected tools capability.');
        }

        if (!in_array('pachybase_describe_entity', $toolNames, true)) {
            throw new RuntimeException('The MCP adapter did not expose the expected PachyBase tools.');
        }

        if (($toolsCall['result']['isError'] ?? false) === true) {
            throw new RuntimeException((string) ($toolsCall['result']['content'][0]['text'] ?? 'The MCP tool call failed.'));
        }

        return [
            'initialize' => $initialize['result'] ?? [],
            'tools' => $toolNames,
            'describe_entity' => $toolsCall['result']['structuredContent'] ?? [],
        ];
    } finally {
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        proc_terminate($process);
        proc_close($process);
    }
}

/**
 * @param resource $stream
 * @return array<string, mixed>
 */
function acceptanceCheckReadJsonLine($stream, string $operation): array
{
    $line = fgets($stream);

    if (!is_string($line) || trim($line) === '') {
        throw new RuntimeException(sprintf('The MCP adapter did not return a response for %s.', $operation));
    }

    $decoded = json_decode(trim($line), true);

    if (!is_array($decoded)) {
        throw new RuntimeException(sprintf('The MCP adapter returned invalid JSON for %s.', $operation));
    }

    return $decoded;
}

function acceptanceCheckDefaultMcpCommand(string $basePath, string $baseUrl, string $token): string
{
    $parts = [
        escapeshellarg(PHP_BINARY),
        escapeshellarg($basePath . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'mcp-serve.php'),
        '--base-url=' . escapeshellarg($baseUrl),
    ];

    if (trim($token) !== '') {
        $parts[] = '--token=' . escapeshellarg($token);
    }

    return implode(' ', $parts);
}

/**
 * @param array{
 *   status: string,
 *   target: string,
 *   entity: string,
 *   checks: array<int, array{status: string, code: string, message: string, hint: string|null}>,
 *   summary: array{passed: int, warnings: int, errors: int}
 * } $report
 */
function acceptanceCheckWriteHumanReport(array $report): void
{
    fwrite(STDOUT, sprintf("PachyBase acceptance check for %s\n", $report['target']));
    fwrite(STDOUT, sprintf("Entity smoke target: %s\n\n", $report['entity']));

    foreach ($report['checks'] as $check) {
        $label = match ($check['status']) {
            'pass' => 'PASS',
            'warning' => 'WARN',
            default => 'FAIL',
        };

        fwrite(STDOUT, sprintf("[%s] %s\n", $label, $check['message']));

        if ($check['hint'] !== null) {
            fwrite(STDOUT, sprintf("       %s\n", $check['hint']));
        }
    }

    fwrite(
        STDOUT,
        sprintf(
            "\nSummary: %d passed, %d warning(s), %d error(s)\n",
            $report['summary']['passed'],
            $report['summary']['warnings'],
            $report['summary']['errors']
        )
    );
}
