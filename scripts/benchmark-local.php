<?php

declare(strict_types=1);

use PachyBase\Config;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(benchmarkLocalMain($argv, $basePath));
}

function benchmarkLocalMain(array $argv, string $basePath): int
{
    Config::load($basePath);

    $options = benchmarkLocalParseArguments(array_slice($argv, 1), $basePath);
    $baseline = benchmarkLocalLoadBaseline($options['baseline']);
    $report = benchmarkLocalBuildReport($options, $baseline);

    if ($options['json']) {
        fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        return $report['status'] === 'fail' ? 1 : 0;
    }

    benchmarkLocalWriteHumanReport($report);

    return $report['status'] === 'fail' ? 1 : 0;
}

/**
 * @param array<int, string> $arguments
 * @return array{
 *   base_url: string,
 *   token: string,
 *   entity: string,
 *   baseline: string,
 *   json: bool
 * }
 */
function benchmarkLocalParseArguments(array $arguments, string $basePath): array
{
    $baseUrl = trim((string) getenv('PACHYBASE_BENCHMARK_BASE_URL'));
    $token = (string) getenv('PACHYBASE_BENCHMARK_TOKEN');
    $entity = trim((string) getenv('PACHYBASE_BENCHMARK_ENTITY'));
    $baseline = trim((string) getenv('PACHYBASE_BENCHMARK_BASELINE'));
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
            continue;
        }

        if (str_starts_with($argument, '--baseline=')) {
            $baseline = trim(substr($argument, 11));
        }
    }

    if ($baseUrl === '') {
        $baseUrl = trim((string) Config::get('APP_URL', 'http://localhost:8080'));
    }

    if ($entity === '') {
        $entity = 'system-settings';
    }

    if ($baseline === '') {
        $baseline = $basePath . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'benchmarks' . DIRECTORY_SEPARATOR . 'local-baseline.json';
    } elseif (!preg_match('/^[A-Za-z]:[\\\\\\/]/', $baseline) && !str_starts_with($baseline, '/') && !str_starts_with($baseline, '\\')) {
        $baseline = $basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $baseline);
    }

    return [
        'base_url' => rtrim($baseUrl, '/'),
        'token' => $token,
        'entity' => $entity,
        'baseline' => $baseline,
        'json' => $json,
    ];
}

/**
 * @return array{
 *   version: string,
 *   name: string,
 *   scenarios: array<int, array<string, mixed>>
 * }
 */
function benchmarkLocalLoadBaseline(string $baselinePath): array
{
    if (!is_file($baselinePath)) {
        throw new RuntimeException(sprintf('Benchmark baseline file was not found at "%s".', $baselinePath));
    }

    $decoded = json_decode((string) file_get_contents($baselinePath), true);

    if (!is_array($decoded) || !isset($decoded['scenarios']) || !is_array($decoded['scenarios'])) {
        throw new RuntimeException('Benchmark baseline file is invalid.');
    }

    return [
        'version' => (string) ($decoded['version'] ?? '1.0'),
        'name' => (string) ($decoded['name'] ?? 'local-default'),
        'scenarios' => array_values($decoded['scenarios']),
    ];
}

/**
 * @param array{
 *   base_url: string,
 *   token: string,
 *   entity: string,
 *   baseline: string,
 *   json?: bool
 * } $options
 * @param array{
 *   version: string,
 *   name: string,
 *   scenarios: array<int, array<string, mixed>>
 * } $baseline
 * @return array{
 *   status: string,
 *   baseline: string,
 *   baseline_version: string,
 *   target: string,
 *   entity: string,
 *   scenarios: array<int, array<string, mixed>>,
 *   summary: array{passed: int, warnings: int, errors: int}
 * }
 */
function benchmarkLocalBuildReport(array $options, array $baseline, ?callable $requester = null): array
{
    $request = $requester ?? static fn(string $method, string $url, ?string $token = null): array => benchmarkLocalRequest($method, $url, $token);
    $results = [];

    foreach ($baseline['scenarios'] as $scenario) {
        $results[] = benchmarkLocalRunScenario($scenario, $options, $request);
    }

    $summary = [
        'passed' => count(array_filter($results, static fn(array $result): bool => $result['status'] === 'pass')),
        'warnings' => count(array_filter($results, static fn(array $result): bool => $result['status'] === 'warning')),
        'errors' => count(array_filter($results, static fn(array $result): bool => $result['status'] === 'error')),
    ];

    return [
        'status' => $summary['errors'] > 0 ? 'fail' : 'pass',
        'baseline' => $baseline['name'],
        'baseline_version' => $baseline['version'],
        'target' => $options['base_url'],
        'entity' => $options['entity'],
        'scenarios' => $results,
        'summary' => $summary,
    ];
}

/**
 * @param array<string, mixed> $scenario
 * @param array{
 *   base_url: string,
 *   token: string,
 *   entity: string,
 *   baseline: string,
 *   json?: bool
 * } $options
 * @return array<string, mixed>
 */
