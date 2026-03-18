<?php

declare(strict_types=1);

use PachyBase\Config\AuthConfig;
use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Seeds\AbstractSqlSeeder;
use PachyBase\Utils\Json;

return new class extends AbstractSqlSeeder {
    public function name(): string
    {
        return '202603170002_seed_bootstrap_admin_user';
    }

    public function description(): string
    {
        return 'Seed the bootstrap admin user for local auth flows';
    }

    protected function statements(DatabaseAdapterInterface $adapter): array
    {
        $table = $adapter->quoteIdentifier('pb_users');
        $email = AuthConfig::bootstrapAdminEmail();

        return [
            [
                'sql' => "DELETE FROM {$table} WHERE email = :email",
                'bindings' => [
                    'email' => $email,
                ],
            ],
            [
                'sql' => "INSERT INTO {$table} (name, email, password_hash, role, scopes, is_active) VALUES (:name, :email, :password_hash, :role, :scopes, :is_active)",
                'bindings' => [
                    'name' => AuthConfig::bootstrapAdminName(),
                    'email' => $email,
                    'password_hash' => password_hash(AuthConfig::bootstrapAdminPassword(), PASSWORD_DEFAULT),
                    'role' => 'admin',
                    'scopes' => Json::encode(['*', 'auth:manage']),
                    'is_active' => true,
                ],
            ],
        ];
    }
};
