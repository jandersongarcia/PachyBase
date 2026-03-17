<?php

declare(strict_types=1);

namespace PachyBase\Database\Schema;

final readonly class RelationDefinition
{
    /**
     * @param array<int, string> $localColumns
     * @param array<int, string> $referencedColumns
     */
    public function __construct(
        public string $name,
        public array $localColumns,
        public string $referencedTable,
        public array $referencedColumns,
        public string $onUpdate,
        public string $onDelete
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'local_columns' => $this->localColumns,
            'referenced_table' => $this->referencedTable,
            'referenced_columns' => $this->referencedColumns,
            'on_update' => $this->onUpdate,
            'on_delete' => $this->onDelete,
        ];
    }
}
