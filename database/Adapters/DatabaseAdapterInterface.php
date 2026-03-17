<?php

declare(strict_types=1);

namespace PachyBase\Database\Adapters;

use PachyBase\Database\Schema\ColumnDefinition;
use PachyBase\Database\Schema\DatabaseSchema;
use PachyBase\Database\Schema\IndexDefinition;
use PachyBase\Database\Schema\PrimaryKeyDefinition;
use PachyBase\Database\Schema\RelationDefinition;
use PachyBase\Database\Schema\TableDefinition;
use PachyBase\Database\Schema\TableSchema;

interface DatabaseAdapterInterface
{
    public function driver(): string;

    public function databaseName(): string;

    public function schemaName(): string;

    public function quoteIdentifier(string $identifier): string;

    /**
     * @return array<int, TableDefinition>
     */
    public function listTables(): array;

    /**
     * @return array<int, ColumnDefinition>
     */
    public function listColumns(string $table): array;

    public function listPrimaryKey(string $table): ?PrimaryKeyDefinition;

    /**
     * @return array<int, IndexDefinition>
     */
    public function listIndexes(string $table): array;

    /**
     * @return array<int, RelationDefinition>
     */
    public function listRelations(string $table): array;

    public function inspectTable(string $table): TableSchema;

    public function inspectDatabase(): DatabaseSchema;
}
