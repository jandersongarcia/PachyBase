<?php

declare(strict_types=1);

namespace PachyBase\Modules\Crud;

final class CrudEntityRegistry
{
    private const DEFAULT_CONFIG_PATH = __DIR__ . '/../../config/CrudEntities.php';

    /**
     * @var array<string, CrudEntity>
     */
    private array $entities = [];

    /**
     * @param array<int, CrudEntity> $entities
     */
    public function __construct(array $entities = [], ?string $configPath = null)
    {
        $entities = $entities === [] ? self::loadConfiguredEntities($configPath) : $entities;

        foreach ($entities as $entity) {
            $this->entities[$entity->slug] = $entity;
        }
    }

    /**
     * @return array<int, CrudEntity>
     */
    public function all(): array
    {
        return array_values($this->entities);
    }

    public function find(string $slug): ?CrudEntity
    {
        return $this->entities[$slug] ?? null;
    }

    /**
     * @return array<int, CrudEntity>
     */
    public static function defaultEntities(): array
    {
        return [
            new CrudEntity(
                slug: 'system-settings',
                table: 'pb_system_settings',
                searchableFields: ['setting_key', 'setting_value'],
                filterableFields: ['id', 'setting_key', 'value_type', 'is_public'],
                sortableFields: ['id', 'setting_key', 'value_type', 'is_public', 'created_at', 'updated_at'],
                hiddenFields: [],
                defaultSort: ['setting_key'],
                validationRules: [
                    'setting_key' => ['min' => 3, 'max' => 120],
                    'setting_value' => ['max' => 2000],
                    'value_type' => ['enum' => ['string', 'text', 'integer', 'float', 'boolean', 'json']],
                ],
                exposed: true,
                allowDelete: false,
                allowedFields: ['setting_key', 'setting_value', 'value_type', 'is_public'],
                maxPerPage: 50,
                readOnlyFields: ['created_at', 'updated_at']
            ),
            new CrudEntity(
                slug: 'api-tokens',
                table: 'pb_api_tokens',
                searchableFields: ['name'],
                filterableFields: ['id', 'name', 'last_used_at'],
                sortableFields: ['id', 'name', 'last_used_at', 'created_at', 'updated_at'],
                hiddenFields: ['token_hash'],
                defaultSort: ['-id'],
                validationRules: [
                    'name' => ['min' => 3, 'max' => 120],
                    'token_hash' => ['min' => 16, 'max' => 255],
                ],
                exposed: true,
                allowDelete: true,
                allowedFields: ['name', 'token_hash', 'expires_at', 'last_used_at', 'is_active', 'user_id'],
                maxPerPage: 100,
                readOnlyFields: ['created_at', 'updated_at']
            ),
        ];
    }

    /**
     * @return array<int, CrudEntity>
     */
    public static function loadConfiguredEntities(?string $configPath = null): array
    {
        $configPath ??= self::DEFAULT_CONFIG_PATH;

        if (!is_file($configPath)) {
            return self::defaultEntities();
        }

        $configured = require $configPath;

        if ($configured === null || $configured === []) {
            return self::defaultEntities();
        }

        if (!is_array($configured)) {
            throw new \RuntimeException('The CRUD entities config file must return an array.');
        }

        return self::normalizeEntities($configured);
    }

    /**
     * @param array<int, CrudEntity|array<string, mixed>> $configured
     * @return array<int, CrudEntity>
     */
    private static function normalizeEntities(array $configured): array
    {
        $entities = [];

        foreach ($configured as $entity) {
            if (is_array($entity)) {
                $entity = CrudEntity::fromArray($entity);
            }

            if (!$entity instanceof CrudEntity) {
                throw new \RuntimeException('Every configured CRUD entity must be a CrudEntity instance or config array.');
            }

            if ($entity->slug === '' || $entity->table === '') {
                throw new \RuntimeException('Configured CRUD entities must define non-empty slug and table values.');
            }

            $entities[] = $entity;
        }

        return $entities;
    }
}
