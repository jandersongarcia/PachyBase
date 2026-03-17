<?php

declare(strict_types=1);

namespace PachyBase\Database\Schema;

final readonly class DatabaseSchema
{
    /**
     * @param array<int, TableSchema> $tables
     */
    public function __construct(
        public string $driver,
        public string $database,
        public string $schema,
        public array $tables
    ) {
    }

    public function table(string $name): ?TableSchema
    {
        foreach ($this->tables as $table) {
            if ($table->table->name === $name) {
                return $table;
            }
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'database' => $this->database,
            'schema' => $this->schema,
            'tables' => array_map(static fn(TableSchema $table): array => $table->toArray(), $this->tables),
        ];
    }
}