function benchmarkLocalRunScenario(array $scenario, array $options, callable $request): array
{
    $requiresToken = (bool) ($scenario['requires_token'] ?? false);
    $token = trim((string) $options['token']);

    if ($requiresToken && $token === '') {
        return [
            'name' => (string) ($scenario['name'] ?? 'scenario'),
            'status' => 'warning',
            'message' => 'Skipped because the scenario requires a bearer token.',
            'path' => benchmarkLocalScenarioPath($scenario, $options['entity']),
        ];
    }

    $iterations = max(1, (int) ($scenario['iterations'] ?? 5));
    $durations = [];
    $errors = [];
    $method = strtoupper((string) ($scenario['method'] ?? 'GET'));
    $path = benchmarkLocalScenarioPath($scenario, $options['entity']);
    $url = $options['base_url'] . $path;
    $expectedStatus = max(100, (int) ($scenario['expect_status'] ?? 200));

    for ($index = 0; $index < $iterations; $index++) {
        try {
            $result = $request($method, $url, $requiresToken ? $token : null);
            $statusCode = (int) ($result['status_code'] ?? 0);

            if ($statusCode !== $expectedStatus) {
                $errors[] = sprintf('Unexpected HTTP %d on iteration %d.', $statusCode, $index + 1);
                continue;
            }

            $durations[] = (float) ($result['duration_ms'] ?? 0.0);
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    $average = benchmarkLocalAverage($durations);
    $p95 = benchmarkLocalPercentile($durations, 95);
    $maxAverage = (float) ($scenario['max_average_ms'] ?? 0.0);
    $maxP95 = (float) ($scenario['max_p95_ms'] ?? 0.0);
    $status = $errors === [] && $average <= $maxAverage && $p95 <= $maxP95 ? 'pass' : 'error';

    return [
        'name' => (string) ($scenario['name'] ?? 'scenario'),
        'status' => $status,
        'method' => $method,
        'path' => $path,
        'iterations' => $iterations,
        'average_ms' => round($average, 2),
        'p95_ms' => round($p95, 2),
        'max_average_ms' => $maxAverage,
        'max_p95_ms' => $maxP95,
        'errors' => $errors,
        'message' => $status === 'pass'
            ? 'Measured latency is within the local baseline.'
            : 'Measured latency exceeded the local baseline or the endpoint returned failures.',
    ];
}

/**
 * @return array{status_code: int, duration_ms: float, headers: array<string, string>, payload: array<string, mixed>}
 */
function benchmarkLocalRequest(string $method, string $url, ?string $token = null): array
{
    $headers = [
        'Accept: application/json',
        'User-Agent: PachyBase-Benchmark/1.0',
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

    $startedAt = hrtime(true);
    $body = @file_get_contents($url, false, $context);
    $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
    $responseHeaders = isset($http_response_header) && is_array($http_response_header)
        ? $http_response_header
        : [];
    $statusCode = benchmarkLocalStatusCode($responseHeaders);

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
        'duration_ms' => $durationMs,
        'headers' => benchmarkLocalNormalizeHeaders($responseHeaders),
        'payload' => $decoded,
    ];
}

/**
 * @param array<int, float> $durations
 */
function benchmarkLocalAverage(array $durations): float
{
    if ($durations === []) {
        return 0.0;
    }

    return array_sum($durations) / count($durations);
}

/**
 * @param array<int, float> $durations
 */
function benchmarkLocalPercentile(array $durations, float $percentile): float
{
    if ($durations === []) {
        return 0.0;
    }

    sort($durations);
    $index = (int) ceil(($percentile / 100) * count($durations)) - 1;
    $index = max(0, min($index, count($durations) - 1));

    return $durations[$index];
}

/**
 * @param array<string, mixed> $scenario
 */
function benchmarkLocalScenarioPath(array $scenario, string $entity): string
{
    $path = (string) ($scenario['path'] ?? '/');

    return str_replace('{entity}', $entity, $path);
}

/**
 * @param array<int, string> $headers
 */
function benchmarkLocalStatusCode(array $headers): int
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
function benchmarkLocalNormalizeHeaders(array $headers): array
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
 * @param array{
 *   status: string,
 *   baseline: string,
 *   baseline_version: string,
 *   target: string,
 *   entity: string,
 *   scenarios: array<int, array<string, mixed>>,
 *   summary: array{passed: int, warnings: int, errors: int}
 * } $report
 */
function benchmarkLocalWriteHumanReport(array $report): void
{
    fwrite(STDOUT, sprintf("PachyBase local benchmark for %s\n", $report['target']));
    fwrite(STDOUT, sprintf("Baseline: %s (%s)\n", $report['baseline'], $report['baseline_version']));
    fwrite(STDOUT, sprintf("Protected entity target: %s\n\n", $report['entity']));

    foreach ($report['scenarios'] as $scenario) {
        $label = match ($scenario['status']) {
            'pass' => 'PASS',
            'warning' => 'WARN',
            default => 'FAIL',
        };

        fwrite(STDOUT, sprintf("[%s] %s\n", $label, (string) $scenario['name']));
        fwrite(STDOUT, sprintf("       %s\n", (string) $scenario['message']));

        if (($scenario['status'] ?? '') !== 'warning') {
            fwrite(
                STDOUT,
                sprintf(
                    "       avg=%.2fms (limit %.2fms) | p95=%.2fms (limit %.2fms)\n",
                    (float) ($scenario['average_ms'] ?? 0.0),
                    (float) ($scenario['max_average_ms'] ?? 0.0),
                    (float) ($scenario['p95_ms'] ?? 0.0),
                    (float) ($scenario['max_p95_ms'] ?? 0.0)
                )
            );
        }

        foreach (($scenario['errors'] ?? []) as $error) {
            fwrite(STDOUT, sprintf("       %s\n", (string) $error));
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
