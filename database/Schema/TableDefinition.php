<?php

declare(strict_types=1);

namespace PachyBase\Database\Schema;

final readonly class TableDefinition
{
    public function __construct(
        public string $name,
        public string $schema,
        public string $type = 'BASE TABLE'
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'schema' => $this->schema,
            'type' => $this->type,
        ];
    }
}
