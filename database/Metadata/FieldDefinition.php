<?php

declare(strict_types=1);

namespace PachyBase\Database\Metadata;

final readonly class FieldDefinition
{
    public function __construct(
        public string $name,
        public string $column,
        public string $type,
        public string $nativeType,
        public bool $primary,
        public bool $required,
        public bool $readOnly,
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
            'column' => $this->column,
            'type' => $this->type,
            'native_type' => $this->nativeType,
            'primary' => $this->primary,
            'required' => $this->required,
            'readonly' => $this->readOnly,
            'nullable' => $this->nullable,
            'default' => $this->defaultValue,
            'auto_increment' => $this->autoIncrement,
            'length' => $this->length,
            'precision' => $this->precision,
            'scale' => $this->scale,
        ];
    }
}
