<?php

declare(strict_types=1);

namespace Tests\Services\Mcp;

use PachyBase\Services\Mcp\McpServer;
use PachyBase\Services\Mcp\PachyBaseMcpBackendInterface;
use PHPUnit\Framework\TestCase;

class McpServerTest extends TestCase
{
    public function testInitializeReturnsToolsCapability(): void
    {
        $server = new McpServer(new FakeMcpBackend(), 'PachyBase MCP', '1.0.0');

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
            ],
        ]);

        $this->assertSame('2.0', $response['jsonrpc']);
        $this->assertSame(1, $response['id']);
        $this->assertSame('2025-06-18', $response['result']['protocolVersion']);
        $this->assertSame(false, $response['result']['capabilities']['tools']['listChanged']);
        $this->assertSame('PachyBase MCP', $response['result']['serverInfo']['name']);
    }

    public function testToolsListExposesCrudAndSchemaTools(): void
    {
        $server = new McpServer(new FakeMcpBackend());
        $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ]);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ]);

        $toolNames = array_column($response['result']['tools'], 'name');

        $this->assertContains('pachybase_get_schema', $toolNames);
        $this->assertContains('pachybase_list_records', $toolNames);
        $this->assertContains('pachybase_update_record', $toolNames);
    }

    public function testToolsCallReturnsStructuredContent(): void
    {
        $server = new McpServer(new FakeMcpBackend());
        $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ]);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'pachybase_describe_entity',
                'arguments' => [
                    'entity' => 'system-settings',
                ],
            ],
        ]);

        $this->assertFalse($response['result']['isError']);
        $this->assertSame('system-settings', $response['result']['structuredContent']['name']);
        $this->assertSame('text', $response['result']['content'][0]['type']);
        $this->assertStringContainsString('system-settings', $response['result']['content'][0]['text']);
    }

    public function testToolsCallReturnsToolExecutionErrorInResult(): void
    {
        $server = new McpServer(new FakeMcpBackend(shouldFailOnListRecords: true));
        $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ]);

        $response = $server->handleMessage([
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'pachybase_list_records',
                'arguments' => [
                    'entity' => 'system-settings',
                ],
            ],
        ]);

        $this->assertTrue($response['result']['isError']);
        $this->assertStringContainsString('backend unavailable', $response['result']['content'][0]['text']);
    }
}

final class FakeMcpBackend implements PachyBaseMcpBackendInterface
{
    public function __construct(
        private readonly bool $shouldFailOnListRecords = false
    ) {
    }

    public function getSchema(): array
    {
        return ['schema_version' => '1.0'];
    }

    public function listEntities(): array
    {
        return ['items' => [['name' => 'system-settings']]];
    }

    public function describeEntity(string $entity): array
    {
        return ['name' => $entity];
    }

    public function listRecords(string $entity, array $query): array
    {
        if ($this->shouldFailOnListRecords) {
            throw new \RuntimeException('backend unavailable');
        }

        return ['entity' => $entity, 'query' => $query];
    }

    public function getRecord(string $entity, string $id): array
    {
        return ['entity' => $entity, 'id' => $id];
    }

    public function createRecord(string $entity, array $payload): array
    {
        return ['entity' => $entity, 'payload' => $payload];
    }

    public function replaceRecord(string $entity, string $id, array $payload): array
    {
        return ['entity' => $entity, 'id' => $id, 'payload' => $payload];
    }

    public function updateRecord(string $entity, string $id, array $payload): array
    {
        return ['entity' => $entity, 'id' => $id, 'payload' => $payload];
    }

    public function deleteRecord(string $entity, string $id): array
    {
        return ['entity' => $entity, 'id' => $id, 'deleted' => true];
    }
}
