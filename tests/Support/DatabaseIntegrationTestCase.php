<?php

declare(strict_types=1);

namespace Tests\Support;

use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PHPUnit\Framework\TestCase;

abstract class DatabaseIntegrationTestCase extends TestCase
{
    protected ?PdoQueryExecutor $executor = null;
    protected string $basePath = '';

    /**
     * @var array<int, array{id: int, slug: string}>
     */
    private array $trackedTenants = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = dirname(__DIR__, 2);
        Config::load($this->basePath);
        $this->executor = new PdoQueryExecutor(Connection::getInstance()->getPDO());
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->trackedTenants) as $tenant) {
            $this->cleanupTenant($tenant['id'], $tenant['slug']);
        }

        $this->executor = null;
        Connection::reset();
        Config::reset();
        $_SERVER = [];
        $_GET = [];
        $_POST = [];

        parent::tearDown();
    }

    /**
     * @param array<string, int|null> $quota
     * @return array{id: int, slug: string}
     */
    protected function createTenant(?string $slug = null, ?string $name = null, bool $isActive = true, array $quota = []): array
    {
        $slug ??= 'tenant-' . bin2hex(random_bytes(4));
        $name ??= 'Tenant ' . $slug;

        $this->executor?->execute(
            sprintf(
                'INSERT INTO %s (name, slug, is_active) VALUES (:name, :slug, :is_active)',
                $this->table('pb_tenants')
            ),
            [
                'name' => $name,
                'slug' => $slug,
                'is_active' => $isActive,
            ]
        );

        $tenantId = (int) Connection::getInstance()->getPDO()->lastInsertId();
        $this->trackTenant($tenantId, $slug);

        if ($quota !== []) {
            $this->upsertQuota($tenantId, $quota);
        }

        return ['id' => $tenantId, 'slug' => $slug];
    }

    protected function trackTenant(int $tenantId, string $slug): void
    {
        $this->trackedTenants[] = ['id' => $tenantId, 'slug' => $slug];
    }

    protected function defaultTenantId(): int
    {
        return (int) ($this->executor?->scalar(
            sprintf(
                'SELECT id AS aggregate FROM %s WHERE slug = :slug LIMIT 1',
                $this->table('pb_tenants')
            ),
            ['slug' => 'default']
        ) ?? 0);
    }

    /**
     * @param array<string, int|null> $quota
     */
    protected function upsertQuota(int $tenantId, array $quota): void
    {
        $existing = $this->executor?->selectOne(
            sprintf('SELECT id FROM %s WHERE tenant_id = :tenant_id LIMIT 1', $this->table('pb_tenant_quotas')),
            ['tenant_id' => $tenantId]
        );

        $bindings = [
            'tenant_id' => $tenantId,
            'max_requests_per_month' => $quota['max_requests_per_month'] ?? null,
            'max_tokens' => $quota['max_tokens'] ?? null,
            'max_entities' => $quota['max_entities'] ?? null,
            'max_storage_bytes' => $quota['max_storage_bytes'] ?? null,
        ];

        if ($existing === null) {
            $this->executor?->execute(
                sprintf(
                    'INSERT INTO %s (tenant_id, max_requests_per_month, max_tokens, max_entities, max_storage_bytes) VALUES (:tenant_id, :max_requests_per_month, :max_tokens, :max_entities, :max_storage_bytes)',
                    $this->table('pb_tenant_quotas')
                ),
                $bindings
            );

            return;
        }

        $this->executor?->execute(
            sprintf(
                'UPDATE %s SET max_requests_per_month = :max_requests_per_month, max_tokens = :max_tokens, max_entities = :max_entities, max_storage_bytes = :max_storage_bytes, updated_at = :updated_at WHERE tenant_id = :tenant_id',
                $this->table('pb_tenant_quotas')
            ),
            array_merge($bindings, ['updated_at' => gmdate('Y-m-d H:i:s')])
        );
    }

    protected function table(string $name): string
    {
        return AdapterFactory::make()->quoteIdentifier($name);
    }

    protected function absolutePath(string $relativePath): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }

    protected function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }

    protected function cleanupTenant(int $tenantId, string $slug): void
    {
        if ($this->executor === null) {
            return;
        }

        foreach ([
            'pb_webhook_deliveries',
            'pb_webhooks',
            'pb_async_jobs',
            'pb_file_objects',
            'pb_project_secrets',
            'pb_project_backups',
            'pb_auth_sessions',
            'pb_api_tokens',
            'pb_users',
            'pb_system_settings',
            'pb_tenant_quota_usage',
            'pb_tenant_quotas',
            'pb_audit_logs',
        ] as $table) {
            $this->executor->execute(
                sprintf('DELETE FROM %s WHERE tenant_id = :tenant_id', $this->table($table)),
                ['tenant_id' => $tenantId]
            );
        }

        $this->executor->execute(
            sprintf('DELETE FROM %s WHERE tenant_key = :tenant_key', $this->table('pb_rate_limit_buckets')),
            ['tenant_key' => $slug]
        );
        $this->executor->execute(
            sprintf('DELETE FROM %s WHERE id = :id', $this->table('pb_tenants')),
            ['id' => $tenantId]
        );

        $this->deleteDirectory($this->absolutePath('build/storage/projects/' . $slug));
        $this->deleteDirectory($this->absolutePath('build/backups/' . $slug));
    }
}
