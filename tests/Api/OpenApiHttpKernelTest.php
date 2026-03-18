<?php

declare(strict_types=1);

namespace Tests\Api;

use PachyBase\Api\HttpKernel;
use PachyBase\Config;
use PachyBase\Database\Connection;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\ResponseCaptured;
use PHPUnit\Framework\TestCase;

class OpenApiHttpKernelTest extends TestCase
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

    public function testKernelPublishesOpenApiJsonDocument(): void
    {
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/openapi.json';
        $_SERVER['HTTP_HOST'] = 'localhost:8080';

        try {
            (new HttpKernel(dirname(__DIR__, 2)))->handle();
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();

            $this->assertSame(200, $captured->getStatusCode());
            $this->assertSame('3.0.3', $payload['openapi']);
            $this->assertArrayHasKey('/ai/schema', $payload['paths']);
            $this->assertArrayHasKey('/api/auth/login', $payload['paths']);
            $this->assertArrayHasKey('/api/system-settings', $payload['paths']);
            $this->assertArrayHasKey('bearerAuth', $payload['components']['securitySchemes']);
        }
    }
}
