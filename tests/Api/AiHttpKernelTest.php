<?php

declare(strict_types=1);

namespace Tests\Api;

use PachyBase\Api\HttpKernel;
use PachyBase\Config;
use PachyBase\Database\Connection;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\ResponseCaptured;
use PHPUnit\Framework\TestCase;

class AiHttpKernelTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(dirname(__DIR__, 2));
    }

    protected function tearDown(): void
    {
        ApiResponse::disableCapture();
        Connection::reset();
        Config::reset();
        $_SERVER = [];
        $_GET = [];
        $_POST = [];
    }

    public function testKernelPublishesAiSchemaEndpoints(): void
    {
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/ai/schema';

        try {
            (new HttpKernel(dirname(__DIR__, 2)))->handle();
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();

            $this->assertSame(200, $captured->getStatusCode());
            $this->assertSame('1.0', $payload['schema_version']);
            $this->assertSame('/ai/entities', $payload['navigation']['entities_url']);
            $this->assertSame('/openapi.json', $payload['openapi_compatibility']['document_url']);
            $this->assertNotEmpty($payload['entities']);
        }
    }

    public function testKernelPublishesAiSchemaJsonAlias(): void
    {
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/ai-schema.json';

        try {
            (new HttpKernel(dirname(__DIR__, 2)))->handle();
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();

            $this->assertSame(200, $captured->getStatusCode());
            $this->assertSame('1.0', $payload['schema_version']);
            $this->assertSame('/ai/entities', $payload['navigation']['entities_url']);
        }
    }

    public function testKernelPublishesAiEntityDocument(): void
    {
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/ai/entity/system-settings';

        try {
            (new HttpKernel(dirname(__DIR__, 2)))->handle();
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();
            $fieldNames = array_column($payload['fields'], 'name');
            $operationNames = array_column($payload['operations'], 'name');

            $this->assertSame(200, $captured->getStatusCode());
            $this->assertSame('system-settings', $payload['name']);
            $this->assertSame('/api/system-settings', $payload['paths']['collection']);
            $this->assertContains('setting_key', $fieldNames);
            $this->assertContains('list', $operationNames);
            $this->assertContains('delete', $operationNames);
        }
    }
}
