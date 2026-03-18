<?php

declare(strict_types=1);

namespace PachyBase\Services\Crud;

use DateTimeImmutable;
use PachyBase\Database\Metadata\EntityDefinition;
use PachyBase\Database\Metadata\FieldDefinition;
use PachyBase\Http\ValidationException;
use PachyBase\Modules\Crud\CrudEntity;

final class EntityCrudValidator
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function validateForCreate(CrudEntity $resource, EntityDefinition $entity, array $payload): array
    {
        return $this->validate($resource, $entity, $payload, 'create', true, true);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function validateForReplace(CrudEntity $resource, EntityDefinition $entity, array $payload): array
    {
        return $this->validate($resource, $entity, $payload, 'replace', true, false);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function validateForPatch(CrudEntity $resource, EntityDefinition $entity, array $payload): array
    {
        return $this->validate($resource, $entity, $payload, 'patch', false, false);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function validate(
        CrudEntity $resource,
        EntityDefinition $entity,
        array $payload,
        string $operation,
        bool $requireRequiredFields,
        bool $requireAtLeastOneField
    ): array {
        $details = [];
        $fieldsByName = [];
        $normalized = [];
        $providedWritableFields = [];

        foreach ($entity->fields as $field) {
            $fieldsByName[$field->name] = $field;
        }

        foreach ($payload as $fieldName => $value) {
            $field = $fieldsByName[$fieldName] ?? null;

            if (!$field instanceof FieldDefinition) {
                $details[] = $this->detail($fieldName, 'unknown_field', 'The field is not recognized by this entity.');
                continue;
            }

            if (!$resource->isFieldAllowed($fieldName)) {
                $details[] = $this->detail($fieldName, 'field_not_allowed', 'The field is not allowed for write operations on this entity.');
                continue;
            }

            if ($resource->isReadOnly($fieldName, $field->readOnly)) {
                $details[] = $this->detail($fieldName, 'readonly_field', 'The field is read-only and cannot be written.');
                continue;
            }

            $providedWritableFields[$fieldName] = true;
            $rules = $this->resolveRules($resource, $field, $operation);
            $normalizedValue = $this->normalizeValue($field, $value, $rules, $details);

            if ($normalizedValue !== self::skipValue()) {
                $normalized[$fieldName] = $normalizedValue;
            }
        }

        if ($requireRequiredFields) {
            foreach ($entity->fields as $field) {
                if (
                    $resource->isReadOnly($field->name, $field->readOnly)
                    || !$resource->isFieldAllowed($field->name)
                    || !$this->isRequired($resource, $field, $operation)
                ) {
                    continue;
                }

                if (!array_key_exists($field->name, $normalized) && !array_key_exists($field->name, $providedWritableFields)) {
                    $details[] = $this->detail($field->name, 'required', 'The field is required.');
                }
            }
        }

        if ($requireAtLeastOneField && $normalized === [] && $providedWritableFields === []) {
            $details[] = $this->detail('payload', 'empty_payload', 'At least one writable field must be provided.');
        }

        if ($details !== []) {
            throw new ValidationException(details: $details);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $rules
     * @param array<int, array<string, mixed>> $details
     */
    private function normalizeValue(FieldDefinition $field, mixed $value, array $rules, array &$details): mixed
    {
        if ($value === null) {
            if (($rules['nullable'] ?? false) === true) {
                return null;
            }

            $details[] = $this->detail($field->name, 'not_nullable', 'The field cannot be null.');

            return self::skipValue();
        }

        $normalized = match ($field->type) {
            'boolean' => $this->normalizeBoolean($field, $value, $details),
            'integer', 'bigint' => $this->normalizeInteger($field, $value, $details),
            'decimal', 'float' => $this->normalizeFloat($field, $value, $details),
            'json' => $this->normalizeJson($field, $value, $details),
            'uuid' => $this->normalizeUuid($field, $value, $details),
            'date' => $this->normalizeDate($field, $value, $details),
            'time' => $this->normalizeTime($field, $value, $details),
            'datetime' => $this->normalizeDateTime($field, $value, $details),
            'string', 'text', 'enum', 'binary' => $this->normalizeScalarString($field, $value, $details),
            default => $this->normalizeScalarString($field, $value, $details),
        };

        if ($normalized === self::skipValue()) {
            return $normalized;
        }

        $this->applyRules($field, $normalized, $rules, $details);

        return $details === [] || !$this->hasFieldErrors($details, $field->name)
            ? $normalized
            : self::skipValue();
    }

    /**
     * @param array<string, mixed> $rules
     * @param array<int, array<string, mixed>> $details
     */
    private function applyRules(FieldDefinition $field, mixed $value, array $rules, array &$details): void
    {
        if (($rules['email'] ?? false) === true && filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false) {
            $details[] = $this->detail($field->name, 'email', 'The field must be a valid email address.');
        }

        if (($rules['url'] ?? false) === true && filter_var((string) $value, FILTER_VALIDATE_URL) === false) {
            $details[] = $this->detail($field->name, 'url', 'The field must be a valid URL.');
        }

        if (($rules['uuid'] ?? false) === true && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', (string) $value) !== 1) {
            $details[] = $this->detail($field->name, 'uuid', 'The field must be a valid UUID.');
        }

        if (array_key_exists('enum', $rules) && is_array($rules['enum']) && !in_array($value, $rules['enum'], true)) {
            $details[] = $this->detail($field->name, 'enum', 'The field must use one of the allowed values.');
        }

        if (array_key_exists('min', $rules)) {
            $this->applyBoundaryRule($field, $value, 'min', $rules['min'], $details);
        }

        if (array_key_exists('max', $rules)) {
            $this->applyBoundaryRule($field, $value, 'max', $rules['max'], $details);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $details
     */
    private function applyBoundaryRule(FieldDefinition $field, mixed $value, string $rule, mixed $limit, array &$details): void
    {
        if (!is_int($limit) && !is_float($limit)) {
            return;
        }

        if (in_array($field->type, ['integer', 'bigint', 'decimal', 'float'], true)) {
            $isInvalid = $rule === 'min' ? $value < $limit : $value > $limit;

            if ($isInvalid) {
                $details[] = $this->detail(
                    $field->name,
                    $rule,
                    sprintf('The field must be %s %s.', $rule === 'min' ? 'greater than or equal to' : 'less than or equal to', (string) $limit)
                );
            }

            return;
        }

        $length = strlen((string) $value);
        $isInvalid = $rule === 'min' ? $length < $limit : $length > $limit;

        if ($isInvalid) {
            $details[] = $this->detail(
                $field->name,
                $rule,
                sprintf('The field length must be %s %s characters.', $rule === 'min' ? 'at least' : 'at most', (string) $limit)
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $details
     */
    private function normalizeBoolean(FieldDefinition $field, mixed $value, array &$details): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'yes', 'y', 'on' => true,
            '0', 'false', 'no', 'n', 'off' => false,
            default => $this->invalid($field, $details, 'boolean', 'The field must be a boolean value.'),
        };
    }

    /**
     * @param array<int, array<string, mixed>> $details
     */
    private function normalizeInteger(FieldDefinition $field, mixed $value, array &$details): mixed
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return $this->invalid($field, $details, 'integer', 'The field must be an integer value.');
    }

    /**
     * @param array<int, array<string, mixed>> $details
     */
    private function normalizeFloat(FieldDefinition $field, mixed $value, array &$details): mixed
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric(trim($value))) {
            return (float) trim($value);
        }

        return $this->invalid($field, $details, 'number', 'The field must be a numeric value.');
    }

    /**
     * @param array<int, array<string, mixed>> $details
     */
    private function normalizeJson(FieldDefinition $field, mixed $value, array &$details): mixed
    {
        if (!is_string($value)) {
            return $this->invalid($field, $details, 'json', 'The field must contain a valid JSON string.');
        }

        json_decode($value);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->invalid($field, $details, 'json', 'The field must contain valid JSON.');
        }

        return $value;
    }

    /**
     * @param array<int, array<string, mixed>> $details
     */
    private function normalizeUuid(FieldDefinition $field, mixed $value, array &$details): mixed
    {
        $normalized = $this->normalizeScalarString($field, $value, $details);

        if ($normalized === self::skipValue()) {
            return $normalized;
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $normalized) !== 1) {
            return $this->invalid($field, $details, 'uuid', 'The field must be a valid UUID.');
        }

        return strtolower($normalized);
    }

    /**
     * @param array<int, array<string, mixed>> $details
     */
    private function normalizeDate(FieldDefinition $field, mixed $value, array &$details): mixed
    {
        $normalized = $this->normalizeScalarString($field, $value, $details);

        if ($normalized === self::skipValue()) {
            return $normalized;
        }

        if (!$this->matchesDateFormat($normalized, 'Y-m-d')) {
            return $this->invalid($field, $details, 'date', 'The field must use the YYYY-MM-DD format.');
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $details
     */
    private function normalizeTime(FieldDefinition $field, mixed $value, array &$details): mixed
    {
        $normalized = $this->normalizeScalarString($field, $value, $details);

        if ($normalized === self::skipValue()) {
            return $normalized;
        }

        if (!$this->matchesAnyDateFormat($normalized, ['H:i', 'H:i:s'])) {
            return $this->invalid($field, $details, 'time', 'The field must use the HH:MM or HH:MM:SS format.');
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $details
     */
    private function normalizeDateTime(FieldDefinition $field, mixed $value, array &$details): mixed
    {
        $normalized = $this->normalizeScalarString($field, $value, $details);

        if ($normalized === self::skipValue()) {
            return $normalized;
        }

        foreach (['Y-m-d\TH:i:sP', 'Y-m-d H:i:s', 'Y-m-d H:i:sP', DATE_ATOM] as $format) {
            if ($this->matchesDateFormat($normalized, $format)) {
                return $normalized;
            }
        }

        try {
            new DateTimeImmutable($normalized);

            return $normalized;
        } catch (\Exception) {
            return $this->invalid($field, $details, 'datetime', 'The field must be a valid datetime value.');
        }
    }

    /**
     * @param array<int, array<string, mixed>> $details
     */
    private function normalizeScalarString(FieldDefinition $field, mixed $value, array &$details): mixed
    {
        if (!is_scalar($value)) {
            return $this->invalid($field, $details, 'string', 'The field must be a scalar value.');
        }

        return (string) $value;
    }

    /**
     * @param array<int, array<string, mixed>> $details
     */
    private function invalid(FieldDefinition $field, array &$details, string $code, string $message): object
    {
        $details[] = $this->detail($field->name, $code, $message);

        return self::skipValue();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRules(CrudEntity $resource, FieldDefinition $field, string $operation): array
    {
        $rules = [
            'type' => $field->type,
            'nullable' => $field->nullable,
        ];

        if ($field->length !== null && in_array($field->type, ['string', 'text', 'enum', 'binary', 'uuid'], true)) {
            $rules['max'] = $field->length;
        }

        foreach ($resource->rulesFor($field->name) as $rule => $value) {
            $rules[$rule] = $value;
        }

        $rules['required'] = $this->isRequired($resource, $field, $operation);

        return $rules;
    }

    private function isRequired(CrudEntity $resource, FieldDefinition $field, string $operation): bool
    {
        $rules = $resource->rulesFor($field->name);

        if ($operation === 'patch') {
            return (bool) ($rules['required_on_patch'] ?? false);
        }

        if ($operation === 'create' && array_key_exists('required_on_create', $rules)) {
            return (bool) $rules['required_on_create'];
        }

        if ($operation === 'replace' && array_key_exists('required_on_replace', $rules)) {
            return (bool) $rules['required_on_replace'];
        }

        if (array_key_exists('required', $rules)) {
            return (bool) $rules['required'];
        }

        return $field->required;
    }

    /**
     * @return array<string, string>
     */
    private function detail(string $field, string $code, string $message): array
    {
        return [
            'field' => $field,
            'code' => $code,
            'message' => $message,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $details
     */
    private function hasFieldErrors(array $details, string $fieldName): bool
    {
        foreach ($details as $detail) {
            if (($detail['field'] ?? null) === $fieldName) {
                return true;
            }
        }

        return false;
    }

    private function matchesDateFormat(string $value, string $format): bool
    {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        $normalizedFormat = ltrim($format, '!');

        return $date instanceof DateTimeImmutable && $date->format($normalizedFormat) === $value;
    }

    /**
     * @param array<int, string> $formats
     */
    private function matchesAnyDateFormat(string $value, array $formats): bool
    {
        foreach ($formats as $format) {
            if ($this->matchesDateFormat($value, $format)) {
                return true;
            }
        }

        return false;
    }

    private static function skipValue(): object
    {
        static $marker;

        return $marker ??= new \stdClass();
    }
}
