<?php

declare(strict_types=1);

namespace Tests\Http;

use PachyBase\Api\Controllers\SystemController;
use PachyBase\Config;
use PachyBase\Database\Connection;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;
use PachyBase\Http\ResponseCaptured;
use PachyBase\Release\ProjectMetadata;
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

    public function testStatusDoesNotTouchDatabaseInDevelopment(): void
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

            $this->assertSame(ProjectMetadata::version(), $payload['data']['version']);
            $this->assertArrayNotHasKey('database', $payload['data']);
            $this->assertSame('/', $payload['data']['request']['path']);
        }
    }

    public function testDeepHealthReportsDatabaseStateWithoutExposingSecrets(): void
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
        $_SERVER['REQUEST_URI'] = '/health/deep';

        $controller = new SystemController();

        try {
            $controller->deepHealth(new Request('GET', '/health/deep'));
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();
            $database = $payload['data']['checks']['database'];

            $this->assertSame(503, $captured->getStatusCode());
            $this->assertSame('degraded', $payload['data']['status']);
            $this->assertSame('mysql', $database['driver']);
            $this->assertFalse($database['connected']);
            $this->assertArrayNotHasKey('host', $database);
            $this->assertArrayNotHasKey('port', $database);
            $this->assertArrayNotHasKey('database', $database);
            $this->assertArrayNotHasKey('error', $database);
        }
    }
}
