<?php

declare(strict_types=1);

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Migrations\MigrationInterface;
use PachyBase\Database\Query\QueryExecutorInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603250002';
    }

    public function description(): string
    {
        return 'Add project backups, secrets, async jobs, webhooks, deliveries, and file storage';
    }

    public function up(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        $this->createProjectBackupsTable($queryExecutor, $adapter);
        $this->createProjectSecretsTable($queryExecutor, $adapter);
        $this->createAsyncJobsTable($queryExecutor, $adapter);
        $this->createWebhooksTable($queryExecutor, $adapter);
        $this->createWebhookDeliveriesTable($queryExecutor, $adapter);
        $this->createFileObjectsTable($queryExecutor, $adapter);
    }

    public function down(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        foreach ([
            'pb_file_objects',
            'pb_webhook_deliveries',
            'pb_webhooks',
            'pb_async_jobs',
            'pb_project_secrets',
            'pb_project_backups',
        ] as $table) {
            $queryExecutor->execute('DROP TABLE IF EXISTS ' . $adapter->quoteIdentifier($table));
        }
    }

    private function createProjectBackupsTable(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        $table = $adapter->quoteIdentifier('pb_project_backups');

        if ($adapter->driver() === 'mysql') {
            $queryExecutor->execute(
                <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `tenant_id` BIGINT UNSIGNED NOT NULL,
                    `label` VARCHAR(120) NOT NULL,
                    `status` VARCHAR(30) NOT NULL,
                    `file_path` VARCHAR(255) NOT NULL,
                    `backup_json` LONGTEXT NOT NULL,
                    `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    `triggered_by_user_id` BIGINT UNSIGNED NULL,
                    `restored_at` DATETIME NULL,
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
                "tenant_id" BIGINT NOT NULL,
                "label" VARCHAR(120) NOT NULL,
                "status" VARCHAR(30) NOT NULL,
                "file_path" VARCHAR(255) NOT NULL,
                "backup_json" TEXT NOT NULL,
                "size_bytes" BIGINT NOT NULL DEFAULT 0,
                "triggered_by_user_id" BIGINT NULL,
                "restored_at" TIMESTAMPTZ NULL,
                "created_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                "updated_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL
        );
    }

    private function createProjectSecretsTable(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        $table = $adapter->quoteIdentifier('pb_project_secrets');

        if ($adapter->driver() === 'mysql') {
            $queryExecutor->execute(
                <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `tenant_id` BIGINT UNSIGNED NOT NULL,
                    `secret_key` VARCHAR(120) NOT NULL,
                    `secret_value_encrypted` LONGTEXT NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `pb_project_secrets_tenant_key_unique` (`tenant_id`, `secret_key`)
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
                "secret_key" VARCHAR(120) NOT NULL,
                "secret_value_encrypted" TEXT NOT NULL,
                "created_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                "updated_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT "pb_project_secrets_tenant_key_unique" UNIQUE ("tenant_id", "secret_key")
            )
            SQL
        );
    }

    private function createAsyncJobsTable(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        $table = $adapter->quoteIdentifier('pb_async_jobs');

        if ($adapter->driver() === 'mysql') {
            $queryExecutor->execute(
                <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `tenant_id` BIGINT UNSIGNED NOT NULL,
                    `type` VARCHAR(80) NOT NULL,
                    `status` VARCHAR(30) NOT NULL,
                    `payload_json` LONGTEXT NOT NULL,
                    `result_json` LONGTEXT NULL,
                    `available_at` DATETIME NOT NULL,
                    `started_at` DATETIME NULL,
                    `finished_at` DATETIME NULL,
                    `attempts` INT NOT NULL DEFAULT 0,
                    `max_attempts` INT NOT NULL DEFAULT 3,
                    `last_error` VARCHAR(255) NULL,
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
                "tenant_id" BIGINT NOT NULL,
                "type" VARCHAR(80) NOT NULL,
                "status" VARCHAR(30) NOT NULL,
                "payload_json" TEXT NOT NULL,
                "result_json" TEXT NULL,
                "available_at" TIMESTAMPTZ NOT NULL,
                "started_at" TIMESTAMPTZ NULL,
                "finished_at" TIMESTAMPTZ NULL,
                "attempts" INT NOT NULL DEFAULT 0,
                "max_attempts" INT NOT NULL DEFAULT 3,
                "last_error" VARCHAR(255) NULL,
                "created_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                "updated_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL
        );
    }

    private function createWebhooksTable(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        $table = $adapter->quoteIdentifier('pb_webhooks');

        if ($adapter->driver() === 'mysql') {
            $queryExecutor->execute(
                <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `tenant_id` BIGINT UNSIGNED NOT NULL,
                    `name` VARCHAR(120) NOT NULL,
                    `event_name` VARCHAR(120) NOT NULL,
                    `target_url` VARCHAR(255) NOT NULL,
                    `signing_secret_encrypted` LONGTEXT NOT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
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
                "tenant_id" BIGINT NOT NULL,
                "name" VARCHAR(120) NOT NULL,
                "event_name" VARCHAR(120) NOT NULL,
                "target_url" VARCHAR(255) NOT NULL,
                "signing_secret_encrypted" TEXT NOT NULL,
                "is_active" BOOLEAN NOT NULL DEFAULT TRUE,
                "created_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                "updated_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL
        );
    }

    private function createWebhookDeliveriesTable(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        $table = $adapter->quoteIdentifier('pb_webhook_deliveries');

        if ($adapter->driver() === 'mysql') {
            $queryExecutor->execute(
                <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `tenant_id` BIGINT UNSIGNED NOT NULL,
                    `webhook_id` BIGINT UNSIGNED NOT NULL,
                    `event_name` VARCHAR(120) NOT NULL,
                    `status` VARCHAR(30) NOT NULL,
                    `request_payload` LONGTEXT NOT NULL,
                    `response_status_code` INT NULL,
                    `response_body` LONGTEXT NULL,
                    `attempted_at` DATETIME NOT NULL,
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
                "tenant_id" BIGINT NOT NULL,
                "webhook_id" BIGINT NOT NULL,
                "event_name" VARCHAR(120) NOT NULL,
                "status" VARCHAR(30) NOT NULL,
                "request_payload" TEXT NOT NULL,
                "response_status_code" INT NULL,
                "response_body" TEXT NULL,
                "attempted_at" TIMESTAMPTZ NOT NULL,
                "created_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                "updated_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL
        );
    }

    private function createFileObjectsTable(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        $table = $adapter->quoteIdentifier('pb_file_objects');

        if ($adapter->driver() === 'mysql') {
            $queryExecutor->execute(
                <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `tenant_id` BIGINT UNSIGNED NOT NULL,
                    `disk` VARCHAR(40) NOT NULL,
                    `object_key` VARCHAR(255) NOT NULL,
                    `original_name` VARCHAR(255) NOT NULL,
                    `content_type` VARCHAR(120) NOT NULL,
                    `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    `checksum_sha256` VARCHAR(64) NOT NULL,
                    `relative_path` VARCHAR(255) NOT NULL,
                    `metadata_json` LONGTEXT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `pb_file_objects_tenant_object_key_unique` (`tenant_id`, `object_key`)
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
                "disk" VARCHAR(40) NOT NULL,
                "object_key" VARCHAR(255) NOT NULL,
                "original_name" VARCHAR(255) NOT NULL,
                "content_type" VARCHAR(120) NOT NULL,
                "size_bytes" BIGINT NOT NULL DEFAULT 0,
                "checksum_sha256" VARCHAR(64) NOT NULL,
                "relative_path" VARCHAR(255) NOT NULL,
                "metadata_json" TEXT NULL,
                "created_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                "updated_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT "pb_file_objects_tenant_object_key_unique" UNIQUE ("tenant_id", "object_key")
            )
            SQL
        );
    }
};
