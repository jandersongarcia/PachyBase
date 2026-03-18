<?php

declare(strict_types=1);

namespace PachyBase\Database\Schema;

final readonly class IndexDefinition
{
    /**
     * @param array<int, string> $columns
     */
    public function __construct(
        public string $name,
        public array $columns,
        public bool $unique,
        public bool $primary = false
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'columns' => $this->columns,
            'unique' => $this->unique,
            'primary' => $this->primary,
        ];
    }
}
