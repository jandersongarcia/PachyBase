<?php

declare(strict_types=1);

namespace Tests\Scripts;

use PachyBase\Config;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../scripts/http-smoke.php';

class HttpSmokeTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::reset();
        putenv('PACHYBASE_SMOKE_BASE_URL');
        putenv('PACHYBASE_SMOKE_TOKEN');
        putenv('PACHYBASE_SMOKE_ENTITY');
    }

    public function testParseArgumentsUsesEnvAndDefaults(): void
    {
        Config::override(['APP_URL' => 'http://localhost:8080']);
        putenv('PACHYBASE_SMOKE_BASE_URL=http://env-host:9090');
        putenv('PACHYBASE_SMOKE_TOKEN=env-token');
        putenv('PACHYBASE_SMOKE_ENTITY=api-tokens');

        $options = httpSmokeParseArguments(['--json']);

        $this->assertSame('http://env-host:9090', $options['base_url']);
        $this->assertSame('env-token', $options['token']);
        $this->assertSame('api-tokens', $options['entity']);
        $this->assertTrue($options['json']);
    }

    public function testBuildReportPassesWithHealthyFakes(): void
    {
        $report = httpSmokeBuildReport(
            [
                'base_url' => 'http://localhost:8080',
                'token' => 'smoke-token',
                'entity' => 'system-settings',
            ],
            function (string $method, string $url, ?string $token = null): array {
                return [
                    'status_code' => 200,
                    'headers' => [
                        'Server-Timing' => 'app;dur=12.30, db;dur=4.10, introspection;dur=2.40',
                        'X-Response-Time-Ms' => '12.30',
                        'X-Query-Time-Ms' => '4.10',
                        'X-Introspection-Time-Ms' => '2.40',
                    ],
                    'payload' => ['success' => true],
                ];
            }
        );

        $checks = array_column($report['checks'], 'status', 'code');

        $this->assertSame('pass', $report['status']);
        $this->assertSame('pass', $checks['ROOT_REACHABLE'] ?? null);
        $this->assertSame('pass', $checks['REQUEST_TIMING_EXPOSED'] ?? null);
        $this->assertSame('pass', $checks['AI_TIMING_EXPOSED'] ?? null);
        $this->assertSame('pass', $checks['CRUD_QUERY_TIMING_EXPOSED'] ?? null);
    }

    public function testBuildReportWarnsWhenCrudTokenIsMissing(): void
    {
        $report = httpSmokeBuildReport(
            [
                'base_url' => 'http://localhost:8080',
                'token' => '',
                'entity' => 'system-settings',
            ],
            function (string $method, string $url, ?string $token = null): array {
                return [
                    'status_code' => 200,
                    'headers' => [
                        'Server-Timing' => 'app;dur=10.00',
                        'X-Response-Time-Ms' => '10.00',
                        'X-Query-Time-Ms' => '2.10',
                        'X-Introspection-Time-Ms' => '1.90',
                    ],
                    'payload' => ['success' => true],
                ];
            }
        );

        $checks = array_column($report['checks'], 'status', 'code');

        $this->assertSame('pass', $report['status']);
        $this->assertSame('warning', $checks['CRUD_SMOKE_SKIPPED'] ?? null);
    }
}
