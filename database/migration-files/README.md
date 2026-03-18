# Database Migration Files

Place PachyBase migration files in this directory.

Each migration file must:

- use the `.php` extension;
- return an instance of `PachyBase\Database\Migrations\MigrationInterface`;
- expose a unique `version()` string;
- implement `up()` and `down()` or extend `AbstractSqlMigration`.

Example:

```php
<?php

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Migrations\AbstractSqlMigration;

return new class extends AbstractSqlMigration {
    public function version(): string
    {
        return '202603170001';
    }

    public function description(): string
    {
        return 'Create users table';
    }

    protected function upStatements(DatabaseAdapterInterface $adapter): array
    {
        $table = $adapter->quoteIdentifier('users');

        return match ($adapter->driver()) {
            'mysql' => [
                "CREATE TABLE {$table} (`id` BIGINT AUTO_INCREMENT PRIMARY KEY, `email` VARCHAR(190) NOT NULL)"
            ],
            default => [
                "CREATE TABLE {$table} (\"id\" BIGSERIAL PRIMARY KEY, \"email\" VARCHAR(190) NOT NULL)"
            ],
        };
    }

    protected function downStatements(DatabaseAdapterInterface $adapter): array
    {
        return [
            'DROP TABLE IF EXISTS ' . $adapter->quoteIdentifier('users'),
        ];
    }
};
```
