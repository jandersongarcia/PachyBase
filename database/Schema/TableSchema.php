<?php

declare(strict_types=1);

namespace PachyBase\Database\Schema;

final readonly class TableSchema
{
    /**
     * @param array<int, ColumnDefinition> $columns
     * @param array<int, IndexDefinition> $indexes
     * @param array<int, RelationDefinition> $relations
     */
    public function __construct(
        public TableDefinition $table,
        public array $columns,
        public ?PrimaryKeyDefinition $primaryKey,
        public array $indexes,
        public array $relations
    ) {
    }

    public function column(string $name): ?ColumnDefinition
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'table' => $this->table->toArray(),
            'columns' => array_map(static fn(ColumnDefinition $column): array => $column->toArray(), $this->columns),
            'primary_key' => $this->primaryKey?->toArray(),
            'indexes' => array_map(static fn(IndexDefinition $index): array => $index->toArray(), $this->indexes),
            'relations' => array_map(static fn(RelationDefinition $relation): array => $relation->toArray(), $this->relations),
        ];
    }
}
