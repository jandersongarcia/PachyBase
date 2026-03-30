<?php

declare(strict_types=1);

use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Metadata\EntityDefinition;
use PachyBase\Database\Metadata\EntityIntrospector;
use PachyBase\Database\Schema\SchemaInspector;
use PachyBase\Modules\Crud\CrudEntity;
use PachyBase\Modules\Crud\CrudEntityRegistry;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(crudSyncMain($argv, $basePath));
}

function crudSyncMain(array $argv, string $basePath): int
{
    Config::load($basePath);

    $arguments = array_slice($argv, 1);
    $outputPath = crudSyncResolveOutputPath(
        $arguments,
        $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'CrudEntities.php',
        $basePath
    );
    $exposeNew = in_array('--expose-new', $arguments, true);

    $introspector = new EntityIntrospector(new SchemaInspector(AdapterFactory::make()));
    $definitions = $introspector->inspectDatabase();
    $payload = crudSyncWriteConfig($definitions, new CrudEntityRegistry(), $outputPath, $exposeNew);

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    return 0;
}

/**
 * @param array<int, string> $arguments
 */
function crudSyncResolveOutputPath(array $arguments, string $defaultPath, string $basePath): string
{
    foreach ($arguments as $argument) {
        if (!str_starts_with($argument, '--output=')) {
            continue;
        }

        return crudSyncResolveProjectPath(substr($argument, 9), $basePath);
    }

    return $defaultPath;
}

/**
 * @param array<int, EntityDefinition> $definitions
 * @return array<string, mixed>
 */
function crudSyncWriteConfig(array $definitions, CrudEntityRegistry $registry, string $outputPath, bool $exposeNew = false): array
{
    $entries = crudSyncBuildEntries($definitions, $registry, $exposeNew);
    $directory = dirname($outputPath);

    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException(sprintf('Unable to create directory "%s".', $directory));
    }

    file_put_contents($outputPath, crudSyncGeneratePhp($entries));

    $configuredTables = array_map(
        static fn(array $entry): string => (string) $entry['table'],
        $entries
    );
    $definitionTables = array_map(
        static fn(EntityDefinition $definition): string => $definition->table,
        $definitions
    );

    return [
        'output' => $outputPath,
        'entities' => count($entries),
        'generated' => count($definitions),
        'preserved' => count(array_diff($configuredTables, $definitionTables)),
    ];
}

/**
 * @param array<int, EntityDefinition> $definitions
 * @return array<int, array<string, mixed>>
 */
function crudSyncBuildEntries(array $definitions, CrudEntityRegistry $registry, bool $exposeNew = false): array
{
    usort(
        $definitions,
        static fn(EntityDefinition $left, EntityDefinition $right): int => strcmp($left->table, $right->table)
    );

    $configuredByTable = [];

    foreach ($registry->all() as $entity) {
        if ($entity->hooks !== []) {
            throw new RuntimeException(
                sprintf('crud:sync cannot serialize callable hooks for "%s".', $entity->slug)
            );
        }

        $configuredByTable[$entity->table] = $entity;
    }

    $entries = [];
    $seenTables = [];

    foreach ($definitions as $definition) {
        $configured = $configuredByTable[$definition->table] ?? null;
        $entries[] = crudSyncEntityDefinitionToConfig($definition, $configured, $exposeNew);
        $seenTables[$definition->table] = true;
    }

    foreach ($registry->all() as $configured) {
        if (isset($seenTables[$configured->table])) {
            continue;
        }

        $entries[] = crudSyncSerializeEntity($configured);
    }

    usort(
        $entries,
        static fn(array $left, array $right): int => strcmp((string) $left['table'], (string) $right['table'])
    );

    return $entries;
}

/**
 * @return array<string, mixed>
 */
function crudSyncEntityDefinitionToConfig(EntityDefinition $definition, ?CrudEntity $configured, bool $exposeNew): array
{
    $hiddenFields = $configured?->hiddenFields ?? crudSyncDefaultHiddenFields($definition);
    $readOnlyFields = array_values(array_unique(array_merge(
        $definition->readOnlyFields(),
        $configured?->readOnlyFields ?? []
    )));

    return [
        'slug' => $configured?->slug ?? crudSyncTableToSlug($definition->table),
        'table' => $definition->table,
        'exposed' => $configured?->exposed ?? $exposeNew,
        'allow_delete' => $configured?->allowDelete ?? true,
        'searchable_fields' => $configured?->searchableFields ?? crudSyncDefaultSearchableFields($definition, $hiddenFields),
        'filterable_fields' => $configured?->filterableFields ?? crudSyncDefaultFilterableFields($definition, $hiddenFields),
        'sortable_fields' => $configured?->sortableFields ?? crudSyncDefaultSortableFields($definition, $hiddenFields),
        'allowed_fields' => $configured?->allowedFields ?? crudSyncDefaultAllowedFields($definition),
        'hidden_fields' => $hiddenFields,
        'readonly_fields' => $readOnlyFields,
        'default_sort' => $configured?->defaultSort ?? crudSyncDefaultSortFields($definition),
        'max_per_page' => $configured?->maxPerPage ?? 100,
        'validation_rules' => $configured?->validationRules ?? crudSyncDefaultValidationRules($definition, $hiddenFields),
    ];
}

