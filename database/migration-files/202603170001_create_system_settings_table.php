<?php

declare(strict_types=1);

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Migrations\AbstractSqlMigration;
use PachyBase\Database\Schema\SystemTableBlueprint;

return new class extends AbstractSqlMigration {
    public function version(): string
    {
        return '202603170001';
    }

    public function description(): string
    {
        return 'Create the system settings table';
    }

    protected function upStatements(DatabaseAdapterInterface $adapter): array
    {
        $table = $adapter->quoteIdentifier('pb_system_settings');
        $primaryKey = SystemTableBlueprint::primaryKey($adapter);
        $isPublic = SystemTableBlueprint::boolean($adapter, 'is_public', false);
        $timestamps = implode(",\n                    ", SystemTableBlueprint::timestamps($adapter));

        if ($adapter->driver() === 'mysql') {
            return [
                <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    {$primaryKey},
                    `setting_key` VARCHAR(120) NOT NULL,
                    `setting_value` TEXT NULL,
                    `value_type` VARCHAR(40) NOT NULL DEFAULT 'string',
                    {$isPublic},
                    {$timestamps},
                    UNIQUE KEY `pb_system_settings_setting_key_unique` (`setting_key`)
                )
                SQL,
            ];
        }

        return [
            <<<SQL
            CREATE TABLE IF NOT EXISTS {$table} (
                {$primaryKey},
                "setting_key" VARCHAR(120) NOT NULL,
                "setting_value" TEXT NULL,
                "value_type" VARCHAR(40) NOT NULL DEFAULT 'string',
                {$isPublic},
                {$timestamps},
                CONSTRAINT "pb_system_settings_setting_key_unique" UNIQUE ("setting_key")
            )
            SQL,
        ];
    }

    protected function downStatements(DatabaseAdapterInterface $adapter): array
    {
        return [
            'DROP TABLE IF EXISTS ' . $adapter->quoteIdentifier('pb_system_settings'),
        ];
    }
};
