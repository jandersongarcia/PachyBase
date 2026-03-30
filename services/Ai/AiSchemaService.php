<?php

declare(strict_types=1);

namespace PachyBase\Services\Ai;

use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Metadata\EntityDefinition;
use PachyBase\Database\Metadata\EntityIntrospector;
use PachyBase\Database\Metadata\FieldDefinition;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Schema\SchemaInspector;
use PachyBase\Modules\Crud\CrudEntity;
use PachyBase\Modules\Crud\CrudEntityRegistry;
use RuntimeException;

final class AiSchemaService
{
    public function __construct(
        private readonly ?CrudEntityRegistry $registry = null,
        private readonly ?EntityIntrospector $introspector = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSchema(): array
    {
        $entities = [];

        foreach ($this->resources() as $resource) {
            $entities[] = $this->describeEntity($resource->slug);
        }

        return [
            'schema_version' => '1.0',
            'generated_at' => gmdate('c'),
            'generator' => [
                'name' => Config::get('APP_NAME', 'PachyBase'),
                'feature' => 'ai-friendly-schema',
            ],
            'navigation' => [
                'entities_url' => '/ai/entities',
                'entity_url_template' => '/ai/entity/{name}',
                'openapi_url' => '/openapi.json',
            ],
            'openapi_compatibility' => $this->openApiCompatibility(),
            'entities' => $entities,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listEntities(): array
    {
        $items = [];

        foreach ($this->resources() as $resource) {
            $entity = $this->metadata($resource);
            $fields = $this->visibleFields($resource, $entity);

            $items[] = [
                'name' => $resource->slug,
                'entity_name' => $entity->name,
                'table' => $resource->table,
                'primary_field' => $entity->primaryField,
                'field_count' => count($fields),
                'collection_path' => $this->collectionPath($resource),
                'item_path' => $this->itemPath($resource),
                'entity_url' => '/ai/entity/' . $resource->slug,
                'operations_available' => $this->availableOperationNames($resource),
            ];
        }

        return [
            'count' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function describeEntity(string $name): array
    {
        $resource = $this->resource($name);
        $entity = $this->metadata($resource);
        $fields = [];

        foreach ($entity->fields as $field) {
            if ($resource->isHidden($field->name)) {
                continue;
            }

            $fields[] = $this->describeField($resource, $entity, $field);
        }

        return [
            'name' => $resource->slug,
            'entity_name' => $entity->name,
            'table' => $resource->table,
            'database_schema' => $entity->schema,
            'primary_field' => $entity->primaryField,
            'description' => sprintf('Automatic CRUD metadata for the "%s" entity.', $resource->slug),
            'paths' => [
                'collection' => $this->collectionPath($resource),
                'item' => $this->itemPath($resource),
                'documentation' => '/ai/entity/' . $resource->slug,
            ],
            'pagination' => [
                'enabled' => true,
                'default_per_page' => 15,
                'max_per_page' => $resource->effectiveMaxPerPage(),
                'query_parameters' => [
                    'page' => [
                        'type' => 'integer',
                        'required' => false,
                        'default' => 1,
                        'minimum' => 1,
                    ],
                    'per_page' => [
                        'type' => 'integer',
                        'required' => false,
                        'default' => 15,
                        'minimum' => 1,
                        'maximum' => $resource->effectiveMaxPerPage(),
                    ],
                ],
            ],
            'filters' => [
                'search_parameter' => [
                    'name' => 'search',
                    'type' => 'string',
                    'enabled' => $this->searchableFields($resource, $entity) !== [],
                    'fields' => $this->searchableFields($resource, $entity),
                ],
                'sort_parameter' => [
                    'name' => 'sort',
                    'type' => 'string',
                    'enabled' => $this->sortableFields($resource, $entity) !== [],
                    'fields' => $this->sortableFields($resource, $entity),
                    'default' => $resource->defaultSort,
                    'syntax' => 'Comma-separated field names. Prefix a field with "-" for descending order.',
                ],
                'field_filters' => [
                    'name' => 'filter',
                    'type' => 'object',
                    'style' => 'deepObject',
                    'enabled' => $this->filterableFields($resource, $entity) !== [],
                    'fields' => $this->filterableFields($resource, $entity),
                    'syntax' => 'Use filter[field]=value for equality, or filter[field][operator]=value for explicit operators.',
                    'operators' => [
                        'eq' => 'Equals. This is also the implicit behavior of filter[field]=value.',
                        'ne' => 'Not equals.',
                        'gt' => 'Greater than. Supported on numeric and date/time fields.',
                        'gte' => 'Greater than or equal to. Supported on numeric and date/time fields.',
                        'lt' => 'Less than. Supported on numeric and date/time fields.',
                        'lte' => 'Less than or equal to. Supported on numeric and date/time fields.',
                        'in' => 'Matches any value from a comma-separated list or repeated list input.',
                        'contains' => 'Case-insensitive partial match for string/text fields.',
                        'null' => 'Use true for IS NULL or false for IS NOT NULL.',
                    ],
                    'examples' => [
                        'filter[setting_key]=site_name',
                        'filter[priority][gte]=3',
                        'filter[title][contains]=alpha',
                        'filter[status][in]=draft,published',
                        'filter[last_used_at][null]=true',
                    ],
                ],
            ],
            'operations' => $this->operations($resource),
            'fields' => $fields,
            'openapi' => [
                'compatible' => true,
                'document_url' => '/openapi.json',
                'component_refs' => [
                    'item' => '#/components/schemas/' . $this->crudItemSchemaName($resource),
                    'create_request' => '#/components/schemas/' . $this->crudCreateSchemaName($resource),
                    'replace_request' => '#/components/schemas/' . $this->crudReplaceSchemaName($resource),
                    'patch_request' => '#/components/schemas/' . $this->crudPatchSchemaName($resource),
                    'delete_result' => '#/components/schemas/' . $this->crudDeleteSchemaName($resource),
                ],
                'paths' => [
                    'collection' => $this->collectionPath($resource),
                    'item' => $this->itemPath($resource),
                ],
            ],
        ];
    }

    /**
     * @return array<int, CrudEntity>
     */
    private function resources(): array
    {
        return array_values(
            array_filter(
                ($this->registry ?? new CrudEntityRegistry())->all(),
                static fn(CrudEntity $resource): bool => $resource->isExposed()
            )
        );
    }

    private function resource(string $slug): CrudEntity
    {
        $resource = ($this->registry ?? new CrudEntityRegistry())->find($slug);

        if (!$resource instanceof CrudEntity || !$resource->isExposed()) {
            throw new RuntimeException(sprintf('Entity not found: %s', $slug), 404);
        }

        return $resource;
    }

    private function metadata(CrudEntity $resource): EntityDefinition
    {
        if ($this->introspector instanceof EntityIntrospector) {
            return $this->introspector->inspectTable($resource->table);
        }

        $connection = Connection::getInstance();
        $adapter = AdapterFactory::make($connection, new PdoQueryExecutor($connection->getPDO()));

        return (new EntityIntrospector(new SchemaInspector($adapter)))->inspectTable($resource->table);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function operations(CrudEntity $resource): array
    {
        return [
            $this->operation($resource, 'list', 'GET', $this->collectionPath($resource), true, 'read'),
            $this->operation($resource, 'show', 'GET', $this->itemPath($resource), true, 'read'),
            $this->operation($resource, 'create', 'POST', $this->collectionPath($resource), true, 'create'),
            $this->operation($resource, 'replace', 'PUT', $this->itemPath($resource), true, 'update'),
            $this->operation($resource, 'patch', 'PATCH', $this->itemPath($resource), true, 'update'),
            $this->operation($resource, 'delete', 'DELETE', $this->itemPath($resource), $resource->allowsDelete(), 'delete'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function operation(CrudEntity $resource, string $name, string $method, string $path, bool $available, string $scopeAction): array
    {
        return [
            'name' => $name,
            'method' => $method,
            'path' => $path,
            'available' => $available,
            'authentication_required' => true,
            'required_scopes' => [
                'crud:' . $scopeAction,
                sprintf('entity:%s:%s', $resource->slug, $scopeAction),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function describeField(CrudEntity $resource, EntityDefinition $entity, FieldDefinition $field): array
    {
        $rules = $resource->rulesFor($field->name);
        $readOnly = $resource->isReadOnly($field->name, $field->readOnly);
        $writable = $resource->allowsWriteTo($field->name, $field->readOnly);

        return [
            'name' => $field->name,
            'column' => $field->column,
            'type' => $field->type,
            'native_type' => $field->nativeType,
            'primary' => $field->primary,
            'required' => $field->required,
            'required_on_create' => $this->isRequiredForOperation($resource, $field, 'create'),
            'required_on_replace' => $this->isRequiredForOperation($resource, $field, 'replace'),
            'required_on_patch' => $this->isRequiredForOperation($resource, $field, 'patch'),
            'readonly' => $readOnly,
            'writable' => $writable,
            'nullable' => $field->nullable,
            'visible' => true,
            'filterable' => in_array($field->name, $this->filterableFields($resource, $entity), true),
            'filter_operators' => in_array($field->name, $this->filterableFields($resource, $entity), true)
                ? $this->filterOperators($field)
                : [],
            'sortable' => in_array($field->name, $this->sortableFields($resource, $entity), true),
            'searchable' => in_array($field->name, $this->searchableFields($resource, $entity), true),
            'default' => $field->defaultValue,
            'auto_increment' => $field->autoIncrement,
            'length' => $field->length,
            'precision' => $field->precision,
            'scale' => $field->scale,
            'validation' => $rules,
            'openapi' => [
                'type' => $this->openApiType($field),
                'format' => $this->openApiFormat($field),
                'nullable' => $field->nullable,
                'readOnly' => $readOnly,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function visibleFields(CrudEntity $resource, EntityDefinition $entity): array
    {
        $fields = [];

        foreach ($entity->fields as $field) {
            if ($resource->isHidden($field->name)) {
                continue;
            }

            $fields[] = $field->name;
        }

        return $fields;
    }

    /**
     * @return array<int, string>
     */
    private function filterableFields(CrudEntity $resource, ?EntityDefinition $entity = null): array
    {
        if ($resource->filterableFields !== []) {
            return $resource->filterableFields;
        }

        if (!$entity instanceof EntityDefinition) {
            return [];
        }

        return array_map(static fn(FieldDefinition $field): string => $field->name, $entity->fields);
    }

    /**
     * @return array<int, string>
     */
    private function sortableFields(CrudEntity $resource, ?EntityDefinition $entity = null): array
    {
        if ($resource->sortableFields !== []) {
            return $resource->sortableFields;
        }

        if (!$entity instanceof EntityDefinition) {
            return [];
        }

        return array_map(static fn(FieldDefinition $field): string => $field->name, $entity->fields);
    }

    /**
     * @return array<int, string>
     */
    private function searchableFields(CrudEntity $resource, ?EntityDefinition $entity = null): array
    {
        if ($resource->searchableFields !== []) {
            return $resource->searchableFields;
        }

        if (!$entity instanceof EntityDefinition) {
            return [];
        }

        $fields = [];

        foreach ($entity->fields as $field) {
            if (in_array($field->type, ['string', 'text'], true)) {
                $fields[] = $field->name;
            }
        }

        return $fields;
    }

    private function isRequiredForOperation(CrudEntity $resource, FieldDefinition $field, string $operation): bool
    {
        if ($operation === 'patch') {
            return (bool) ($resource->rulesFor($field->name)['required_on_patch'] ?? false);
        }

        $rules = $resource->rulesFor($field->name);

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
     * @return array<string, mixed>
     */
    private function openApiCompatibility(): array
    {
        return [
            'compatible' => true,
            'document_url' => '/openapi.json',
            'version' => '3.0.3',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function availableOperationNames(CrudEntity $resource): array
    {
        return array_values(
            array_map(
                static fn(array $operation): string => (string) $operation['name'],
                array_filter(
                    $this->operations($resource),
                    static fn(array $operation): bool => (bool) $operation['available']
                )
            )
        );
    }

    private function collectionPath(CrudEntity $resource): string
    {
        return '/api/' . $resource->slug;
    }

    private function itemPath(CrudEntity $resource): string
    {
        return $this->collectionPath($resource) . '/{id}';
    }

    private function crudItemSchemaName(CrudEntity $resource): string
    {
        return 'Crud' . $this->studly($resource->slug) . 'Item';
    }

    private function crudCreateSchemaName(CrudEntity $resource): string
    {
        return 'Crud' . $this->studly($resource->slug) . 'CreateRequest';
    }

    private function crudReplaceSchemaName(CrudEntity $resource): string
    {
        return 'Crud' . $this->studly($resource->slug) . 'ReplaceRequest';
    }

    private function crudPatchSchemaName(CrudEntity $resource): string
    {
        return 'Crud' . $this->studly($resource->slug) . 'PatchRequest';
    }

    private function crudDeleteSchemaName(CrudEntity $resource): string
    {
        return 'Crud' . $this->studly($resource->slug) . 'DeleteResult';
    }

    private function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    private function openApiType(FieldDefinition $field): string
    {
        return match ($field->type) {
            'integer', 'bigint' => 'integer',
            'decimal', 'float' => 'number',
            'boolean' => 'boolean',
            'json' => 'object',
            default => 'string',
        };
    }

    private function openApiFormat(FieldDefinition $field): ?string
    {
        return match ($field->type) {
            'bigint' => 'int64',
            'decimal' => 'double',
            'float' => 'float',
            'date' => 'date',
            'datetime' => 'date-time',
            'uuid' => 'uuid',
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private function filterOperators(FieldDefinition $field): array
    {
        $operators = ['eq', 'ne', 'in', 'null'];

        if (in_array($field->type, ['integer', 'bigint', 'decimal', 'float', 'date', 'datetime', 'time'], true)) {
            $operators = array_merge($operators, ['gt', 'gte', 'lt', 'lte']);
        }

        if (in_array($field->type, ['string', 'text'], true)) {
            $operators[] = 'contains';
        }

        return $operators;
    }
}
