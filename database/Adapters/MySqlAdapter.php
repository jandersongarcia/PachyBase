<?php

declare(strict_types=1);

namespace PachyBase\Database\Adapters;

use PachyBase\Database\Schema\ColumnDefinition;
use PachyBase\Database\Schema\IndexDefinition;
use PachyBase\Database\Schema\PrimaryKeyDefinition;
use PachyBase\Database\Schema\RelationDefinition;
use PachyBase\Database\Schema\TableDefinition;

final class MySqlAdapter extends AbstractDatabaseAdapter
{
    public function driver(): string
    {
        return 'mysql';
    }

    /**
     * @return array<int, TableDefinition>
     */
    public function listTables(): array
    {
        $rows = $this->queryExecutor->select(
            <<<'SQL'
            SELECT
                TABLE_NAME AS table_name,
                TABLE_TYPE AS table_type
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = :schema
            ORDER BY TABLE_NAME
            SQL,
            ['schema' => $this->schemaName]
        );

        return array_map(
            fn(array $row): TableDefinition => new TableDefinition(
                (string) $row['table_name'],
                $this->schemaName,
                (string) $row['table_type']
            ),
            $rows
        );
    }

    /**
     * @return array<int, ColumnDefinition>
     */
    public function listColumns(string $table): array
    {
        $rows = $this->queryExecutor->select(
            <<<'SQL'
            SELECT
                COLUMN_NAME AS column_name,
                DATA_TYPE AS data_type,
                COLUMN_TYPE AS column_type,
                IS_NULLABLE AS is_nullable,
                COLUMN_DEFAULT AS column_default,
                EXTRA AS extra,
                CHARACTER_MAXIMUM_LENGTH AS character_maximum_length,
                NUMERIC_PRECISION AS numeric_precision,
                NUMERIC_SCALE AS numeric_scale
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = :schema
              AND TABLE_NAME = :table
            ORDER BY ORDINAL_POSITION
            SQL,
            ['schema' => $this->schemaName, 'table' => $table]
        );

        return array_map(
            function (array $row): ColumnDefinition {
                $nativeType = strtolower((string) $row['data_type']);
                $columnType = strtolower((string) ($row['column_type'] ?? $nativeType));

                return new ColumnDefinition(
                    (string) $row['column_name'],
                    $nativeType,
                    $this->typeNormalizer->normalize($this->driver(), $nativeType, $columnType),
                    strtoupper((string) $row['is_nullable']) === 'YES',
                    $row['column_default'],
                    str_contains(strtolower((string) $row['extra']), 'auto_increment'),
                    isset($row['character_maximum_length']) ? (int) $row['character_maximum_length'] : null,
                    isset($row['numeric_precision']) ? (int) $row['numeric_precision'] : null,
                    isset($row['numeric_scale']) ? (int) $row['numeric_scale'] : null
                );
            },
            $rows
        );
    }

    public function listPrimaryKey(string $table): ?PrimaryKeyDefinition
    {
        $rows = $this->queryExecutor->select(
            <<<'SQL'
            SELECT
                CONSTRAINT_NAME AS constraint_name,
                COLUMN_NAME AS column_name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = :schema
              AND TABLE_NAME = :table
              AND CONSTRAINT_NAME = 'PRIMARY'
            ORDER BY ORDINAL_POSITION
            SQL,
            ['schema' => $this->schemaName, 'table' => $table]
        );

        if ($rows === []) {
            return null;
        }

        return new PrimaryKeyDefinition(
            (string) $rows[0]['constraint_name'],
            array_map(static fn(array $row): string => (string) $row['column_name'], $rows)
        );
    }

    /**
     * @return array<int, IndexDefinition>
     */
    public function listIndexes(string $table): array
    {
        $rows = $this->queryExecutor->select(
            <<<'SQL'
            SELECT
                INDEX_NAME AS index_name,
                COLUMN_NAME AS column_name,
                NON_UNIQUE AS non_unique
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = :schema
              AND TABLE_NAME = :table
            ORDER BY INDEX_NAME, SEQ_IN_INDEX
            SQL,
            ['schema' => $this->schemaName, 'table' => $table]
        );

        $indexes = [];

        foreach ($this->groupRowsBy($rows, 'index_name') as $indexName => $indexRows) {
            $indexes[] = new IndexDefinition(
                $indexName,
                array_map(static fn(array $row): string => (string) $row['column_name'], $indexRows),
                ((int) $indexRows[0]['non_unique']) === 0,
                $indexName === 'PRIMARY'
            );
        }

        return $indexes;
    }

    /**
     * @return array<int, RelationDefinition>
     */
    public function listRelations(string $table): array
    {
        $rows = $this->queryExecutor->select(
            <<<'SQL'
            SELECT
                kcu.CONSTRAINT_NAME AS constraint_name,
                kcu.COLUMN_NAME AS column_name,
                kcu.REFERENCED_TABLE_NAME AS referenced_table_name,
                kcu.REFERENCED_COLUMN_NAME AS referenced_column_name,
                rc.UPDATE_RULE AS update_rule,
                rc.DELETE_RULE AS delete_rule
            FROM information_schema.KEY_COLUMN_USAGE kcu
            INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
               AND rc.TABLE_NAME = kcu.TABLE_NAME
               AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
            WHERE kcu.TABLE_SCHEMA = :schema
              AND kcu.TABLE_NAME = :table
              AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
            SQL,
            ['schema' => $this->schemaName, 'table' => $table]
        );

        $relations = [];

        foreach ($this->groupRowsBy($rows, 'constraint_name') as $constraintName => $constraintRows) {
            $relations[] = new RelationDefinition(
                $constraintName,
                array_map(static fn(array $row): string => (string) $row['column_name'], $constraintRows),
                (string) $constraintRows[0]['referenced_table_name'],
                array_map(static fn(array $row): string => (string) $row['referenced_column_name'], $constraintRows),
                (string) $constraintRows[0]['update_rule'],
                (string) $constraintRows[0]['delete_rule']
            );
        }

        return $relations;
    }

    protected function identifierQuote(): string
    {
        return '`';
    }
}
