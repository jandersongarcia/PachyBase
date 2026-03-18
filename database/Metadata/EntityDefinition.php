<?php

declare(strict_types=1);

namespace PachyBase\Database\Metadata;

final readonly class EntityDefinition
{
    /**
     * @param array<int, FieldDefinition> $fields
     */
    public function __construct(
        public string $name,
        public string $table,
        public string $schema,
        public ?string $primaryField,
        public array $fields
    ) {
    }

    public function field(string $name): ?FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function requiredFields(): array
    {
        $requiredFields = [];

        foreach ($this->fields as $field) {
            if ($field->required) {
                $requiredFields[] = $field->name;
            }
        }

        return $requiredFields;
    }

    /**
     * @return array<int, string>
     */
    public function readOnlyFields(): array
    {
        $readOnlyFields = [];

        foreach ($this->fields as $field) {
            if ($field->readOnly) {
                $readOnlyFields[] = $field->name;
            }
        }

        return $readOnlyFields;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'table' => $this->table,
            'schema' => $this->schema,
            'primary_field' => $this->primaryField,
            'required_fields' => $this->requiredFields(),
            'readonly_fields' => $this->readOnlyFields(),
            'fields' => array_map(static fn(FieldDefinition $field): array => $field->toArray(), $this->fields),
        ];
    }
}
