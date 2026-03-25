<?php

declare(strict_types=1);

namespace PachyBase\Services\Tenancy;

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
            throw new RuntimeException('The default tenant is not configured.', 500);
        }

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
}
