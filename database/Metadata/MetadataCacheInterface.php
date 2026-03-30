<?php

declare(strict_types=1);

namespace PachyBase\Database\Metadata;

interface MetadataCacheInterface
{
    public function get(string $table): ?EntityDefinition;

    public function put(EntityDefinition $entity): void;

    public function clear(): void;
}
