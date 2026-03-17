<?php

declare(strict_types=1);

namespace PachyBase\Database\Migrations;

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Query\QueryExecutorInterface;

final class MigrationRunner
{
    private readonly MigrationRepository $repository;

    public function __construct(
        private readonly QueryExecutorInterface $queryExecutor,
        private readonly DatabaseAdapterInterface $adapter,
        ?MigrationRepository $repository = null
    ) {
        $this->repository = $repository ?? new MigrationRepository($queryExecutor, $adapter);
    }

    /**
     * @param iterable<int, MigrationInterface> $migrations
     * @return array<int, array{version: string, description: string, applied: bool, batch: int|null}>
     */
    public function status(iterable $migrations): array
    {
        $this->repository->ensureTable();
        $applied = [];

        foreach ($this->repository->applied() as $record) {
            $applied[$record['version']] = $record;
        }

        $status = [];

        foreach ($migrations as $migration) {
            $record = $applied[$migration->version()] ?? null;
            $status[] = [
                'version' => $migration->version(),
                'description' => $migration->description(),
                'applied' => $record !== null,
                'batch' => $record['batch'] ?? null,
            ];
        }

        return $status;
    }

    /**
     * @param iterable<int, MigrationInterface> $migrations
     * @return array{
     *     batch: int|null,
     *     applied_count: int,
     *     skipped_count: int,
     *     applied_versions: array<int, string>
     * }
     */
    public function migrate(iterable $migrations): array
    {
        $this->repository->ensureTable();
        $pending = [];
        $appliedVersions = array_fill_keys($this->repository->appliedVersions(), true);

        foreach ($migrations as $migration) {
            if (isset($appliedVersions[$migration->version()])) {
                continue;
            }

            $pending[] = $migration;
        }

        if ($pending === []) {
            return [
                'batch' => null,
                'applied_count' => 0,
                'skipped_count' => count($appliedVersions),
                'applied_versions' => [],
            ];
        }

        $batch = $this->repository->nextBatchNumber();
        $executed = [];

        $this->queryExecutor->transaction(function () use ($pending, $batch, &$executed): void {
            foreach ($pending as $migration) {
                $migration->up($this->queryExecutor, $this->adapter);
                $this->repository->log($migration, $batch);
                $executed[] = $migration->version();
            }
        });

        return [
            'batch' => $batch,
            'applied_count' => count($executed),
            'skipped_count' => count($appliedVersions),
            'applied_versions' => $executed,
        ];
    }

    /**
     * @param iterable<int, MigrationInterface> $migrations
     * @return array{
     *     rolled_back_count: int,
     *     rolled_back_versions: array<int, string>
     * }
     */
    public function rollback(iterable $migrations, int $steps = 1): array
    {
        $this->repository->ensureTable();
        $loaded = [];

        foreach ($migrations as $migration) {
            $loaded[$migration->version()] = $migration;
        }

        $records = $this->repository->latestBatches($steps);
        $rolledBack = [];

        $this->queryExecutor->transaction(function () use ($records, $loaded, &$rolledBack): void {
            foreach ($records as $record) {
                $migration = $loaded[$record['version']] ?? null;
                if ($migration === null) {
                    continue;
                }

                $migration->down($this->queryExecutor, $this->adapter);
                $this->repository->forget($migration->version());
                $rolledBack[] = $migration->version();
            }
        });

        return [
            'rolled_back_count' => count($rolledBack),
            'rolled_back_versions' => $rolledBack,
        ];
    }
}
