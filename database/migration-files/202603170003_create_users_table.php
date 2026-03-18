<?php

declare(strict_types=1);

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Migrations\AbstractSqlMigration;
use PachyBase\Database\Schema\SystemTableBlueprint;

return new class extends AbstractSqlMigration {
    public function version(): string
    {
        return '202603170003';
    }

    public function description(): string
    {
        return 'Create the users table';
    }

    protected function upStatements(DatabaseAdapterInterface $adapter): array
    {
        $table = $adapter->quoteIdentifier('pb_users');
        $primaryKey = SystemTableBlueprint::primaryKey($adapter);
        $isActive = SystemTableBlueprint::boolean($adapter, 'is_active', true);
        $timestamps = implode(",\n                    ", SystemTableBlueprint::timestamps($adapter));

        if ($adapter->driver() === 'mysql') {
            return [
                <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    {$primaryKey},
                    `name` VARCHAR(120) NOT NULL,
                    `email` VARCHAR(190) NOT NULL,
                    `password_hash` VARCHAR(255) NOT NULL,
                    `role` VARCHAR(40) NOT NULL DEFAULT 'admin',
                    `scopes` VARCHAR(2000) NOT NULL,
                    {$isActive},
                    `last_login_at` DATETIME NULL,
                    {$timestamps},
                    UNIQUE KEY `pb_users_email_unique` (`email`)
                )
                SQL,
            ];
        }

        return [
            <<<SQL
            CREATE TABLE IF NOT EXISTS {$table} (
                {$primaryKey},
                "name" VARCHAR(120) NOT NULL,
                "email" VARCHAR(190) NOT NULL,
                "password_hash" VARCHAR(255) NOT NULL,
                "role" VARCHAR(40) NOT NULL DEFAULT 'admin',
                "scopes" VARCHAR(2000) NOT NULL,
                {$isActive},
                "last_login_at" TIMESTAMPTZ NULL,
                {$timestamps},
                CONSTRAINT "pb_users_email_unique" UNIQUE ("email")
            )
            SQL,
        ];
    }

    protected function downStatements(DatabaseAdapterInterface $adapter): array
    {
        return [
            'DROP TABLE IF EXISTS ' . $adapter->quoteIdentifier('pb_users'),
        ];
    }
};
