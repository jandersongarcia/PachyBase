<?php

declare(strict_types=1);

namespace Tests\Http;

use PachyBase\Config;
use PachyBase\Controllers\SystemController;
use PachyBase\Database\Connection;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;
use PachyBase\Http\ResponseCaptured;
use PHPUnit\Framework\TestCase;

class SystemControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        ApiResponse::disableCapture();
        Config::reset();
        Connection::reset();
        $_SERVER = [];
    }

    public function testStatusDoesNotExposeDatabaseTopologyOrErrorsInDevelopment(): void
    {
        Config::override([
            'APP_ENV' => 'development',
            'APP_DEBUG' => 'true',
            'APP_NAME' => 'PachyBase',
            'DB_DRIVER' => 'mysql',
            'DB_HOST' => 'db-internal',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'secret_db',
            'DB_USERNAME' => 'app_user',
            'DB_PASSWORD' => 'super-secret',
        ]);

        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        $controller = new SystemController();

        try {
            $controller->status(new Request('GET', '/'));
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();

            $this->assertArrayHasKey('database', $payload['data']);
            $this->assertSame('mysql', $payload['data']['database']['driver']);
            $this->assertArrayNotHasKey('host', $payload['data']['database']);
            $this->assertArrayNotHasKey('port', $payload['data']['database']);
            $this->assertArrayNotHasKey('database', $payload['data']['database']);
            $this->assertArrayNotHasKey('error', $payload['data']['database']);
        }
    }
}
