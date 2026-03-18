<?php

declare(strict_types=1);

namespace PachyBase\Database\Metadata;

final class InMemoryMetadataCache implements MetadataCacheInterface
{
    /**
     * @var array<string, EntityDefinition>
     */
    private array $entities = [];

    public function get(string $table): ?EntityDefinition
    {
        return $this->entities[$table] ?? null;
    }

    public function put(EntityDefinition $entity): void
    {
        $this->entities[$entity->table] = $entity;
    }

    public function clear(): void
    {
        $this->entities = [];
    }
}
