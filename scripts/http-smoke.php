<?php

declare(strict_types=1);

use PachyBase\Config;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(httpSmokeMain($argv, $basePath));
}

function httpSmokeMain(array $argv, string $basePath): int
{
    Config::load($basePath);

    $options = httpSmokeParseArguments(array_slice($argv, 1));
    $report = httpSmokeBuildReport($options);

    if ($options['json']) {
        fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        return $report['status'] === 'fail' ? 1 : 0;
    }

    httpSmokeWriteHumanReport($report);

    return $report['status'] === 'fail' ? 1 : 0;
}

/**
 * @param array<int, string> $arguments
 * @return array{
 *   base_url: string,
 *   token: string,
 *   entity: string,
 *   json: bool
 * }
 */
function httpSmokeParseArguments(array $arguments): array
{
    $baseUrl = trim((string) getenv('PACHYBASE_SMOKE_BASE_URL'));
    $token = (string) getenv('PACHYBASE_SMOKE_TOKEN');
    $entity = trim((string) getenv('PACHYBASE_SMOKE_ENTITY'));
    $json = false;

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
        }
    }

    if ($baseUrl === '') {
        $baseUrl = trim((string) Config::get('APP_URL', 'http://localhost:8080'));
    }

    if ($entity === '') {
        $entity = 'system-settings';
    }

    return [
        'base_url' => rtrim($baseUrl, '/'),
        'token' => $token,
        'entity' => $entity,
        'json' => $json,
    ];
}

/**
 * @param array{
 *   base_url: string,
 *   token: string,
 *   entity: string,
 *   json?: bool
 * } $options
 * @return array{
 *   status: string,
 *   target: string,
 *   entity: string,
 *   checks: array<int, array{status: string, code: string, message: string, hint: string|null}>,
 *   summary: array{passed: int, warnings: int, errors: int}
 * }
 */
