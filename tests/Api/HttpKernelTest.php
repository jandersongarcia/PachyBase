<?php

declare(strict_types=1);

namespace Tests\Api;

use PachyBase\Api\HttpKernel;
use PachyBase\Config;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\ResponseCaptured;
use PHPUnit\Framework\TestCase;

class HttpKernelTest extends TestCase
{
    protected function tearDown(): void
    {
        ApiResponse::disableCapture();
        Config::reset();
        $_SERVER = [];
        $_GET = [];
        $_POST = [];
    }

    public function testKernelBootstrapsRoutesAndDispatchesSystemEndpoint(): void
    {
        Config::override([
            'APP_ENV' => 'development',
            'APP_DEBUG' => 'true',
            'APP_NAME' => 'PachyBase',
            'DB_DRIVER' => 'mysql',
            'DB_HOST' => 'db',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'pachybase',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => 'root',
        ]);

        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        try {
            (new HttpKernel(dirname(__DIR__, 2)))->handle();
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();

            $this->assertSame(200, $captured->getStatusCode());
            $this->assertTrue($payload['success']);
            $this->assertSame('system.status', $payload['meta']['resource']);
            $this->assertSame('/', $payload['meta']['path']);
            $this->assertSame('running', $payload['data']['status']);
            $this->assertArrayNotHasKey('database', $payload['data']);
        }
    }

    public function testKernelDispatchesLightweightHealthEndpoint(): void
    {
        Config::override([
            'APP_ENV' => 'development',
            'APP_DEBUG' => 'true',
            'APP_NAME' => 'PachyBase',
        ]);

        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/health';

        try {
            (new HttpKernel(dirname(__DIR__, 2)))->handle();
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();

            $this->assertSame(200, $captured->getStatusCode());
            $this->assertSame('system.health', $payload['meta']['resource']);
            $this->assertSame('ok', $payload['data']['status']);
            $this->assertArrayHasKey('application', $payload['data']['checks']);
            $this->assertArrayNotHasKey('database', $payload['data']['checks']);
        }
    }

    public function testKernelHandlesCorsPreflightForKnownRoute(): void
    {
        Config::override([
            'APP_ENV' => 'development',
            'APP_DEBUG' => 'true',
            'APP_NAME' => 'PachyBase',
            'APP_CORS_ALLOWED_ORIGINS' => 'https://app.example.com',
            'APP_CORS_ALLOWED_HEADERS' => 'Authorization, Content-Type',
            'APP_CORS_EXPOSED_HEADERS' => 'X-Request-Id',
            'APP_CORS_ALLOW_CREDENTIALS' => 'true',
            'APP_CORS_MAX_AGE' => '900',
        ]);

        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/api/auth/login';
        $_SERVER['HTTP_ORIGIN'] = 'https://app.example.com';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] = 'Authorization, Content-Type';

        try {
            (new HttpKernel(dirname(__DIR__, 2)))->handle();
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();
            $headers = $captured->getHeaders();

            $this->assertSame(200, $captured->getStatusCode());
            $this->assertTrue($payload['success']);
            $this->assertTrue($payload['data']['preflight']);
            $this->assertSame('cors.preflight', $payload['meta']['resource']);
            $this->assertSame('https://app.example.com', $headers['Access-Control-Allow-Origin']);
            $this->assertSame('Authorization, Content-Type', $headers['Access-Control-Allow-Headers']);
            $this->assertStringContainsString('POST', $headers['Access-Control-Allow-Methods']);
            $this->assertSame('900', $headers['Access-Control-Max-Age']);
        }
    }
}
