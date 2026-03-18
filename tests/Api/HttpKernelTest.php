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
        }
    }
}
