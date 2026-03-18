<?php

declare(strict_types=1);

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Migrations\AbstractSqlMigration;

return new class extends AbstractSqlMigration {
    public function version(): string
    {
        return '202603170005';
    }

    public function description(): string
    {
        return 'Expand the API tokens table for auth metadata';
    }

    protected function upStatements(DatabaseAdapterInterface $adapter): array
    {
        $table = $adapter->quoteIdentifier('pb_api_tokens');

        if ($adapter->driver() === 'mysql') {
            return [
                <<<SQL
                ALTER TABLE {$table}
                    ADD COLUMN IF NOT EXISTS `user_id` BIGINT UNSIGNED NULL,
                    ADD COLUMN IF NOT EXISTS `token_prefix` VARCHAR(32) NULL,
                    ADD COLUMN IF NOT EXISTS `scopes` VARCHAR(2000) NOT NULL DEFAULT '[]',
                    ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    ADD COLUMN IF NOT EXISTS `expires_at` DATETIME NULL,
                    ADD COLUMN IF NOT EXISTS `revoked_at` DATETIME NULL
                SQL,
            ];
        }

        return [
            <<<SQL
            ALTER TABLE {$table}
                ADD COLUMN IF NOT EXISTS "user_id" BIGINT NULL,
                ADD COLUMN IF NOT EXISTS "token_prefix" VARCHAR(32) NULL,
                ADD COLUMN IF NOT EXISTS "scopes" VARCHAR(2000) NOT NULL DEFAULT '[]',
                ADD COLUMN IF NOT EXISTS "is_active" BOOLEAN NOT NULL DEFAULT TRUE,
                ADD COLUMN IF NOT EXISTS "expires_at" TIMESTAMPTZ NULL,
                ADD COLUMN IF NOT EXISTS "revoked_at" TIMESTAMPTZ NULL
            SQL,
        ];
    }

    protected function downStatements(DatabaseAdapterInterface $adapter): array
    {
        $table = $adapter->quoteIdentifier('pb_api_tokens');

        if ($adapter->driver() === 'mysql') {
            return [
                <<<SQL
                ALTER TABLE {$table}
                    DROP COLUMN IF EXISTS `user_id`,
                    DROP COLUMN IF EXISTS `token_prefix`,
                    DROP COLUMN IF EXISTS `scopes`,
                    DROP COLUMN IF EXISTS `is_active`,
                    DROP COLUMN IF EXISTS `expires_at`,
                    DROP COLUMN IF EXISTS `revoked_at`
                SQL,
            ];
        }

        return [
            <<<SQL
            ALTER TABLE {$table}
                DROP COLUMN IF EXISTS "user_id",
                DROP COLUMN IF EXISTS "token_prefix",
                DROP COLUMN IF EXISTS "scopes",
                DROP COLUMN IF EXISTS "is_active",
                DROP COLUMN IF EXISTS "expires_at",
                DROP COLUMN IF EXISTS "revoked_at"
            SQL,
        ];
    }
};
