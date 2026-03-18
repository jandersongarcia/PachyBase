<?php

declare(strict_types=1);

namespace PachyBase\Services\Crud;

use PachyBase\Database\Metadata\EntityDefinition;

final class EntityCrudSerializer
{
    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $hiddenFields
     * @return array<string, mixed>
     */
    public function serialize(EntityDefinition $entity, array $row, array $hiddenFields = []): array
    {
        $serialized = [];

        foreach ($entity->fields as $field) {
            if (in_array($field->name, $hiddenFields, true) || !array_key_exists($field->column, $row)) {
                continue;
            }

            $serialized[$field->name] = $this->castValue($field->type, $row[$field->column]);
        }

        return $serialized;
    }

    private function castValue(string $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $this->toBoolean($value),
            'integer', 'bigint' => is_numeric($value) ? (int) $value : $value,
            'decimal', 'float' => is_numeric($value) ? (float) $value : $value,
            'json' => $this->decodeJson($value),
            default => $value,
        };
    }

    private function toBoolean(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 't', 'yes', 'y', 'on' => true,
            '0', 'false', 'f', 'no', 'n', 'off' => false,
            default => $value,
        };
    }

    private function decodeJson(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
