<?php

declare(strict_types=1);

namespace PachyBase\Database\Schema;

final readonly class PrimaryKeyDefinition
{
    /**
     * @param array<int, string> $columns
     */
    public function __construct(
        public string $name,
        public array $columns
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'columns' => $this->columns,
        ];
    }
}
