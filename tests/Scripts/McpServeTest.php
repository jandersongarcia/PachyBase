<?php

declare(strict_types=1);

namespace Tests\Scripts;

use PachyBase\Config;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../scripts/mcp-serve.php';

class McpServeTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::reset();
        putenv('PACHYBASE_MCP_BASE_URL');
        putenv('PACHYBASE_MCP_TOKEN');
    }

    public function testResolveOptionsUsesEnvAndArguments(): void
    {
        Config::override(['APP_URL' => 'http://localhost:8080']);
        putenv('PACHYBASE_MCP_BASE_URL=http://env-host:8081');
        putenv('PACHYBASE_MCP_TOKEN=env-token');

        $options = mcpServeResolveOptions([
            '--base-url=http://cli-host:9000',
            '--token=cli-token',
        ]);

        $this->assertSame('http://cli-host:9000', $options['base_url']);
        $this->assertSame('cli-token', $options['token']);
    }

    public function testResolveOptionsFallsBackToAppUrl(): void
    {
        Config::override(['APP_URL' => 'http://localhost:8080/']);

        $options = mcpServeResolveOptions([]);

        $this->assertSame('http://localhost:8080', $options['base_url']);
        $this->assertSame('', $options['token']);
    }
}
