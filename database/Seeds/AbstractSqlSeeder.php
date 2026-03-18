<?php

declare(strict_types=1);

namespace PachyBase\Database\Seeds;

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Query\QueryExecutorInterface;

abstract class AbstractSqlSeeder implements SeederInterface
{
    public function run(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        foreach ($this->statements($adapter) as $statement) {
            if (is_string($statement)) {
                $queryExecutor->execute($statement);
                continue;
            }

            $queryExecutor->execute($statement['sql'], $statement['bindings'] ?? []);
        }
    }

    /**
     * @return array<int, string|array{sql: string, bindings?: array<int|string, mixed>}>
     */
    abstract protected function statements(DatabaseAdapterInterface $adapter): array;
}
