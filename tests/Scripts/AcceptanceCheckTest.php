<?php

declare(strict_types=1);

namespace Tests\Scripts;

use PachyBase\Config;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../scripts/acceptance-check.php';

class AcceptanceCheckTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::reset();
        putenv('PACHYBASE_ACCEPTANCE_BASE_URL');
        putenv('PACHYBASE_ACCEPTANCE_TOKEN');
        putenv('PACHYBASE_ACCEPTANCE_ENTITY');
        putenv('PACHYBASE_ACCEPTANCE_MCP_COMMAND');
    }

    public function testParseArgumentsUsesEnvAndDefaults(): void
    {
        Config::override(['APP_URL' => 'http://localhost:8080']);
        putenv('PACHYBASE_ACCEPTANCE_BASE_URL=http://env-host:9000');
        putenv('PACHYBASE_ACCEPTANCE_TOKEN=env-token');
        putenv('PACHYBASE_ACCEPTANCE_ENTITY=api-tokens');

        $options = acceptanceCheckParseArguments(['--json'], 'C:\\app\\PachyBase');

        $this->assertSame('http://env-host:9000', $options['base_url']);
        $this->assertSame('env-token', $options['token']);
        $this->assertSame('api-tokens', $options['entity']);
        $this->assertTrue($options['json']);
        $this->assertStringContainsString('mcp-serve.php', $options['mcp_command']);
    }

    public function testBuildReportPassesWithHealthyFakes(): void
    {
        $report = acceptanceCheckBuildReport(
            [
                'base_url' => 'http://localhost:8080',
                'token' => 'acceptance-token',
                'entity' => 'system-settings',
                'mcp_command' => 'php scripts/mcp-serve.php',
            ],
            function (string $method, string $url, ?string $token = null): array {
                if (str_ends_with($url, '/openapi.json')) {
                    return ['paths' => ['/api/system-settings' => ['get' => true]]];
                }

                if (str_ends_with($url, '/ai/schema')) {
                    return ['schema_version' => '1.0'];
                }

                if (str_ends_with($url, '/ai/entities')) {
                    return ['items' => [['name' => 'system-settings']]];
                }

                if (str_contains($url, '/api/system-settings')) {
                    return ['success' => true, 'meta' => ['pagination' => ['total' => 1]]];
                }

                return [];
            },
            function (string $command, string $entity): array {
                return [
                    'initialize' => ['capabilities' => ['tools' => ['listChanged' => false]]],
                    'tools' => ['pachybase_describe_entity'],
                    'describe_entity' => ['name' => $entity],
                ];
            }
        );

        $checks = array_column($report['checks'], 'status', 'code');

        $this->assertSame('pass', $report['status']);
        $this->assertSame('pass', $checks['OPENAPI_REACHABLE'] ?? null);
        $this->assertSame('pass', $checks['CRUD_COLLECTION_REACHABLE'] ?? null);
        $this->assertSame('pass', $checks['MCP_SMOKE_PASSED'] ?? null);
    }

    public function testBuildReportWarnsWhenCrudTokenIsMissing(): void
    {
        $report = acceptanceCheckBuildReport(
            [
                'base_url' => 'http://localhost:8080',
                'token' => '',
                'entity' => 'system-settings',
                'mcp_command' => 'php scripts/mcp-serve.php',
            ],
            function (string $method, string $url, ?string $token = null): array {
                if (str_ends_with($url, '/openapi.json')) {
                    return ['paths' => ['/api/system-settings' => ['get' => true]]];
                }

                if (str_ends_with($url, '/ai/schema')) {
                    return ['schema_version' => '1.0'];
                }

                if (str_ends_with($url, '/ai/entities')) {
                    return ['items' => [['name' => 'system-settings']]];
                }

                return [];
            },
            function (string $command, string $entity): array {
                return [
                    'initialize' => ['capabilities' => ['tools' => ['listChanged' => false]]],
                    'tools' => ['pachybase_describe_entity'],
                    'describe_entity' => ['name' => $entity],
                ];
            }
        );

        $checks = array_column($report['checks'], 'status', 'code');

        $this->assertSame('pass', $report['status']);
        $this->assertSame('warning', $checks['CRUD_SMOKE_SKIPPED'] ?? null);
    }

    public function testBuildReportFailsWhenMcpSmokeFails(): void
    {
        $report = acceptanceCheckBuildReport(
            [
                'base_url' => 'http://localhost:8080',
                'token' => 'acceptance-token',
                'entity' => 'system-settings',
                'mcp_command' => 'php scripts/mcp-serve.php',
            ],
            function (string $method, string $url, ?string $token = null): array {
                if (str_ends_with($url, '/openapi.json')) {
                    return ['paths' => ['/api/system-settings' => ['get' => true]]];
                }

                if (str_ends_with($url, '/ai/schema')) {
                    return ['schema_version' => '1.0'];
                }

                if (str_ends_with($url, '/ai/entities')) {
                    return ['items' => [['name' => 'system-settings']]];
                }

                if (str_contains($url, '/api/system-settings')) {
                    return ['success' => true];
                }

                return [];
            },
            function (string $command, string $entity): array {
                throw new \RuntimeException('MCP process failed to initialize.');
            }
        );

        $checks = array_column($report['checks'], 'status', 'code');

        $this->assertSame('fail', $report['status']);
        $this->assertSame('error', $checks['MCP_SMOKE_FAILED'] ?? null);
    }
}
