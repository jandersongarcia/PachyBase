<?php

declare(strict_types=1);

namespace PachyBase\Database\Metadata;

use JsonException;
use PachyBase\Config;

final class FileMetadataCache implements MetadataCacheInterface
{
    private const CACHE_VERSION = '1';
    private const FILE_MODE = 0664;

    /**
     * @var array<string, EntityDefinition>
     */
    private array $entities = [];

    private readonly string $cacheDirectory;
    private readonly string $schemaFingerprint;
    private readonly string $namespaceFingerprint;

    public function __construct(
        ?string $cacheDirectory = null,
        ?string $schemaFingerprint = null
    ) {
        $this->cacheDirectory = $cacheDirectory
            ?? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'entity-metadata';
        $this->schemaFingerprint = $schemaFingerprint ?? $this->resolveSchemaFingerprint();
        $this->namespaceFingerprint = $this->resolveNamespaceFingerprint();
    }

    public function get(string $table): ?EntityDefinition
    {
        if (isset($this->entities[$table])) {
            return $this->entities[$table];
        }

        $path = $this->cachePath($table);

        if (!is_file($path)) {
            return null;
        }

        $payload = $this->readPayload($path);

        if (!$this->isValidPayload($payload)) {
            @unlink($path);

            return null;
        }

        $entity = $this->hydrateEntity($payload['entity']);
        $this->entities[$table] = $entity;

        return $entity;
    }

    public function put(EntityDefinition $entity): void
    {
        if (trim($entity->table) === '') {
            return;
        }

        $this->entities[$entity->table] = $entity;

        if (!$this->ensureCacheDirectory()) {
            return;
        }

        $payload = [
            'version' => self::CACHE_VERSION,
            'schema_fingerprint' => $this->schemaFingerprint,
            'namespace_fingerprint' => $this->namespaceFingerprint,
            'entity' => $this->normalizeEntity($entity),
        ];

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!is_string($encoded)) {
            return;
        }

