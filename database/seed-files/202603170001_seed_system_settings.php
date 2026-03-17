<?php

declare(strict_types=1);

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Seeds\AbstractSqlSeeder;

return new class extends AbstractSqlSeeder {
    public function name(): string
    {
        return '202603170001_seed_system_settings';
    }

    public function description(): string
    {
        return 'Seed the default PachyBase system settings';
    }

    protected function statements(DatabaseAdapterInterface $adapter): array
    {
        $table = $adapter->quoteIdentifier('pb_system_settings');

        return [
            [
                'sql' => "DELETE FROM {$table} WHERE setting_key IN (:key_one, :key_two, :key_three, :key_four)",
                'bindings' => [
                    'key_one' => 'app.name',
                    'key_two' => 'api.contract_version',
                    'key_three' => 'auth.guard',
                    'key_four' => 'database.default_driver',
                ],
            ],
            [
                'sql' => "INSERT INTO {$table} (setting_key, setting_value, value_type, is_public) VALUES (:setting_key, :setting_value, :value_type, :is_public)",
                'bindings' => [
                    'setting_key' => 'app.name',
                    'setting_value' => 'PachyBase',
                    'value_type' => 'string',
                    'is_public' => true,
                ],
            ],
            [
                'sql' => "INSERT INTO {$table} (setting_key, setting_value, value_type, is_public) VALUES (:setting_key, :setting_value, :value_type, :is_public)",
                'bindings' => [
                    'setting_key' => 'api.contract_version',
                    'setting_value' => '1.0',
                    'value_type' => 'string',
                    'is_public' => true,
                ],
            ],
            [
                'sql' => "INSERT INTO {$table} (setting_key, setting_value, value_type, is_public) VALUES (:setting_key, :setting_value, :value_type, :is_public)",
                'bindings' => [
                    'setting_key' => 'auth.guard',
                    'setting_value' => 'bearer',
                    'value_type' => 'string',
                    'is_public' => false,
                ],
            ],
            [
                'sql' => "INSERT INTO {$table} (setting_key, setting_value, value_type, is_public) VALUES (:setting_key, :setting_value, :value_type, :is_public)",
                'bindings' => [
                    'setting_key' => 'database.default_driver',
                    'setting_value' => $adapter->driver(),
                    'value_type' => 'string',
                    'is_public' => false,
                ],
            ],
        ];
    }
};
