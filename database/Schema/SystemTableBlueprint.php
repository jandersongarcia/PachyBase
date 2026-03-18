<?php

declare(strict_types=1);

namespace PachyBase\Database\Schema;

use PachyBase\Database\Adapters\DatabaseAdapterInterface;

final class SystemTableBlueprint
{
    public static function primaryKey(DatabaseAdapterInterface $adapter, string $column = 'id'): string
    {
        return match ($adapter->driver()) {
            'mysql' => sprintf('`%s` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', $column),
            default => sprintf('"%s" BIGSERIAL PRIMARY KEY', $column),
        };
    }

    public static function boolean(DatabaseAdapterInterface $adapter, string $column, bool $default = false): string
    {
        return match ($adapter->driver()) {
            'mysql' => sprintf('`%s` TINYINT(1) NOT NULL DEFAULT %d', $column, $default ? 1 : 0),
            default => sprintf('"%s" BOOLEAN NOT NULL DEFAULT %s', $column, $default ? 'TRUE' : 'FALSE'),
        };
    }

    /**
     * @return array<int, string>
     */
    public static function timestamps(DatabaseAdapterInterface $adapter): array
    {
        return match ($adapter->driver()) {
            'mysql' => [
                '`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                '`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            ],
            default => [
                '"created_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP',
                '"updated_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP',
            ],
        };
    }
}
