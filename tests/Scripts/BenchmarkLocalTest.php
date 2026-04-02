<?php

declare(strict_types=1);

namespace Tests\Scripts;

use PachyBase\Config;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../scripts/benchmark-local.php';

class BenchmarkLocalTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::reset();
        putenv('PACHYBASE_BENCHMARK_BASE_URL');
        putenv('PACHYBASE_BENCHMARK_TOKEN');
        putenv('PACHYBASE_BENCHMARK_ENTITY');
        putenv('PACHYBASE_BENCHMARK_BASELINE');
    }

    public function testParseArgumentsUsesEnvAndDefaults(): void
    {
        Config::override(['APP_URL' => 'http://localhost:8080']);
        putenv('PACHYBASE_BENCHMARK_BASE_URL=http://env-host:8081');
        putenv('PACHYBASE_BENCHMARK_TOKEN=bench-token');
        putenv('PACHYBASE_BENCHMARK_ENTITY=api-tokens');

        $options = benchmarkLocalParseArguments(['--json'], 'C:\\app\\PachyBase');

        $this->assertSame('http://env-host:8081', $options['base_url']);
        $this->assertSame('bench-token', $options['token']);
        $this->assertSame('api-tokens', $options['entity']);
        $this->assertTrue($options['json']);
        $this->assertStringContainsString('assets', $options['baseline']);
    }

    public function testBuildReportPassesWhenMeasurementsStayWithinBaseline(): void
    {
        $baseline = [
            'version' => '1.0',
            'name' => 'test-baseline',
            'scenarios' => [
                [
                    'name' => 'root_status',
                    'method' => 'GET',
                    'path' => '/',
                    'iterations' => 3,
                    'expect_status' => 200,
                    'max_average_ms' => 50,
                    'max_p95_ms' => 60,
                ],
                [
                    'name' => 'crud_collection',
                    'method' => 'GET',
                    'path' => '/api/{entity}?per_page=1',
                    'iterations' => 2,
                    'expect_status' => 200,
                    'max_average_ms' => 40,
                    'max_p95_ms' => 50,
                    'requires_token' => true,
                ],
            ],
        ];

        $calls = 0;
        $report = benchmarkLocalBuildReport(
            [
                'base_url' => 'http://localhost:8080',
                'token' => 'bench-token',
                'entity' => 'system-settings',
                'baseline' => 'unused.json',
            ],
            $baseline,
            function (string $method, string $url, ?string $token = null) use (&$calls): array {
                $calls++;

                return [
                    'status_code' => 200,
                    'duration_ms' => $calls % 2 === 0 ? 20.0 : 30.0,
                    'headers' => [],
                    'payload' => ['success' => true],
                ];
            }
        );

        $statuses = array_column($report['scenarios'], 'status', 'name');

        $this->assertSame('pass', $report['status']);
        $this->assertSame('pass', $statuses['root_status'] ?? null);
        $this->assertSame('pass', $statuses['crud_collection'] ?? null);
    }

    public function testBuildReportWarnsWhenProtectedScenarioHasNoToken(): void
    {
        $baseline = [
            'version' => '1.0',
            'name' => 'test-baseline',
            'scenarios' => [
                [
                    'name' => 'crud_collection',
                    'method' => 'GET',
                    'path' => '/api/{entity}?per_page=1',
                    'iterations' => 2,
                    'expect_status' => 200,
                    'max_average_ms' => 40,
                    'max_p95_ms' => 50,
                    'requires_token' => true,
                ],
            ],
        ];

        $report = benchmarkLocalBuildReport(
            [
                'base_url' => 'http://localhost:8080',
                'token' => '',
                'entity' => 'system-settings',
                'baseline' => 'unused.json',
            ],
            $baseline,
            static fn(string $method, string $url, ?string $token = null): array => []
        );

        $this->assertSame('pass', $report['status']);
        $this->assertSame('warning', $report['scenarios'][0]['status']);
    }

    public function testBuildReportIgnoresWarmupRequestFromMeasuredLatency(): void
    {
        $baseline = [
            'version' => '1.0',
            'name' => 'test-baseline',
            'scenarios' => [
                [
                    'name' => 'root_status',
                    'method' => 'GET',
                    'path' => '/',
                    'iterations' => 2,
                    'warmup_requests' => 1,
                    'expect_status' => 200,
                    'max_average_ms' => 50,
                    'max_p95_ms' => 50,
                ],
            ],
        ];

        $durations = [500.0, 10.0, 20.0];
        $report = benchmarkLocalBuildReport(
            [
                'base_url' => 'http://localhost:8080',
                'token' => '',
                'entity' => 'system-settings',
                'baseline' => 'unused.json',
            ],
            $baseline,
            function (string $method, string $url, ?string $token = null) use (&$durations): array {
                return [
                    'status_code' => 200,
                    'duration_ms' => array_shift($durations),
                    'headers' => [],
                    'payload' => ['success' => true],
                ];
            }
        );

        $scenario = $report['scenarios'][0];

        $this->assertSame('pass', $scenario['status']);
        $this->assertSame(15.0, $scenario['average_ms']);
        $this->assertSame(20.0, $scenario['p95_ms']);
    }

    public function testMeasuredDurationPrefersServerReportedResponseTimeHeader(): void
    {
        $duration = benchmarkLocalMeasuredDuration(
            [
                'X-Response-Time-Ms' => '187.42',
                'Server-Timing' => 'app;dur=187.42',
            ],
            1250.0
        );

        $this->assertSame(187.42, $duration);
    }
}
