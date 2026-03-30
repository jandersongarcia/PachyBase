<?php

declare(strict_types=1);

namespace PachyBase\Database\Metadata;

use PachyBase\Database\Schema\ColumnDefinition;
use PachyBase\Database\Schema\SchemaInspector;
use PachyBase\Database\Schema\TableSchema;
use PachyBase\Services\Observability\RequestMetrics;

final class EntityIntrospector
{
    private readonly MetadataCacheInterface $cache;

    public function __construct(
        private readonly ?SchemaInspector $schemaInspector = null,
        ?MetadataCacheInterface $cache = null
    ) {
        $this->cache = $cache ?? new FileMetadataCache();
    }

    public function inspectTable(string $table): EntityDefinition
    {
        $cached = $this->cache->get($table);

        if ($cached instanceof EntityDefinition) {
            return $cached;
        }

        $startedAt = hrtime(true);
        $tableSchema = $this->schemaInspector()->inspectTable($table);
        RequestMetrics::recordIntrospection((hrtime(true) - $startedAt) / 1_000_000);

        $entity = $this->buildDefinition($tableSchema);
        $this->cache->put($entity);

        return $entity;
    }

    /**
     * @return array<int, EntityDefinition>
     */
    public function inspectDatabase(): array
    {
        $entities = [];
        $startedAt = hrtime(true);
        $databaseSchema = $this->schemaInspector()->inspectDatabase();
        RequestMetrics::recordIntrospection((hrtime(true) - $startedAt) / 1_000_000);

        foreach ($databaseSchema->tables as $tableSchema) {
            $cached = $this->cache->get($tableSchema->table->name);

            if ($cached instanceof EntityDefinition) {
                $entities[] = $cached;
                continue;
            }

            $entity = $this->buildDefinition($tableSchema);
            $this->cache->put($entity);
            $entities[] = $entity;
        }

        return $entities;
    }

    public function clearCache(): void
    {
        $this->cache->clear();
    }

    public function buildDefinition(TableSchema $tableSchema): EntityDefinition
    {
        $primaryColumns = $tableSchema->primaryKey?->columns ?? [];
        $fields = [];

        foreach ($tableSchema->columns as $column) {
            $fields[] = $this->buildField($column, $primaryColumns);
        }

        return new EntityDefinition(
            $this->resolveEntityName($tableSchema->table->name),
            $tableSchema->table->name,
            $tableSchema->table->schema,
            count($primaryColumns) === 1 ? $primaryColumns[0] : null,
            $fields
        );
    }

    private function schemaInspector(): SchemaInspector
    {
        return $this->schemaInspector ?? new SchemaInspector();
    }

    /**
     * @param array<int, string> $primaryColumns
     */
    private function buildField(ColumnDefinition $column, array $primaryColumns): FieldDefinition
    {
        $isPrimary = in_array($column->name, $primaryColumns, true);
        $isReadOnly = $this->isReadOnlyField($column, $isPrimary);
        $defaultValue = $this->normalizeDefaultValue($column);

        return new FieldDefinition(
            $column->name,
            $column->name,
            $column->normalizedType,
            $column->nativeType,
            $isPrimary,
            !$column->nullable && $defaultValue === null && !$isReadOnly,
            $isReadOnly,
            $column->nullable,
            $defaultValue,
            $column->autoIncrement,
            $column->length,
            $column->precision,
            $column->scale
        );
    }

    private function isReadOnlyField(ColumnDefinition $column, bool $isPrimary): bool
    {
        if ($isPrimary || $column->autoIncrement) {
            return true;
        }

        return in_array($column->name, ['created_at', 'updated_at', 'deleted_at'], true);
    }

    private function normalizeDefaultValue(ColumnDefinition $column): mixed
    {
        if ($column->defaultValue === null) {
            return null;
        }

        if (!is_string($column->defaultValue)) {
            return $column->defaultValue;
        }

        $defaultValue = trim($column->defaultValue);
        $loweredValue = strtolower($defaultValue);

        if ($column->normalizedType === 'boolean') {
            return match ($loweredValue) {
                'true', '1' => true,
                'false', '0' => false,
                default => $defaultValue,
            };
        }

        if (in_array($column->normalizedType, ['integer', 'bigint'], true) && preg_match('/^-?\d+$/', $defaultValue) === 1) {
            return (int) $defaultValue;
        }

        if (in_array($column->normalizedType, ['decimal', 'float'], true) && is_numeric($defaultValue)) {
            return (float) $defaultValue;
        }

        if (preg_match("/^'(.*)'::[a-z0-9_ ]+$/i", $defaultValue, $matches) === 1) {
            return str_replace("''", "'", $matches[1]);
        }

        if (preg_match("/^'(.*)'$/", $defaultValue, $matches) === 1) {
            return str_replace("''", "'", $matches[1]);
        }

        if (strtoupper($defaultValue) === 'CURRENT_TIMESTAMP') {
            return 'CURRENT_TIMESTAMP';
        }

        return $defaultValue;
    }

    private function resolveEntityName(string $table): string
    {
        $normalized = preg_replace('/^(pb_|pachybase_)/', '', $table) ?? $table;
        $segments = array_values(array_filter(explode('_', $normalized), static fn(string $segment): bool => $segment !== ''));

        if ($segments === []) {
            return $table;
        }

        $lastIndex = array_key_last($segments);

        if ($lastIndex !== null) {
            $segments[$lastIndex] = $this->singularize($segments[$lastIndex]);
        }

        return implode('_', $segments);
    }

    private function singularize(string $value): string
    {
        if (str_ends_with($value, 'ies') && strlen($value) > 3) {
            return substr($value, 0, -3) . 'y';
        }

        if (preg_match('/(sses|shes|ches|xes|zes)$/', $value) === 1) {
            return substr($value, 0, -2);
        }

        if (str_ends_with($value, 's') && preg_match('/(ss|us|is)$/', $value) !== 1) {
            return substr($value, 0, -1);
        }

        return $value;
    }
}