function httpSmokeBuildReport(array $options, ?callable $requester = null): array
{
    $request = $requester ?? static fn(string $method, string $url, ?string $token = null): array => httpSmokeRequest($method, $url, $token);
    $checks = [];

    $root = httpSmokeTry(static fn(): array => $request('GET', $options['base_url'] . '/', null));
    $checks[] = $root['ok']
        ? httpSmokeResult('pass', 'ROOT_REACHABLE', 'Root status endpoint is reachable.', null)
        : httpSmokeResult('error', 'ROOT_UNAVAILABLE', 'Root status endpoint could not be reached.', $root['message']);

    if ($root['ok']) {
        $headers = $root['payload']['headers'] ?? [];
        $checks[] = httpSmokeHasTimingHeaders($headers, ['Server-Timing', 'X-Response-Time-Ms'])
            ? httpSmokeResult('pass', 'REQUEST_TIMING_EXPOSED', 'Request timing headers are exposed on the root endpoint.', null)
            : httpSmokeResult('error', 'REQUEST_TIMING_MISSING', 'Request timing headers are missing on the root endpoint.', 'Expose Server-Timing and X-Response-Time-Ms in API responses.');
    }

    $openApi = httpSmokeTry(static fn(): array => $request('GET', $options['base_url'] . '/openapi.json', null));
    $checks[] = $openApi['ok']
        ? httpSmokeResult('pass', 'OPENAPI_REACHABLE', 'OpenAPI document is reachable.', null)
        : httpSmokeResult('error', 'OPENAPI_UNAVAILABLE', 'OpenAPI document could not be reached.', $openApi['message']);

    $aiSchema = httpSmokeTry(static fn(): array => $request('GET', $options['base_url'] . '/ai/schema', null));
    $checks[] = $aiSchema['ok']
        ? httpSmokeResult('pass', 'AI_SCHEMA_REACHABLE', 'AI schema endpoint is reachable.', null)
        : httpSmokeResult('error', 'AI_SCHEMA_UNAVAILABLE', 'AI schema endpoint could not be reached.', $aiSchema['message']);

    if ($aiSchema['ok']) {
        $headers = $aiSchema['payload']['headers'] ?? [];
        $checks[] = httpSmokeHasTimingHeaders($headers, ['Server-Timing', 'X-Query-Time-Ms', 'X-Introspection-Time-Ms'])
            ? httpSmokeResult('pass', 'AI_TIMING_EXPOSED', 'AI schema endpoint exposes query and introspection timing headers.', null)
            : httpSmokeResult('error', 'AI_TIMING_MISSING', 'AI schema endpoint is missing query or introspection timing headers.', 'Expose X-Query-Time-Ms and X-Introspection-Time-Ms on schema responses.');
    }

    $token = trim((string) $options['token']);
    if ($token === '') {
        $checks[] = httpSmokeResult(
            'warning',
            'CRUD_SMOKE_SKIPPED',
            'Protected CRUD smoke check was skipped because no token was provided.',
            'Export PACHYBASE_SMOKE_TOKEN or pass --token=... to validate the protected entity path.'
        );
    } else {
        $crud = httpSmokeTry(
            static fn(): array => $request('GET', $options['base_url'] . '/api/' . $options['entity'] . '?per_page=1', $token)
        );
        $checks[] = $crud['ok']
            ? httpSmokeResult('pass', 'CRUD_COLLECTION_REACHABLE', sprintf('Protected CRUD list for "%s" is reachable.', $options['entity']), null)
            : httpSmokeResult('error', 'CRUD_COLLECTION_FAILED', sprintf('Protected CRUD list for "%s" failed.', $options['entity']), $crud['message']);

        if ($crud['ok']) {
            $headers = $crud['payload']['headers'] ?? [];
            $checks[] = httpSmokeHasTimingHeaders($headers, ['Server-Timing', 'X-Query-Time-Ms'])
                ? httpSmokeResult('pass', 'CRUD_QUERY_TIMING_EXPOSED', 'Protected CRUD endpoint exposes query timing headers.', null)
                : httpSmokeResult('error', 'CRUD_QUERY_TIMING_MISSING', 'Protected CRUD endpoint is missing query timing headers.', 'Expose X-Query-Time-Ms on CRUD responses.');
        }
    }

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
function httpSmokeTry(callable $operation): array
{
    try {
        $payload = $operation();

        if (!is_array($payload)) {
            return [
                'ok' => false,
                'message' => 'The target operation did not return a structured payload.',
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
function httpSmokeResult(string $status, string $code, string $message, ?string $hint): array
{
    return [
        'status' => $status,
        'code' => $code,
        'message' => $message,
        'hint' => $hint,
    ];
}

/**
 * @return array{status_code: int, headers: array<string, string>, payload: array<string, mixed>}
 */
function httpSmokeRequest(string $method, string $url, ?string $token = null): array
{
    $headers = [
        'Accept: application/json',
        'User-Agent: PachyBase-Http-Smoke/1.0',
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
    $statusCode = httpSmokeStatusCode($responseHeaders);

    if ($body === false) {
        throw new RuntimeException(sprintf('Request failed for "%s".', $url));
    }

    $decoded = json_decode($body, true);

    if (!is_array($decoded)) {
        throw new RuntimeException(sprintf('The endpoint "%s" returned a non-JSON response.', $url));
    }

    if ($statusCode >= 400) {
        throw new RuntimeException((string) ($decoded['error']['message'] ?? ('HTTP ' . $statusCode)));
    }

    return [
        'status_code' => $statusCode,
        'headers' => httpSmokeNormalizeHeaders($responseHeaders),
        'payload' => $decoded,
    ];
}

/**
 * @param array<int, string> $headers
 */
function httpSmokeStatusCode(array $headers): int
{
    foreach ($headers as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
            return (int) $matches[1];
        }
    }

    return 200;
}

/**
 * @param array<int, string> $headers
 * @return array<string, string>
 */
function httpSmokeNormalizeHeaders(array $headers): array
{
    $normalized = [];

    foreach ($headers as $header) {
        if (!str_contains($header, ':')) {
            continue;
        }

        [$name, $value] = explode(':', $header, 2);
        $normalized[trim($name)] = trim($value);
    }

    return $normalized;
}

/**
 * @param array<string, string> $headers
 * @param array<int, string> $requiredHeaders
 */
function httpSmokeHasTimingHeaders(array $headers, array $requiredHeaders): bool
{
    foreach ($requiredHeaders as $header) {
        if (!array_key_exists($header, $headers) || trim($headers[$header]) === '') {
            return false;
        }
    }

    return true;
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
function httpSmokeWriteHumanReport(array $report): void
{
    fwrite(STDOUT, sprintf("PachyBase HTTP smoke check for %s\n", $report['target']));
    fwrite(STDOUT, sprintf("Protected entity target: %s\n\n", $report['entity']));

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
