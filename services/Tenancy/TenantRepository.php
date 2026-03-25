<?php

declare(strict_types=1);

namespace PachyBase\Services\Tenancy;

use PachyBase\Config;
use PachyBase\Config\TenancyConfig;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Query\QueryExecutorInterface;
use RuntimeException;

final class TenantRepository
{
    private readonly QueryExecutorInterface $queryExecutor;
    private readonly string $table;

    public function __construct(?QueryExecutorInterface $queryExecutor = null)
    {
        $connection = Connection::getInstance();
        $adapter = AdapterFactory::make($connection);

        $this->queryExecutor = $queryExecutor ?? new PdoQueryExecutor($connection->getPDO());
        $this->table = $adapter->quoteIdentifier('pb_tenants');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveById(int $id): ?array
    {
        return $this->queryExecutor->selectOne(
            sprintf('SELECT * FROM %s WHERE id = :id AND is_active = :is_active LIMIT 1', $this->table),
            [
                'id' => $id,
                'is_active' => true,
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveBySlug(string $slug): ?array
    {
        return $this->queryExecutor->selectOne(
            sprintf('SELECT * FROM %s WHERE slug = :slug AND is_active = :is_active LIMIT 1', $this->table),
            [
                'slug' => strtolower(trim($slug)),
                'is_active' => true,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultTenant(): array
    {
        $tenant = $this->findActiveBySlug(TenancyConfig::defaultSlug());

        if ($tenant === null) {
            $this->queryExecutor->execute(
                sprintf('INSERT INTO %s (name, slug, is_active) VALUES (:name, :slug, :is_active)', $this->table),
                [
                    'name' => TenancyConfig::defaultName(),
                    'slug' => TenancyConfig::defaultSlug(),
                    'is_active' => true,
                ]
            );

            $tenant = $this->findActiveBySlug(TenancyConfig::defaultSlug());
        }

        if ($tenant === null) {
            throw new RuntimeException('The default tenant is not configured.', 500);
        }

        $this->ensureDefaultSettings((int) $tenant['id']);

        return $tenant;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveReference(?string $reference): array
    {
        $normalized = strtolower(trim((string) $reference));

        if ($normalized === '') {
            return $this->defaultTenant();
        }

        if (ctype_digit($normalized)) {
            $tenant = $this->findActiveById((int) $normalized);

            if ($tenant !== null) {
                return $tenant;
            }
        }

        $tenant = $this->findActiveBySlug($normalized);

        if ($tenant !== null) {
            return $tenant;
        }

        throw new RuntimeException(sprintf('Tenant not found for reference "%s".', $reference), 404);
    }

    private function ensureDefaultSettings(int $tenantId): void
    {
        $table = AdapterFactory::make(Connection::getInstance())->quoteIdentifier('pb_system_settings');
        $defaults = [
            'app.name' => [
                'value' => (string) Config::get('APP_NAME', 'PachyBase'),
                'type' => 'string',
                'public' => true,
            ],
            'api.contract_version' => [
                'value' => '1.0',
                'type' => 'string',
                'public' => true,
            ],
            'auth.guard' => [
                'value' => 'bearer',
                'type' => 'string',
                'public' => false,
            ],
            'database.default_driver' => [
                'value' => (string) Config::get('DB_DRIVER', 'mysql'),
                'type' => 'string',
                'public' => false,
            ],
        ];

        foreach ($defaults as $key => $setting) {
            $exists = $this->queryExecutor->selectOne(
                sprintf('SELECT id FROM %s WHERE tenant_id = :tenant_id AND setting_key = :setting_key LIMIT 1', $table),
                ['tenant_id' => $tenantId, 'setting_key' => $key]
            );

            if ($exists !== null) {
                continue;
            }

            $this->queryExecutor->execute(
                sprintf(
                    'INSERT INTO %s (tenant_id, setting_key, setting_value, value_type, is_public) VALUES (:tenant_id, :setting_key, :setting_value, :value_type, :is_public)',
                    $table
                ),
                [
                    'tenant_id' => $tenantId,
                    'setting_key' => $key,
                    'setting_value' => $setting['value'],
                    'value_type' => $setting['type'],
                    'is_public' => $setting['public'],
                ]
            );
        }
    }
}
