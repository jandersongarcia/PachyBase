<?php

declare(strict_types=1);

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Migrations\AbstractSqlMigration;
use PachyBase\Database\Schema\SystemTableBlueprint;

return new class extends AbstractSqlMigration {
    public function version(): string
    {
        return '202603170002';
    }

    public function description(): string
    {
        return 'Create the API tokens table';
    }

    protected function upStatements(DatabaseAdapterInterface $adapter): array
    {
        $table = $adapter->quoteIdentifier('pb_api_tokens');
        $primaryKey = SystemTableBlueprint::primaryKey($adapter);
        $timestamps = implode(",\n                    ", SystemTableBlueprint::timestamps($adapter));

        if ($adapter->driver() === 'mysql') {
            return [
                <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    {$primaryKey},
                    `name` VARCHAR(120) NOT NULL,
                    `token_hash` VARCHAR(255) NOT NULL,
                    `last_used_at` DATETIME NULL,
                    {$timestamps},
                    UNIQUE KEY `pb_api_tokens_token_hash_unique` (`token_hash`)
                )
                SQL,
            ];
        }

        return [
            <<<SQL
            CREATE TABLE IF NOT EXISTS {$table} (
                {$primaryKey},
                "name" VARCHAR(120) NOT NULL,
                "token_hash" VARCHAR(255) NOT NULL,
                "last_used_at" TIMESTAMPTZ NULL,
                {$timestamps},
                CONSTRAINT "pb_api_tokens_token_hash_unique" UNIQUE ("token_hash")
            )
            SQL,
        ];
    }

    protected function downStatements(DatabaseAdapterInterface $adapter): array
    {
        return [
            'DROP TABLE IF EXISTS ' . $adapter->quoteIdentifier('pb_api_tokens'),
        ];
    }
};
