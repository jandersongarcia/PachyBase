---
id: entity-metadata
title: Entity Metadata
sidebar_position: 4
---

# Entity Metadata

Phase 4 introduces the bridge between raw database schema and the core runtime metadata used by PachyBase.

## What this layer provides

- `database/Metadata/EntityDefinition.php`: canonical entity representation for the core.
- `database/Metadata/FieldDefinition.php`: canonical field representation with semantic flags.
- `database/Metadata/EntityIntrospector.php`: central service that transforms `TableSchema` into `EntityDefinition`.
- `database/Metadata/InMemoryMetadataCache.php`: runtime cache that avoids rebuilding the same metadata repeatedly.

## What gets identified

For each supported table, PachyBase now resolves:

- entity name
- source table
- source schema
- primary field
- required fields
- readonly fields
- normalized field types
- nullable fields
- default values

## Metadata heuristics

The current metadata rules are intentionally predictable:

- table names are normalized into entity names with stable prefixes removed, such as `pb_` and `pachybase_`
- the last table segment is singularized when safe, so `pb_system_settings` becomes `system_setting`
- primary keys are marked as readonly
- auto-increment fields are marked as readonly
- standard system timestamps such as `created_at`, `updated_at`, and `deleted_at` are marked as readonly
- required fields are non-nullable fields without defaults that are not readonly

## Example usage

```php
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Metadata\EntityIntrospector;
use PachyBase\Database\Schema\SchemaInspector;

$introspector = new EntityIntrospector(new SchemaInspector(AdapterFactory::make()));
$entity = $introspector->inspectTable('pb_system_settings');

print_r($entity->toArray());
```

## CLI inspection

You can inspect all currently visible entities from the active database driver with:

```bash
docker compose -f docker/docker-compose.yml exec php php scripts/inspect-entities.php
```

This command emits the normalized entity metadata as JSON and is useful for validating upcoming CRUD generation work.

## Validation

The Phase 4 metadata layer is covered by:

- unit tests for entity and field mapping heuristics
- integration tests against the active database driver
- cache behavior validation inside a single runtime
