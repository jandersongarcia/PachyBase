<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Metadata\EntityIntrospector;
use PachyBase\Database\Schema\SchemaInspector;

Config::load(dirname(__DIR__));

$entities = (new EntityIntrospector(new SchemaInspector(AdapterFactory::make())))->inspectDatabase();

echo json_encode(
    array_map(static fn($entity): array => $entity->toArray(), $entities),
    JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
) . PHP_EOL;
