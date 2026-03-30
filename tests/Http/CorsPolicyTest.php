<?php

declare(strict_types=1);

namespace Tests\Http;

use PachyBase\Config;
use PachyBase\Http\CorsPolicy;
use PHPUnit\Framework\TestCase;

class CorsPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::reset();
    }

    public function testWildcardOriginEchoesSpecificOriginWhenCredentialsAreEnabled(): void
    {
        Config::override([
            'APP_CORS_ALLOWED_ORIGINS' => '*',
            'APP_CORS_ALLOW_CREDENTIALS' => 'true',
        ]);

        $headers = CorsPolicy::fromConfig()->responseHeaders('https://agent.example.com');

        $this->assertSame('https://agent.example.com', $headers['Access-Control-Allow-Origin']);
        $this->assertSame('true', $headers['Access-Control-Allow-Credentials']);
        $this->assertSame('Origin', $headers['Vary']);
    }

    public function testWildcardAllowedHeadersReflectRequestedHeadersOnPreflight(): void
    {
        Config::override([
            'APP_CORS_ALLOWED_ORIGINS' => 'https://app.example.com',
            'APP_CORS_ALLOWED_HEADERS' => '*',
            'APP_CORS_MAX_AGE' => '300',
        ]);

        $headers = CorsPolicy::fromConfig()->preflightHeaders(
            'https://app.example.com',
            ['POST'],
            'Authorization, X-Request-Id'
        );

        $this->assertSame('Authorization, X-Request-Id', $headers['Access-Control-Allow-Headers']);
        $this->assertSame('300', $headers['Access-Control-Max-Age']);
        $this->assertStringContainsString('Access-Control-Request-Headers', $headers['Vary']);
    }
}
