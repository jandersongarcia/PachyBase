<?php

declare(strict_types=1);

namespace Tests\Services;

use PachyBase\Config;
use PachyBase\Database\Connection;
use PachyBase\Http\Request;
use PachyBase\Services\SystemStatusService;
use PHPUnit\Framework\TestCase;

class SystemStatusServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Connection::reset();
        Config::reset();
    }

    public function testBuildStatusPayloadIncludesRequestDetailsOutsideProduction(): void
    {
        Config::override([
            'APP_ENV' => 'development',
            'APP_NAME' => 'PachyBase Dev',
        ]);

        $payload = (new SystemStatusService())->buildStatusPayload(new Request('GET', '/status'));

        $this->assertSame('PachyBase Dev', $payload['name']);
        $this->assertSame('running', $payload['status']);
        $this->assertSame('development', $payload['environment']);
        $this->assertSame('GET', $payload['request']['method']);
        $this->assertSame('/status', $payload['request']['path']);
    }

    public function testBuildStatusPayloadOmitsRequestDetailsInProduction(): void
    {
        Config::override([
            'APP_ENV' => 'production',
            'APP_NAME' => 'PachyBase Prod',
        ]);

        $payload = (new SystemStatusService())->buildStatusPayload(new Request('GET', '/status'));

        $this->assertSame('PachyBase Prod', $payload['name']);
        $this->assertArrayNotHasKey('environment', $payload);
        $this->assertArrayNotHasKey('request', $payload);
    }

    public function testBuildHealthPayloadMarksDatabaseAsDegradedWhenConnectionCheckFails(): void
    {
        Connection::reset();
        Config::override([
            'APP_ENV' => 'production',
            'APP_NAME' => 'PachyBase Health',
            'DB_DRIVER' => 'sqlite',
        ]);

        $payload = (new SystemStatusService())->buildHealthPayload(new Request('GET', '/health'), true);

        $this->assertSame('degraded', $payload['status']);
        $this->assertSame('sqlite', $payload['checks']['database']['driver']);
        $this->assertFalse($payload['checks']['database']['connected']);
        $this->assertArrayNotHasKey('environment', $payload);
    }
}
