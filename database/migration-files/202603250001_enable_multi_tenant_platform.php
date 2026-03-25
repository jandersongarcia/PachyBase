<?php

declare(strict_types=1);

use PachyBase\Config\TenancyConfig;
use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Migrations\MigrationInterface;
use PachyBase\Database\Query\QueryExecutorInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603250001';
    }

    public function description(): string
    {
        return 'Enable multi-tenant isolation, shared rate limiting, quotas, and audit storage';
    }

    public function up(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        $this->createTenantsTable($queryExecutor, $adapter);
        $defaultTenantId = $this->ensureDefaultTenant($queryExecutor, $adapter);

        $this->addTenantColumn($queryExecutor, $adapter, 'pb_system_settings', false);
        $this->addTenantColumn($queryExecutor, $adapter, 'pb_users', false);
        $this->addTenantColumn($queryExecutor, $adapter, 'pb_auth_sessions', false);
        $this->addTenantColumn($queryExecutor, $adapter, 'pb_api_tokens', false);

        $this->backfillTenantId($queryExecutor, $adapter, 'pb_system_settings', $defaultTenantId);
        $this->backfillTenantId($queryExecutor, $adapter, 'pb_users', $defaultTenantId);
        $this->backfillTenantId($queryExecutor, $adapter, 'pb_auth_sessions', $defaultTenantId);
        $this->backfillTenantId($queryExecutor, $adapter, 'pb_api_tokens', $defaultTenantId);

        $this->enforceTenantNotNull($queryExecutor, $adapter, 'pb_system_settings');
        $this->enforceTenantNotNull($queryExecutor, $adapter, 'pb_users');
        $this->enforceTenantNotNull($queryExecutor, $adapter, 'pb_auth_sessions');
        $this->enforceTenantNotNull($queryExecutor, $adapter, 'pb_api_tokens');

        $this->replaceUniqueConstraint(
            $queryExecutor,
            $adapter,
            'pb_system_settings',
            'pb_system_settings_setting_key_unique',
            ['tenant_id', 'setting_key']
        );
        $this->replaceUniqueConstraint(
            $queryExecutor,
            $adapter,
            'pb_users',
            'pb_users_email_unique',
            ['tenant_id', 'email']
        );

        $this->ensureIndex($queryExecutor, $adapter, 'pb_users', 'pb_users_tenant_id_index', ['tenant_id']);
        $this->ensureIndex($queryExecutor, $adapter, 'pb_auth_sessions', 'pb_auth_sessions_tenant_id_index', ['tenant_id']);
        $this->ensureIndex($queryExecutor, $adapter, 'pb_api_tokens', 'pb_api_tokens_tenant_id_index', ['tenant_id']);
        $this->ensureIndex($queryExecutor, $adapter, 'pb_system_settings', 'pb_system_settings_tenant_id_index', ['tenant_id']);

        $this->ensureColumn(
            $queryExecutor,
            $adapter,
            'pb_api_tokens',
            'created_by_user_id',
            $adapter->driver() === 'mysql' ? '`created_by_user_id` BIGINT UNSIGNED NULL' : '"created_by_user_id" BIGINT NULL'
        );
        $this->ensureColumn(
            $queryExecutor,
            $adapter,
            'pb_api_tokens',
            'revoked_by_user_id',
            $adapter->driver() === 'mysql' ? '`revoked_by_user_id` BIGINT UNSIGNED NULL' : '"revoked_by_user_id" BIGINT NULL'
        );
        $this->ensureColumn(
            $queryExecutor,
            $adapter,
            'pb_api_tokens',
            'revoked_reason',
            $adapter->driver() === 'mysql' ? '`revoked_reason` VARCHAR(120) NULL' : '"revoked_reason" VARCHAR(120) NULL'
        );
        $this->ensureColumn(
            $queryExecutor,
            $adapter,
            'pb_auth_sessions',
            'revoked_reason',
            $adapter->driver() === 'mysql' ? '`revoked_reason` VARCHAR(120) NULL' : '"revoked_reason" VARCHAR(120) NULL'
        );

        $this->createRateLimitTable($queryExecutor, $adapter);
        $this->createAuditTable($queryExecutor, $adapter);
        $this->createTenantQuotaTable($queryExecutor, $adapter);
        $this->createTenantQuotaUsageTable($queryExecutor, $adapter);
    }

    public function down(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        foreach ([
            'pb_tenant_quota_usage',
            'pb_tenant_quotas',
            'pb_audit_logs',
            'pb_rate_limit_buckets',
        ] as $table) {
            $queryExecutor->execute('DROP TABLE IF EXISTS ' . $adapter->quoteIdentifier($table));
        }

        $this->dropColumn($queryExecutor, $adapter, 'pb_api_tokens', 'created_by_user_id');
        $this->dropColumn($queryExecutor, $adapter, 'pb_api_tokens', 'revoked_by_user_id');
        $this->dropColumn($queryExecutor, $adapter, 'pb_api_tokens', 'revoked_reason');
        $this->dropColumn($queryExecutor, $adapter, 'pb_auth_sessions', 'revoked_reason');
        $this->dropColumn($queryExecutor, $adapter, 'pb_api_tokens', 'tenant_id');
        $this->dropColumn($queryExecutor, $adapter, 'pb_auth_sessions', 'tenant_id');
        $this->dropColumn($queryExecutor, $adapter, 'pb_users', 'tenant_id');
        $this->dropColumn($queryExecutor, $adapter, 'pb_system_settings', 'tenant_id');
        $queryExecutor->execute('DROP TABLE IF EXISTS ' . $adapter->quoteIdentifier('pb_tenants'));
    }

    private function createTenantsTable(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        $table = $adapter->quoteIdentifier('pb_tenants');

        if ($adapter->driver() === 'mysql') {
            $queryExecutor->execute(
                <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(120) NOT NULL,
                    `slug` VARCHAR(80) NOT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `pb_tenants_slug_unique` (`slug`)
                )
                SQL
            );

            return;
        }

        $queryExecutor->execute(
            <<<SQL
            CREATE TABLE IF NOT EXISTS {$table} (
                "id" BIGSERIAL PRIMARY KEY,
                "name" VARCHAR(120) NOT NULL,
                "slug" VARCHAR(80) NOT NULL,
                "is_active" BOOLEAN NOT NULL DEFAULT TRUE,
                "created_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                "updated_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT "pb_tenants_slug_unique" UNIQUE ("slug")
            )
            SQL
        );
    }

    private function ensureDefaultTenant(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): int
    {
        $table = $adapter->quoteIdentifier('pb_tenants');
        $slug = TenancyConfig::defaultSlug();
        $name = TenancyConfig::defaultName();
        $existing = $queryExecutor->selectOne(
            sprintf('SELECT id FROM %s WHERE slug = :slug LIMIT 1', $table),
            ['slug' => $slug]
        );

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $queryExecutor->execute(
            sprintf('INSERT INTO %s (name, slug, is_active) VALUES (:name, :slug, :is_active)', $table),
            [
                'name' => $name,
                'slug' => $slug,
                'is_active' => true,
            ]
        );

        $inserted = $queryExecutor->selectOne(
            sprintf('SELECT id FROM %s WHERE slug = :slug LIMIT 1', $table),
            ['slug' => $slug]
        );

        return (int) ($inserted['id'] ?? 0);
    }

    private function addTenantColumn(
        QueryExecutorInterface $queryExecutor,
        DatabaseAdapterInterface $adapter,
        string $tableName,
        bool $nullable
    ): void {
        $definition = $adapter->driver() === 'mysql'
            ? sprintf('`tenant_id` BIGINT UNSIGNED %s', $nullable ? 'NULL' : 'NULL')
            : sprintf('"tenant_id" BIGINT %s', $nullable ? 'NULL' : 'NULL');

        $this->ensureColumn($queryExecutor, $adapter, $tableName, 'tenant_id', $definition);
    }

    private function ensureColumn(
        QueryExecutorInterface $queryExecutor,
        DatabaseAdapterInterface $adapter,
        string $tableName,
        string $columnName,
        string $definition
    ): void {
        foreach ($adapter->listColumns($tableName) as $column) {
            if ($column->name === $columnName) {
                return;
            }
        }

        $queryExecutor->execute(
            sprintf(
                'ALTER TABLE %s ADD COLUMN %s',
                $adapter->quoteIdentifier($tableName),
                $definition
            )
        );
    }

    private function backfillTenantId(
        QueryExecutorInterface $queryExecutor,
        DatabaseAdapterInterface $adapter,
        string $tableName,
        int $tenantId
    ): void {
        $queryExecutor->execute(
            sprintf(
                'UPDATE %s SET %s = :tenant_id WHERE %s IS NULL',
                $adapter->quoteIdentifier($tableName),
                $adapter->quoteIdentifier('tenant_id'),
                $adapter->quoteIdentifier('tenant_id')
            ),
            ['tenant_id' => $tenantId]
        );
    }

    private function enforceTenantNotNull(
        QueryExecutorInterface $queryExecutor,
        DatabaseAdapterInterface $adapter,
        string $tableName
    ): void {
        $table = $adapter->quoteIdentifier($tableName);

        if ($adapter->driver() === 'mysql') {
            $queryExecutor->execute(sprintf('ALTER TABLE %s MODIFY COLUMN `tenant_id` BIGINT UNSIGNED NOT NULL', $table));

            return;
        }

        $queryExecutor->execute(sprintf('ALTER TABLE %s ALTER COLUMN "tenant_id" SET NOT NULL', $table));
    }

    /**
     * @param array<int, string> $columns
     */
    private function replaceUniqueConstraint(
        QueryExecutorInterface $queryExecutor,
        DatabaseAdapterInterface $adapter,
        string $tableName,
        string $constraintName,
        array $columns
    ): void {
        $table = $adapter->quoteIdentifier($tableName);
        $columnSql = implode(', ', array_map([$adapter, 'quoteIdentifier'], $columns));

        if ($adapter->driver() === 'mysql') {
            foreach ($adapter->listIndexes($tableName) as $index) {
                if ($index->name === $constraintName) {
                    $queryExecutor->execute(sprintf('DROP INDEX %s ON %s', $adapter->quoteIdentifier($constraintName), $table));
                    break;
                }
            }

            $queryExecutor->execute(
                sprintf(
                    'ALTER TABLE %s ADD UNIQUE KEY %s (%s)',
                    $table,
                    $adapter->quoteIdentifier($constraintName),
                    $columnSql
                )
            );

            return;
        }

        $queryExecutor->execute(
            sprintf(
                'ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s',
                $table,
                $adapter->quoteIdentifier($constraintName)
            )
        );
        $queryExecutor->execute(
            sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s UNIQUE (%s)',
                $table,
                $adapter->quoteIdentifier($constraintName),
                $columnSql
            )
        );
    }

    /**
     * @param array<int, string> $columns
     */
    private function ensureIndex(
        QueryExecutorInterface $queryExecutor,
        DatabaseAdapterInterface $adapter,
        string $tableName,
        string $indexName,
        array $columns
    ): void {
        foreach ($adapter->listIndexes($tableName) as $index) {
            if ($index->name === $indexName) {
                return;
            }
        }

        $queryExecutor->execute(
            sprintf(
                'CREATE INDEX %s ON %s (%s)',
                $adapter->quoteIdentifier($indexName),
                $adapter->quoteIdentifier($tableName),
                implode(', ', array_map([$adapter, 'quoteIdentifier'], $columns))
            )
        );
    }

    private function createRateLimitTable(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        $table = $adapter->quoteIdentifier('pb_rate_limit_buckets');

        if ($adapter->driver() === 'mysql') {
            $queryExecutor->execute(
                <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `bucket_key` VARCHAR(80) NOT NULL,
                    `tenant_key` VARCHAR(80) NOT NULL,
                    `credential_key` VARCHAR(80) NOT NULL,
                    `scope_key` VARCHAR(120) NOT NULL,
                    `request_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    `reset_at` DATETIME NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `pb_rate_limit_buckets_bucket_key_unique` (`bucket_key`)
                )
                SQL
            );

            return;
        }

        $queryExecutor->execute(
            <<<SQL
            CREATE TABLE IF NOT EXISTS {$table} (
                "id" BIGSERIAL PRIMARY KEY,
                "bucket_key" VARCHAR(80) NOT NULL,
                "tenant_key" VARCHAR(80) NOT NULL,
                "credential_key" VARCHAR(80) NOT NULL,
                "scope_key" VARCHAR(120) NOT NULL,
                "request_count" BIGINT NOT NULL DEFAULT 0,
                "reset_at" TIMESTAMPTZ NOT NULL,
                "created_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                "updated_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT "pb_rate_limit_buckets_bucket_key_unique" UNIQUE ("bucket_key")
            )
            SQL
        );
    }

    private function createAuditTable(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        $table = $adapter->quoteIdentifier('pb_audit_logs');

        if ($adapter->driver() === 'mysql') {
            $queryExecutor->execute(
                <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `tenant_id` BIGINT UNSIGNED NULL,
                    `category` VARCHAR(40) NOT NULL,
                    `event` VARCHAR(120) NOT NULL,
                    `level` VARCHAR(20) NOT NULL,
                    `outcome` VARCHAR(20) NOT NULL,
                    `request_id` VARCHAR(80) NULL,
                    `method` VARCHAR(12) NULL,
                    `path` VARCHAR(255) NULL,
                    `status_code` INT NULL,
                    `resource` VARCHAR(120) NULL,
                    `ip` VARCHAR(64) NULL,
                    `user_agent` VARCHAR(255) NULL,
                    `principal_json` LONGTEXT NULL,
                    `metrics_json` LONGTEXT NULL,
                    `context_json` LONGTEXT NULL,
                    `error_json` LONGTEXT NULL,
                    `occurred_at` DATETIME NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
                SQL
            );

            return;
        }

        $queryExecutor->execute(
            <<<SQL
            CREATE TABLE IF NOT EXISTS {$table} (
                "id" BIGSERIAL PRIMARY KEY,
                "tenant_id" BIGINT NULL,
                "category" VARCHAR(40) NOT NULL,
                "event" VARCHAR(120) NOT NULL,
                "level" VARCHAR(20) NOT NULL,
                "outcome" VARCHAR(20) NOT NULL,
                "request_id" VARCHAR(80) NULL,
                "method" VARCHAR(12) NULL,
                "path" VARCHAR(255) NULL,
                "status_code" INT NULL,
                "resource" VARCHAR(120) NULL,
                "ip" VARCHAR(64) NULL,
                "user_agent" VARCHAR(255) NULL,
                "principal_json" TEXT NULL,
                "metrics_json" TEXT NULL,
                "context_json" TEXT NULL,
                "error_json" TEXT NULL,
                "occurred_at" TIMESTAMPTZ NOT NULL,
                "created_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                "updated_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL
        );
    }

    private function createTenantQuotaTable(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        $table = $adapter->quoteIdentifier('pb_tenant_quotas');

        if ($adapter->driver() === 'mysql') {
            $queryExecutor->execute(
                <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `tenant_id` BIGINT UNSIGNED NOT NULL,
                    `max_requests_per_month` BIGINT UNSIGNED NULL,
                    `max_tokens` BIGINT UNSIGNED NULL,
                    `max_entities` BIGINT UNSIGNED NULL,
                    `max_storage_bytes` BIGINT UNSIGNED NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `pb_tenant_quotas_tenant_id_unique` (`tenant_id`)
                )
                SQL
            );

            return;
        }

        $queryExecutor->execute(
            <<<SQL
            CREATE TABLE IF NOT EXISTS {$table} (
                "id" BIGSERIAL PRIMARY KEY,
                "tenant_id" BIGINT NOT NULL,
                "max_requests_per_month" BIGINT NULL,
                "max_tokens" BIGINT NULL,
                "max_entities" BIGINT NULL,
                "max_storage_bytes" BIGINT NULL,
                "created_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                "updated_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT "pb_tenant_quotas_tenant_id_unique" UNIQUE ("tenant_id")
            )
            SQL
        );
    }

    private function createTenantQuotaUsageTable(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        $table = $adapter->quoteIdentifier('pb_tenant_quota_usage');

        if ($adapter->driver() === 'mysql') {
            $queryExecutor->execute(
                <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `tenant_id` BIGINT UNSIGNED NOT NULL,
                    `metric` VARCHAR(40) NOT NULL,
                    `period_key` VARCHAR(20) NOT NULL,
                    `used_value` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `pb_tenant_quota_usage_unique` (`tenant_id`, `metric`, `period_key`)
                )
                SQL
            );

            return;
        }

        $queryExecutor->execute(
            <<<SQL
            CREATE TABLE IF NOT EXISTS {$table} (
                "id" BIGSERIAL PRIMARY KEY,
                "tenant_id" BIGINT NOT NULL,
                "metric" VARCHAR(40) NOT NULL,
                "period_key" VARCHAR(20) NOT NULL,
                "used_value" BIGINT NOT NULL DEFAULT 0,
                "created_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                "updated_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT "pb_tenant_quota_usage_unique" UNIQUE ("tenant_id", "metric", "period_key")
            )
            SQL
        );
    }

    private function dropColumn(
        QueryExecutorInterface $queryExecutor,
        DatabaseAdapterInterface $adapter,
        string $tableName,
        string $columnName
    ): void {
        $table = $adapter->quoteIdentifier($tableName);

        if ($adapter->driver() === 'mysql') {
            foreach ($adapter->listColumns($tableName) as $column) {
                if ($column->name === $columnName) {
                    $queryExecutor->execute(sprintf('ALTER TABLE %s DROP COLUMN %s', $table, $adapter->quoteIdentifier($columnName)));
                    return;
                }
            }

            return;
        }

        $queryExecutor->execute(sprintf('ALTER TABLE %s DROP COLUMN IF EXISTS %s', $table, $adapter->quoteIdentifier($columnName)));
    }
};