        $this->writePayload($this->cachePath($entity->table), $encoded . PHP_EOL);
    }

    public function clear(): void
    {
        $this->entities = [];

        if (!is_dir($this->cacheDirectory)) {
            return;
        }

        $files = glob($this->cacheDirectory . DIRECTORY_SEPARATOR . '*');

        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            @unlink($file);
        }
    }

    private function cachePath(string $table): string
    {
        return $this->cacheDirectory
            . DIRECTORY_SEPARATOR
            . sha1($this->namespaceFingerprint . '|' . strtolower($table))
            . '.json';
    }

    private function ensureCacheDirectory(): bool
    {
        if (is_dir($this->cacheDirectory)) {
            return is_writable($this->cacheDirectory);
        }

        return @mkdir($this->cacheDirectory, 0777, true) && is_writable($this->cacheDirectory);
    }

    private function resolveSchemaFingerprint(): string
    {
        $timestamps = [
            (int) (@filemtime(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Metadata' . DIRECTORY_SEPARATOR . 'EntityIntrospector.php') ?: 0),
            (int) (@filemtime(__FILE__) ?: 0),
        ];

        $migrationFiles = glob(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migration-files' . DIRECTORY_SEPARATOR . '*.php'
        );

        if (is_array($migrationFiles)) {
            foreach ($migrationFiles as $migrationFile) {
                $timestamps[] = (int) (@filemtime($migrationFile) ?: 0);
            }
        }

        return sha1(implode('|', [self::CACHE_VERSION, (string) max($timestamps)]));
    }

    private function resolveNamespaceFingerprint(): string
    {
        $driver = (string) Config::get('DB_DRIVER', 'unknown');
        $database = (string) Config::get('DB_DATABASE', '');
        $schema = (string) Config::get('DB_SCHEMA', $driver === 'pgsql' ? 'public' : $database);

        return sha1(implode('|', [$driver, $database, $schema, $this->schemaFingerprint]));
    }

    private function isValidPayload(mixed $payload): bool
    {
        return is_array($payload)
            && ($payload['version'] ?? null) === self::CACHE_VERSION
            && ($payload['schema_fingerprint'] ?? null) === $this->schemaFingerprint
            && ($payload['namespace_fingerprint'] ?? null) === $this->namespaceFingerprint
            && isset($payload['entity'])
            && is_array($payload['entity'])
            && $this->isValidEntityPayload($payload['entity']);
    }

    /**
     * @param mixed $payload
     */
    private function isValidEntityPayload(mixed $payload): bool
    {
        if (!is_array($payload)) {
            return false;
        }

        if (!isset($payload['name'], $payload['table'], $payload['schema'], $payload['fields'])) {
            return false;
        }

        if (!is_string($payload['name']) || trim($payload['name']) === '') {
            return false;
        }

        if (!is_string($payload['table']) || trim($payload['table']) === '') {
            return false;
        }

        if (!is_string($payload['schema']) || !is_array($payload['fields'])) {
            return false;
        }

        foreach ($payload['fields'] as $field) {
            if (!is_array($field)) {
                return false;
            }

            foreach (['name', 'column', 'type', 'native_type'] as $requiredKey) {
                if (!isset($field[$requiredKey]) || !is_string($field[$requiredKey]) || trim($field[$requiredKey]) === '') {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeEntity(EntityDefinition $entity): array
    {
        return [
            'name' => $entity->name,
            'table' => $entity->table,
            'schema' => $entity->schema,
            'primary_field' => $entity->primaryField,
            'fields' => array_map(
                static fn(FieldDefinition $field): array => [
                    'name' => $field->name,
                    'column' => $field->column,
                    'type' => $field->type,
                    'native_type' => $field->nativeType,
                    'primary' => $field->primary,
                    'required' => $field->required,
                    'readonly' => $field->readOnly,
                    'nullable' => $field->nullable,
                    'default' => $field->defaultValue,
                    'auto_increment' => $field->autoIncrement,
                    'length' => $field->length,
                    'precision' => $field->precision,
                    'scale' => $field->scale,
                ],
                $entity->fields
            ),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hydrateEntity(array $payload): EntityDefinition
    {
        $fields = [];

        foreach (($payload['fields'] ?? []) as $field) {
            if (!is_array($field)) {
                continue;
            }

            $fields[] = new FieldDefinition(
                (string) ($field['name'] ?? ''),
                (string) ($field['column'] ?? ''),
                (string) ($field['type'] ?? ''),
                (string) ($field['native_type'] ?? ''),
                (bool) ($field['primary'] ?? false),
                (bool) ($field['required'] ?? false),
                (bool) ($field['readonly'] ?? false),
                (bool) ($field['nullable'] ?? false),
                $field['default'] ?? null,
                (bool) ($field['auto_increment'] ?? false),
                isset($field['length']) ? (int) $field['length'] : null,
                isset($field['precision']) ? (int) $field['precision'] : null,
                isset($field['scale']) ? (int) $field['scale'] : null
            );
        }

        return new EntityDefinition(
            (string) ($payload['name'] ?? ''),
            (string) ($payload['table'] ?? ''),
            (string) ($payload['schema'] ?? ''),
            isset($payload['primary_field']) ? (string) $payload['primary_field'] : null,
            $fields
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readPayload(string $path): ?array
    {
        $handle = @fopen($path, 'rb');

        if (!is_resource($handle)) {
            return null;
        }

        try {
            if (!@flock($handle, LOCK_SH)) {
                return null;
            }

            $contents = stream_get_contents($handle) ?: '';
            @flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if (trim($contents) === '') {
            return null;
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    private function writePayload(string $path, string $contents): void
    {
        $temporaryPath = $path . '.tmp.' . $this->temporarySuffix();
        $handle = @fopen($temporaryPath, 'wb');

        if (!is_resource($handle)) {
            return;
        }

        try {
            if (!@flock($handle, LOCK_EX)) {
                return;
            }

            if (@fwrite($handle, $contents) === false) {
                return;
            }

            @fflush($handle);
            @flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        @chmod($temporaryPath, self::FILE_MODE);

        if (!@rename($temporaryPath, $path)) {
            @unlink($path);

            if (!@rename($temporaryPath, $path)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function temporarySuffix(): string
    {
        try {
            return bin2hex(random_bytes(6));
        } catch (\Throwable) {
            return str_replace('.', '', uniqid('metadata', true));
        }
    }
}
