<?php

declare(strict_types=1);

namespace Tests\Http;

use PachyBase\Http\DatabaseRateLimiter;
use PachyBase\Http\RateLimitPolicy;
use PachyBase\Http\Request;
use RuntimeException;
use Tests\Support\DatabaseIntegrationTestCase;

class DatabaseRateLimiterTest extends DatabaseIntegrationTestCase
{
    /**
     * @var array<int, string>
     */
    private array $scopeKeys = [];

    protected function tearDown(): void
    {
        foreach ($this->scopeKeys as $scopeKey) {
            $this->executor?->execute(
                sprintf('DELETE FROM %s WHERE scope_key = :scope_key', $this->table('pb_rate_limit_buckets')),
                ['scope_key' => $scopeKey]
            );
        }

        $this->scopeKeys = [];

        parent::tearDown();
    }

    public function testEnforceCreatesIncrementsAndLimitsBuckets(): void
    {
        $suffix = 'rate-limit-' . bin2hex(random_bytes(3));
        $scopeKey = 'api:' . $suffix;
        $this->scopeKeys[] = $scopeKey;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.21';

        $limiter = new DatabaseRateLimiter(new RateLimitPolicy(true, 2, 60, __FILE__), $this->executor);
        $request = new Request('GET', '/api/' . $suffix, [], ['X-Tenant-Id' => 'tenant-alpha']);

        $limiter->enforce($request);
        $limiter->enforce($request);

        $row = $this->executor?->selectOne(
            sprintf('SELECT * FROM %s WHERE scope_key = :scope_key LIMIT 1', $this->table('pb_rate_limit_buckets')),
            ['scope_key' => $scopeKey]
        );

        $this->assertSame(2, (int) ($row['request_count'] ?? 0));
        $this->assertSame('tenant-alpha', $row['tenant_key']);
        $this->assertStringStartsWith('ip:', (string) $row['credential_key']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(429);

        $limiter->enforce($request);
    }

    public function testOptionsRequestsBypassAndExpiredBucketsReset(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.22';
        $optionsScope = 'api:options-' . bin2hex(random_bytes(3));
        $resetScope = 'api:reset-' . bin2hex(random_bytes(3));
        $this->scopeKeys[] = $optionsScope;
        $this->scopeKeys[] = $resetScope;

        $limiter = new DatabaseRateLimiter(new RateLimitPolicy(true, 1, 60, __FILE__), $this->executor);
        $limiter->enforce(new Request('OPTIONS', '/api/' . substr($optionsScope, 4)));

        $optionsCount = (int) ($this->executor?->scalar(
            sprintf('SELECT COUNT(*) AS aggregate FROM %s WHERE scope_key = :scope_key', $this->table('pb_rate_limit_buckets')),
            ['scope_key' => $optionsScope]
        ) ?? 0);

        $this->assertSame(0, $optionsCount);

        $request = new Request('GET', '/api/' . substr($resetScope, 4));
        $limiter->enforce($request);
        $this->executor?->execute(
            sprintf('UPDATE %s SET request_count = :request_count, reset_at = :reset_at WHERE scope_key = :scope_key', $this->table('pb_rate_limit_buckets')),
            [
                'request_count' => 9,
                'reset_at' => gmdate('Y-m-d H:i:s', time() - 5),
                'scope_key' => $resetScope,
            ]
        );

        $limiter->enforce($request);

        $row = $this->executor?->selectOne(
            sprintf('SELECT * FROM %s WHERE scope_key = :scope_key LIMIT 1', $this->table('pb_rate_limit_buckets')),
            ['scope_key' => $resetScope]
        );

        $this->assertSame(1, (int) ($row['request_count'] ?? 0));
    }

    public function testAuthorizationAndTenantValuesProduceIndependentBuckets(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.23';
        $suffix = 'bucket-' . bin2hex(random_bytes(3));
        $scopeKey = 'api:' . $suffix;
        $this->scopeKeys[] = $scopeKey;
        $limiter = new DatabaseRateLimiter(new RateLimitPolicy(true, 10, 60, __FILE__), $this->executor);

        $limiter->enforce(new Request('GET', '/api/' . $suffix, [], [
            'Authorization' => 'Bearer token-a',
            'X-Tenant-Id' => 'tenant-a',
        ]));
        $limiter->enforce(new Request('GET', '/api/' . $suffix, [], [
            'Authorization' => 'Bearer token-a',
            'X-Tenant-Id' => 'tenant-b',
        ]));
        $limiter->enforce(new Request('GET', '/api/' . $suffix, [], [
            'Authorization' => 'Bearer token-b',
            'X-Tenant-Id' => 'tenant-a',
        ]));

        $count = (int) ($this->executor?->scalar(
            sprintf('SELECT COUNT(*) AS aggregate FROM %s WHERE scope_key = :scope_key', $this->table('pb_rate_limit_buckets')),
            ['scope_key' => $scopeKey]
        ) ?? 0);

        $this->assertSame(3, $count);
    }
}
