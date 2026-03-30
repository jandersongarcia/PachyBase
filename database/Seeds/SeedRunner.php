<?php

declare(strict_types=1);

namespace PachyBase\Database\Seeds;

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Query\QueryExecutorInterface;

final class SeedRunner
{
    private readonly SeedRepository $repository;

    public function __construct(
        private readonly QueryExecutorInterface $queryExecutor,
        private readonly DatabaseAdapterInterface $adapter,
        ?SeedRepository $repository = null
    ) {
        $this->repository = $repository ?? new SeedRepository($queryExecutor, $adapter);
    }

    /**
     * @param iterable<int, SeederInterface> $seeders
     * @return array<int, array{name: string, description: string, executed: bool, executed_at: string|null}>
     */
    public function status(iterable $seeders): array
    {
        $this->repository->ensureTable();
        $executed = [];

        foreach ($this->repository->executed() as $record) {
            $executed[$record['name']] = $record;
        }

        $status = [];

        foreach ($seeders as $seeder) {
            $record = $executed[$seeder->name()] ?? null;
            $status[] = [
                'name' => $seeder->name(),
                'description' => $seeder->description(),
                'executed' => $record !== null,
                'executed_at' => $record['executed_at'] ?? null,
            ];
        }

        return $status;
    }

    /**
     * @param iterable<int, SeederInterface> $seeders
     * @return array{
     *     force: bool,
     *     executed_count: int,
     *     skipped_count: int,
     *     executed_names: array<int, string>
     * }
     */
    public function run(iterable $seeders, bool $force = false): array
    {
        $this->repository->ensureTable();
        $executedNames = array_fill_keys($this->repository->executedNames(), true);
        $executed = [];
        $skipped = 0;

        $this->queryExecutor->transaction(function () use ($seeders, $force, &$executedNames, &$executed, &$skipped): void {
            foreach ($seeders as $seeder) {
                $wasExecuted = isset($executedNames[$seeder->name()]);

                if ($wasExecuted && !$force) {
                    $skipped++;
                    continue;
                }

                $seeder->run($this->queryExecutor, $this->adapter);
                $this->repository->record($seeder);
                $executed[] = $seeder->name();
                $executedNames[$seeder->name()] = true;
            }
        });

        return [
            'force' => $force,
            'executed_count' => count($executed),
            'skipped_count' => $skipped,
            'executed_names' => $executed,
        ];
    }
}
