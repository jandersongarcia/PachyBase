<?php

declare(strict_types=1);

namespace PachyBase\Database\Migrations;

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Query\QueryExecutorInterface;

abstract class AbstractSqlMigration implements MigrationInterface
{
    public function up(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        $this->runStatements($this->upStatements($adapter), $queryExecutor);
    }

    public function down(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
        $this->runStatements($this->downStatements($adapter), $queryExecutor);
    }

    /**
     * @return array<int, string>
     */
    abstract protected function upStatements(DatabaseAdapterInterface $adapter): array;

    /**
     * @return array<int, string>
     */
    abstract protected function downStatements(DatabaseAdapterInterface $adapter): array;

    /**
     * @param array<int, string> $statements
     */
    private function runStatements(array $statements, QueryExecutorInterface $queryExecutor): void
    {
        foreach ($statements as $statement) {
            $queryExecutor->execute($statement);
        }
    }
}