/**
 * @return array<string, mixed>
 */
function crudSyncSerializeEntity(CrudEntity $entity): array
{
    return [
        'slug' => $entity->slug,
        'table' => $entity->table,
        'exposed' => $entity->exposed,
        'allow_delete' => $entity->allowDelete,
        'searchable_fields' => $entity->searchableFields,
        'filterable_fields' => $entity->filterableFields,
        'sortable_fields' => $entity->sortableFields,
        'allowed_fields' => $entity->allowedFields,
        'hidden_fields' => $entity->hiddenFields,
        'readonly_fields' => $entity->readOnlyFields,
        'default_sort' => $entity->defaultSort,
        'max_per_page' => $entity->maxPerPage,
        'validation_rules' => $entity->validationRules,
    ];
}

/**
 * @param array<int, array<string, mixed>> $entries
 */
function crudSyncGeneratePhp(array $entries): string
{
    $body = array_map(
        static fn(array $entry): string => '    ' . crudSyncExportPhpValue($entry, 1),
        $entries
    );

    return implode(PHP_EOL, [
        '<?php',
        '',
        'declare(strict_types=1);',
        '',
        'return [',
        implode(',' . PHP_EOL, $body),
        '];',
        '',
    ]);
}

/**
 * @return array<int, string>
 */
function crudSyncDefaultSearchableFields(EntityDefinition $definition, array $hiddenFields): array
{
    $fields = [];

    foreach ($definition->fields as $field) {
        if (in_array($field->name, $hiddenFields, true)) {
            continue;
        }

        if (in_array($field->type, ['string', 'text'], true)) {
            $fields[] = $field->name;
        }
    }

    return $fields;
}

/**
 * @return array<int, string>
 */
function crudSyncDefaultFilterableFields(EntityDefinition $definition, array $hiddenFields): array
{
    return array_values(array_map(
        static fn($field): string => $field->name,
        array_filter(
            $definition->fields,
            static fn($field): bool => !in_array($field->name, $hiddenFields, true)
        )
    ));
}

/**
 * @return array<int, string>
 */
function crudSyncDefaultSortableFields(EntityDefinition $definition, array $hiddenFields): array
{
    return crudSyncDefaultFilterableFields($definition, $hiddenFields);
}

/**
 * @return array<int, string>
 */
function crudSyncDefaultAllowedFields(EntityDefinition $definition): array
{
    $fields = [];

    foreach ($definition->fields as $field) {
        if ($field->readOnly) {
            continue;
        }

        $fields[] = $field->name;
    }

    return $fields;
}

/**
 * @return array<int, string>
 */
function crudSyncDefaultHiddenFields(EntityDefinition $definition): array
{
    $hiddenFields = [];

    foreach ($definition->fields as $field) {
        if (preg_match('/(?:password|secret|token|hash)/i', $field->name) === 1) {
            $hiddenFields[] = $field->name;
        }
    }

    return $hiddenFields;
}

/**
 * @return array<int, string>
 */
function crudSyncDefaultSortFields(EntityDefinition $definition): array
{
    if ($definition->primaryField !== null && $definition->primaryField !== '') {
        return ['-' . $definition->primaryField];
    }

    $firstField = $definition->fields[0]->name ?? null;

    return $firstField === null ? [] : [$firstField];
}

/**
 * @param array<int, string> $hiddenFields
 * @return array<string, array<string, int>>
 */
function crudSyncDefaultValidationRules(EntityDefinition $definition, array $hiddenFields): array
{
    $rules = [];

    foreach ($definition->fields as $field) {
        if (in_array($field->name, $hiddenFields, true)) {
            continue;
        }

        if ($field->length === null || !in_array($field->type, ['string', 'text'], true)) {
            continue;
        }

        $rules[$field->name] = ['max' => $field->length];
    }

    return $rules;
}

function crudSyncTableToSlug(string $table): string
{
    $slug = preg_replace('/^(?:pb_|pachybase_)/', '', $table) ?? $table;

    return str_replace('_', '-', $slug);
}

/**
 * @param mixed $value
 */
function crudSyncExportPhpValue(mixed $value, int $depth = 0): string
{
    if (is_array($value)) {
        if ($value === []) {
            return '[]';
        }

        $isList = array_is_list($value);
        $indent = str_repeat('    ', $depth);
        $childIndent = str_repeat('    ', $depth + 1);
        $lines = ['['];

        foreach ($value as $key => $item) {
            $line = $childIndent;

            if (!$isList) {
                $line .= crudSyncExportPhpValue($key) . ' => ';
            }

            $line .= crudSyncExportPhpValue($item, $depth + 1) . ',';
            $lines[] = $line;
        }

        $lines[] = $indent . ']';

        return implode(PHP_EOL, $lines);
    }

    if (is_string($value)) {
        return "'" . str_replace(['\\', '\''], ['\\\\', '\\\''], $value) . "'";
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if ($value === null) {
        return 'null';
    }

    return (string) $value;
}

function crudSyncResolveProjectPath(string $path, string $basePath): string
{
    if ($path === '') {
        return $basePath;
    }

    if (preg_match('~^(?:[A-Za-z]:[\\\\/]|[\\\\/]{2}|/)~', $path) === 1) {
        return $path;
    }

    return $basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}
