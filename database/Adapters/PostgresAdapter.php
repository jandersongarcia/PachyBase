<?php

declare(strict_types=1);

namespace PachyBase\Database\Adapters;

use PachyBase\Database\Schema\ColumnDefinition;
use PachyBase\Database\Schema\IndexDefinition;
use PachyBase\Database\Schema\PrimaryKeyDefinition;
use PachyBase\Database\Schema\RelationDefinition;
use PachyBase\Database\Schema\TableDefinition;

final class PostgresAdapter extends AbstractDatabaseAdapter
{
    public function driver(): string
    {
        return 'pgsql';
    }

    /**
     * @return array<int, TableDefinition>
     */
    public function listTables(): array
    {
        $rows = $this->queryExecutor->select(
            <<<'SQL'
            SELECT
                table_name,
                table_type
            FROM information_schema.tables
            WHERE table_schema = :schema
            ORDER BY table_name
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
                column_name,
                data_type,
                udt_name,
                is_nullable,
                column_default,
                character_maximum_length,
                numeric_precision,
                numeric_scale,
                is_identity
            FROM information_schema.columns
            WHERE table_schema = :schema
              AND table_name = :table
            ORDER BY ordinal_position
            SQL,
            ['schema' => $this->schemaName, 'table' => $table]
        );

        return array_map(
            function (array $row): ColumnDefinition {
                $nativeType = strtolower((string) ($row['udt_name'] ?? $row['data_type']));
                $fullType = strtolower((string) ($row['data_type'] ?? $nativeType));
                $columnDefault = $row['column_default'];
                $isIdentity = strtoupper((string) ($row['is_identity'] ?? 'NO')) === 'YES';
                $isSerial = is_string($columnDefault) && str_contains(strtolower($columnDefault), 'nextval(');

                return new ColumnDefinition(
                    (string) $row['column_name'],
                    $nativeType,
                    $this->typeNormalizer->normalize($this->driver(), $nativeType, $fullType),
                    strtoupper((string) $row['is_nullable']) === 'YES',
                    $columnDefault,
                    $isIdentity || $isSerial,
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
                tc.constraint_name,
                kcu.column_name
            FROM information_schema.table_constraints tc
            INNER JOIN information_schema.key_column_usage kcu
                ON kcu.constraint_name = tc.constraint_name
               AND kcu.table_schema = tc.table_schema
               AND kcu.table_name = tc.table_name
            WHERE tc.table_schema = :schema
              AND tc.table_name = :table
              AND tc.constraint_type = 'PRIMARY KEY'
            ORDER BY kcu.ordinal_position
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
                idx.index_name,
                idx.is_unique,
                idx.is_primary,
                idx.column_name
            FROM (
                SELECT
                    i.relname AS index_name,
                    ix.indisunique AS is_unique,
                    ix.indisprimary AS is_primary,
                    a.attname AS column_name,
                    ord.ordinality AS position
                FROM pg_class t
                INNER JOIN pg_namespace ns ON ns.oid = t.relnamespace
                INNER JOIN pg_index ix ON ix.indrelid = t.oid
                INNER JOIN pg_class i ON i.oid = ix.indexrelid
                INNER JOIN LATERAL unnest(ix.indkey) WITH ORDINALITY AS ord(attnum, ordinality) ON true
                INNER JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ord.attnum
                WHERE ns.nspname = :schema
                  AND t.relname = :table
            ) idx
            ORDER BY idx.index_name, idx.position
            SQL,
            ['schema' => $this->schemaName, 'table' => $table]
        );

        $indexes = [];

        foreach ($this->groupRowsBy($rows, 'index_name') as $indexName => $indexRows) {
            $indexes[] = new IndexDefinition(
                $indexName,
                array_map(static fn(array $row): string => (string) $row['column_name'], $indexRows),
                $this->toBoolean($indexRows[0]['is_unique']),
                $this->toBoolean($indexRows[0]['is_primary'])
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
                tc.constraint_name,
                kcu.column_name,
                ccu.table_name AS referenced_table_name,
                ccu.column_name AS referenced_column_name,
                rc.update_rule,
                rc.delete_rule
            FROM information_schema.table_constraints tc
            INNER JOIN information_schema.key_column_usage kcu
                ON kcu.constraint_name = tc.constraint_name
               AND kcu.table_schema = tc.table_schema
               AND kcu.table_name = tc.table_name
            INNER JOIN information_schema.constraint_column_usage ccu
                ON ccu.constraint_name = tc.constraint_name
               AND ccu.constraint_schema = tc.table_schema
            INNER JOIN information_schema.referential_constraints rc
                ON rc.constraint_name = tc.constraint_name
               AND rc.constraint_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_schema = :schema
              AND tc.table_name = :table
            ORDER BY tc.constraint_name, kcu.ordinal_position
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
        return '"';
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 't', 'true', 'yes', 'y'], true);
    }
}
