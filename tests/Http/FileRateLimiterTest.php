<?php

declare(strict_types=1);

namespace Tests\Http;

use PachyBase\Http\FileRateLimiter;
use PachyBase\Http\RateLimitPolicy;
use PachyBase\Http\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FileRateLimiterTest extends TestCase
{
    private string $storagePath = '';

    protected function tearDown(): void
    {
        $_SERVER = [];

        if ($this->storagePath !== '' && is_file($this->storagePath)) {
            unlink($this->storagePath);
        }

        $directory = $this->storagePath !== '' ? dirname($this->storagePath) : '';
        if ($directory !== '' && is_dir($directory)) {
            @rmdir($directory);
        }
    }

    public function testEnforceThrowsWhenTheClientExceedsTheWindowLimit(): void
    {
        $this->storagePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-rate-limit-' . bin2hex(random_bytes(4)) . '.json';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $limiter = new FileRateLimiter(new RateLimitPolicy(true, 2, 60, $this->storagePath));
        $request = new Request('GET', '/api/system-settings');

        $limiter->enforce($request);
        $limiter->enforce($request);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(429);

        $limiter->enforce($request);
    }

    public function testBearerCredentialsUseIndependentBuckets(): void
    {
        $this->storagePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-rate-limit-' . bin2hex(random_bytes(4)) . '.json';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $limiter = new FileRateLimiter(new RateLimitPolicy(true, 1, 60, $this->storagePath));

        $limiter->enforce(new Request('GET', '/api/system-settings', [], ['Authorization' => 'Bearer first']));
        $limiter->enforce(new Request('GET', '/api/system-settings', [], ['Authorization' => 'Bearer second']));

        $this->assertFileExists($this->storagePath);
    }
}
