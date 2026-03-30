<?php

declare(strict_types=1);

namespace PachyBase\Database\Adapters;

use PachyBase\Database\Query\QueryExecutorInterface;
use PachyBase\Database\Schema\DatabaseSchema;
use PachyBase\Database\Schema\TableDefinition;
use PachyBase\Database\Schema\TableSchema;
use PachyBase\Database\Schema\TypeNormalizer;

abstract class AbstractDatabaseAdapter implements DatabaseAdapterInterface
{
    public function __construct(
        protected readonly QueryExecutorInterface $queryExecutor,
        protected readonly string $databaseName,
        protected readonly string $schemaName,
        protected readonly TypeNormalizer $typeNormalizer
    ) {
    }

    public function databaseName(): string
    {
        return $this->databaseName;
    }

    public function schemaName(): string
    {
        return $this->schemaName;
    }

    public function inspectTable(string $table): TableSchema
    {
        $tableDefinition = null;

        foreach ($this->listTables() as $candidate) {
            if ($candidate->name === $table) {
                $tableDefinition = $candidate;
                break;
            }
        }

        if ($tableDefinition === null) {
            $tableDefinition = new TableDefinition($table, $this->schemaName, 'BASE TABLE');
        }

        return $this->buildTableSchema($tableDefinition);
    }

    public function inspectDatabase(): DatabaseSchema
    {
        $tables = [];

        foreach ($this->listTables() as $table) {
            $tables[] = $this->buildTableSchema($table);
        }

        return new DatabaseSchema(
            $this->driver(),
            $this->databaseName,
            $this->schemaName,
            $tables
        );
    }

    public function quoteIdentifier(string $identifier): string
    {
        $quote = $this->identifierQuote();
        $segments = array_map(
            static fn(string $segment): string => $quote . str_replace($quote, $quote . $quote, $segment) . $quote,
            explode('.', $identifier)
        );

        return implode('.', $segments);
    }

    protected function buildTableSchema(TableDefinition $tableDefinition): TableSchema
    {
        return new TableSchema(
            $tableDefinition,
            $this->listColumns($tableDefinition->name),
            $this->listPrimaryKey($tableDefinition->name),
            $this->listIndexes($tableDefinition->name),
            $this->listRelations($tableDefinition->name)
        );
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function groupRowsBy(array $rows, string $key): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $group = (string) ($row[$key] ?? '');
            if ($group === '') {
                continue;
            }

            $grouped[$group][] = $row;
        }

        return $grouped;
    }

    abstract protected function identifierQuote(): string;
}
