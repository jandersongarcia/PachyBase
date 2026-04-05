<?php

declare(strict_types=1);

namespace Tests\Http;

use PachyBase\Config;
use PachyBase\Http\RateLimitPolicy;
use PHPUnit\Framework\TestCase;

class RateLimitPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::reset();
    }

    public function testFromConfigNormalizesValuesAndBounds(): void
    {
        Config::override([
            'APP_RATE_LIMIT_ENABLED' => 'yes',
            'APP_RATE_LIMIT_MAX_REQUESTS' => '0',
            'APP_RATE_LIMIT_WINDOW_SECONDS' => '-5',
            'APP_RATE_LIMIT_STORAGE_PATH' => 'build/runtime/custom-rate-limit.json',
            'APP_RATE_LIMIT_BACKEND' => 'invalid',
        ]);

        $policy = RateLimitPolicy::fromConfig();

        $this->assertTrue($policy->enabled());
        $this->assertSame(1, $policy->maxRequests());
        $this->assertSame(1, $policy->windowSeconds());
        $this->assertStringEndsWith('build' . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'custom-rate-limit.json', $policy->storagePath());
        $this->assertSame('database', $policy->backend());
    }

    public function testFromConfigPreservesAbsolutePathsAndSupportedBackends(): void
    {
        Config::override([
            'APP_RATE_LIMIT_ENABLED' => 'true',
            'APP_RATE_LIMIT_MAX_REQUESTS' => '120',
            'APP_RATE_LIMIT_WINDOW_SECONDS' => '60',
            'APP_RATE_LIMIT_STORAGE_PATH' => 'C:\\tmp\\rate-limit.json',
            'APP_RATE_LIMIT_BACKEND' => 'file',
        ]);

        $policy = RateLimitPolicy::fromConfig();

        $this->assertSame('C:\\tmp\\rate-limit.json', $policy->storagePath());
        $this->assertSame('file', $policy->backend());
    }
}
