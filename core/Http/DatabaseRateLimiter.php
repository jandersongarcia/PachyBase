<?php

declare(strict_types=1);

namespace PachyBase\Http;

use PachyBase\Config\TenancyConfig;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Query\QueryExecutorInterface;
use RuntimeException;

final class DatabaseRateLimiter
{
    private readonly QueryExecutorInterface $queryExecutor;
    private readonly string $table;

    public function __construct(
        private readonly ?RateLimitPolicy $policy = null,
        ?QueryExecutorInterface $queryExecutor = null
    ) {
        $connection = Connection::getInstance();
        $adapter = AdapterFactory::make($connection);

        $this->queryExecutor = $queryExecutor ?? new PdoQueryExecutor($connection->getPDO());
        $this->table = $adapter->quoteIdentifier('pb_rate_limit_buckets');
    }

    public function enforce(Request $request): void
    {
        $policy = $this->policy ?? RateLimitPolicy::fromConfig();

        if (!$policy->enabled() || $request->getMethod() === 'OPTIONS') {
            return;
        }

        [$bucketKey, $tenantKey, $credentialKey, $scopeKey] = $this->bucketParts($request);
        $now = time();
        $resetAt = gmdate('Y-m-d H:i:s', $now + $policy->windowSeconds());

        $this->queryExecutor->transaction(function (QueryExecutorInterface $queryExecutor) use ($bucketKey, $tenantKey, $credentialKey, $scopeKey, $policy, $now, $resetAt): void {
            $row = $queryExecutor->selectOne(
                sprintf('SELECT * FROM %s WHERE bucket_key = :bucket_key FOR UPDATE', $this->table),
                ['bucket_key' => $bucketKey]
            );

            if ($row === null) {
                $queryExecutor->execute(
                    sprintf(
                        'INSERT INTO %s (bucket_key, tenant_key, credential_key, scope_key, request_count, reset_at) VALUES (:bucket_key, :tenant_key, :credential_key, :scope_key, :request_count, :reset_at)',
                        $this->table
                    ),
                    [
                        'bucket_key' => $bucketKey,
                        'tenant_key' => $tenantKey,
                        'credential_key' => $credentialKey,
                        'scope_key' => $scopeKey,
                        'request_count' => 1,
                        'reset_at' => $resetAt,
                    ]
                );

                return;
            }

            $currentCount = (int) ($row['request_count'] ?? 0);
            $currentResetAt = strtotime((string) ($row['reset_at'] ?? '')) ?: 0;

            if ($currentResetAt <= $now) {
                $queryExecutor->execute(
                    sprintf(
                        'UPDATE %s SET request_count = :request_count, reset_at = :reset_at, updated_at = :updated_at WHERE bucket_key = :bucket_key',
                        $this->table
                    ),
                    [
                        'bucket_key' => $bucketKey,
                        'request_count' => 1,
                        'reset_at' => $resetAt,
                        'updated_at' => gmdate('Y-m-d H:i:s'),
                    ]
                );

                return;
            }

            if ($currentCount >= $policy->maxRequests()) {
                $retryAfter = max(1, $currentResetAt - $now);

                throw new RuntimeException(
                    sprintf('Rate limit exceeded. Retry in %d second(s).', $retryAfter),
                    429
                );
            }

            $queryExecutor->execute(
                sprintf(
                    'UPDATE %s SET request_count = :request_count, updated_at = :updated_at WHERE bucket_key = :bucket_key',
                    $this->table
                ),
                [
                    'bucket_key' => $bucketKey,
                    'request_count' => $currentCount + 1,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]
            );
        });
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function bucketParts(Request $request): array
    {
        $tenantKey = trim((string) $request->header(TenancyConfig::headerName(), ''));
        $authorization = trim((string) $request->header('Authorization', ''));
        $credentialKey = $authorization !== ''
            ? 'auth:' . sha1($authorization)
            : 'ip:' . sha1($this->resolveClientIp());
        $scopeKey = $this->scopeKey($request);
        $bucketKey = sha1(implode('|', [$tenantKey !== '' ? $tenantKey : '-', $credentialKey, $scopeKey]));

        return [$bucketKey, $tenantKey !== '' ? $tenantKey : '-', $credentialKey, $scopeKey];
    }

    private function scopeKey(Request $request): string
    {
        $segments = array_values(array_filter(explode('/', trim($request->getPath(), '/'))));

        if ($segments === []) {
            return 'root';
        }

        if (($segments[0] ?? null) !== 'api') {
            return $segments[0];
        }

        return isset($segments[1]) ? 'api:' . $segments[1] : 'api';
    }

    private function resolveClientIp(): string
    {
        $forwardedFor = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwardedFor !== '') {
            $parts = explode(',', $forwardedFor);
            $candidate = trim((string) ($parts[0] ?? ''));

            if ($candidate !== '') {
                return $candidate;
            }
        }

        $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        return $remoteAddr !== '' ? $remoteAddr : 'unknown';
    }
}
