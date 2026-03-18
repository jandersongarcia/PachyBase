<?php

declare(strict_types=1);

namespace PachyBase\Database\Migrations;

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Query\QueryExecutorInterface;

final class MigrationRepository
{
    private const TABLE = 'pachybase_migrations';

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
     * @return array<int, array{version: string, description: string, batch: int, applied_at: string|null}>
     */
    public function applied(): array
    {
        $rows = $this->queryExecutor->select(
            sprintf(
                'SELECT version, description, batch, applied_at FROM %s ORDER BY batch ASC, id ASC',
                $this->tableName()
            )
        );

        return array_map(
            static fn(array $row): array => [
                'version' => (string) $row['version'],
                'description' => (string) $row['description'],
                'batch' => (int) $row['batch'],
                'applied_at' => isset($row['applied_at']) ? (string) $row['applied_at'] : null,
            ],
            $rows
        );
    }

    /**
     * @return array<int, string>
     */
    public function appliedVersions(): array
    {
        return array_map(
            static fn(array $row): string => (string) $row['version'],
            $this->queryExecutor->select(
                sprintf('SELECT version FROM %s ORDER BY id ASC', $this->tableName())
            )
        );
    }

    public function nextBatchNumber(): int
    {
        $batch = $this->queryExecutor->scalar(
            sprintf('SELECT MAX(batch) AS max_batch FROM %s', $this->tableName())
        );

        return ((int) $batch) + 1;
    }

    public function log(MigrationInterface $migration, int $batch): void
    {
        $this->queryExecutor->execute(
            sprintf(
                'INSERT INTO %s (version, description, batch, applied_at) VALUES (:version, :description, :batch, CURRENT_TIMESTAMP)',
                $this->tableName()
            ),
            [
                'version' => $migration->version(),
                'description' => $migration->description(),
                'batch' => $batch,
            ]
        );
    }

    public function forget(string $version): void
    {
        $this->queryExecutor->execute(
            sprintf('DELETE FROM %s WHERE version = :version', $this->tableName()),
            ['version' => $version]
        );
    }

    /**
     * @return array<int, array{version: string, description: string, batch: int}>
     */
    public function latestBatches(int $steps = 1): array
    {
        $steps = max(1, $steps);

        $batches = $this->queryExecutor->select(
            sprintf(
                'SELECT DISTINCT batch FROM %s ORDER BY batch DESC LIMIT %d',
                $this->tableName(),
                $steps
            )
        );

        if ($batches === []) {
            return [];
        }

        $batchNumbers = array_map(static fn(array $row): int => (int) $row['batch'], $batches);
        $placeholders = [];
        $bindings = [];

        foreach ($batchNumbers as $index => $batchNumber) {
            $placeholder = 'batch_' . $index;
            $placeholders[] = ':' . $placeholder;
            $bindings[$placeholder] = $batchNumber;
        }

        $rows = $this->queryExecutor->select(
            sprintf(
                'SELECT version, description, batch FROM %s WHERE batch IN (%s) ORDER BY batch DESC, id DESC',
                $this->tableName(),
                implode(', ', $placeholders)
            ),
            $bindings
        );

        return array_map(
            static fn(array $row): array => [
                'version' => (string) $row['version'],
                'description' => (string) $row['description'],
                'batch' => (int) $row['batch'],
            ],
            $rows
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
                    `version` VARCHAR(190) NOT NULL UNIQUE,
                    `description` VARCHAR(255) NOT NULL,
                    `batch` INT NOT NULL,
                    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
                SQL,
            'pgsql' => <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    "id" BIGSERIAL PRIMARY KEY,
                    "version" VARCHAR(190) NOT NULL UNIQUE,
                    "description" VARCHAR(255) NOT NULL,
                    "batch" INTEGER NOT NULL,
                    "applied_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
                SQL,
            default => <<<SQL
                CREATE TABLE IF NOT EXISTS {$table} (
                    id INTEGER PRIMARY KEY,
                    version VARCHAR(190) NOT NULL UNIQUE,
                    description VARCHAR(255) NOT NULL,
                    batch INTEGER NOT NULL,
                    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
                SQL,
        };
    }
}
