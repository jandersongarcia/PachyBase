<?php

declare(strict_types=1);

namespace PachyBase\Database\Schema;

final readonly class ColumnDefinition
{
    public function __construct(
        public string $name,
        public string $nativeType,
        public string $normalizedType,
        public bool $nullable,
        public mixed $defaultValue = null,
        public bool $autoIncrement = false,
        public ?int $length = null,
        public ?int $precision = null,
        public ?int $scale = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'native_type' => $this->nativeType,
            'normalized_type' => $this->normalizedType,
            'nullable' => $this->nullable,
            'default' => $this->defaultValue,
            'auto_increment' => $this->autoIncrement,
            'length' => $this->length,
            'precision' => $this->precision,
            'scale' => $this->scale,
        ];
    }
}
