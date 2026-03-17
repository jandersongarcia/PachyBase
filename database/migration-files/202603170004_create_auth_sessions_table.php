<?php

declare(strict_types=1);

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Migrations\AbstractSqlMigration;
use PachyBase\Database\Schema\SystemTableBlueprint;

return new class extends AbstractSqlMigration {
    public function version(): string
    {
        return '202603170004';
    }

    public function description(): string
    {
        return 'Create the auth sessions table';
    }

    protected function upStatements(DatabaseAdapterInterface $adapter): array
    {
        $table = $adapter->quoteIdentifier('pb_auth_sessions');
        $primaryKey = SystemTableBlueprint::primaryKey($adapter);
        $timestamps = implode(",\n                    ", SystemTableBlueprint::timestamps($adapter));

        if ($adapter->driver() === 'mysql') {
            return [
                <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    {$primaryKey},
                    `user_id` BIGINT UNSIGNED NOT NULL,
                    `refresh_token_hash` VARCHAR(255) NOT NULL,
                    `scopes` VARCHAR(2000) NOT NULL,
                    `expires_at` DATETIME NOT NULL,
                    `revoked_at` DATETIME NULL,
                    `last_used_at` DATETIME NULL,
                    {$timestamps},
                    UNIQUE KEY `pb_auth_sessions_refresh_token_hash_unique` (`refresh_token_hash`)
                )
                SQL,
            ];
        }

        return [
            <<<SQL
            CREATE TABLE IF NOT EXISTS {$table} (
                {$primaryKey},
                "user_id" BIGINT NOT NULL,
                "refresh_token_hash" VARCHAR(255) NOT NULL,
                "scopes" VARCHAR(2000) NOT NULL,
                "expires_at" TIMESTAMPTZ NOT NULL,
                "revoked_at" TIMESTAMPTZ NULL,
                "last_used_at" TIMESTAMPTZ NULL,
                {$timestamps},
                CONSTRAINT "pb_auth_sessions_refresh_token_hash_unique" UNIQUE ("refresh_token_hash")
            )
            SQL,
        ];
    }

    protected function downStatements(DatabaseAdapterInterface $adapter): array
    {
        return [
            'DROP TABLE IF EXISTS ' . $adapter->quoteIdentifier('pb_auth_sessions'),
        ];
    }
};
