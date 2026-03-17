<?php

declare(strict_types=1);

namespace PachyBase\Database\Seeds;

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Query\QueryExecutorInterface;

final class SeedRepository
{
    private const TABLE = 'pachybase_seeders';

    public function __construct(
        private readonly QueryExecutorInterface $queryExecutor,
        private readonly DatabaseAdapterInterface $adapter
    ) {
    }

    public function ensureTable(): void
    {
        $this->queryExecutor->execute($this->createTableStatement());
    }

    /**
     * @return array<int, array{name: string, description: string, executed_at: string|null}>
     */
    public function executed(): array
    {
        $rows = $this->queryExecutor->select(
            sprintf(
                'SELECT name, description, executed_at FROM %s ORDER BY id ASC',
                $this->tableName()
            )
        );

        return array_map(
            static fn(array $row): array => [
                'name' => (string) $row['name'],
                'description' => (string) $row['description'],
                'executed_at' => isset($row['executed_at']) ? (string) $row['executed_at'] : null,
            ],
            $rows
        );
    }

    /**
     * @return array<int, string>
     */
    public function executedNames(): array
    {
        return array_map(
            static fn(array $row): string => (string) $row['name'],
            $this->queryExecutor->select(
                sprintf('SELECT name FROM %s ORDER BY id ASC', $this->tableName())
            )
        );
    }

    public function record(SeederInterface $seeder): void
    {
        $this->queryExecutor->execute(
            sprintf('DELETE FROM %s WHERE name = :name', $this->tableName()),
            ['name' => $seeder->name()]
        );

        $this->queryExecutor->execute(
            sprintf(
                'INSERT INTO %s (name, description, executed_at) VALUES (:name, :description, CURRENT_TIMESTAMP)',
                $this->tableName()
            ),
            [
                'name' => $seeder->name(),
                'description' => $seeder->description(),
            ]
        );
    }

    private function tableName(): string
    {
        return $this->adapter->quoteIdentifier(self::TABLE);
    }

    private function createTableStatement(): string
    {
        $table = $this->tableName();

        return match ($this->adapter->driver()) {
            'mysql' => <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name` VARCHAR(190) NOT NULL UNIQUE,
                    `description` VARCHAR(255) NOT NULL,
                    `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
                SQL,
            'pgsql' => <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    "id" BIGSERIAL PRIMARY KEY,
                    "name" VARCHAR(190) NOT NULL UNIQUE,
                    "description" VARCHAR(255) NOT NULL,
                    "executed_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
                SQL,
            default => <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    id INTEGER PRIMARY KEY,
                    name VARCHAR(190) NOT NULL UNIQUE,
                    description VARCHAR(255) NOT NULL,
                    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
                SQL,
        };
    }
}
