---
id: database-layer
title: Database Layer
sidebar_position: 3
---

# Database Layer

Phases 2, 3, and 4 introduce a reusable persistence foundation for PachyBase. The project no longer depends on scattered schema SQL or raw PDO access spread across services and controllers.

## What this layer provides

- `database/Connection.php`: central PDO connection with driver, database, and schema metadata.
- `database/Query/PdoQueryExecutor.php`: safe prepared-query execution with bindings and transactions.
- `database/Adapters/DatabaseAdapterInterface.php`: common contract for database adapters.
- `database/Adapters/MySqlAdapter.php`: MySQL schema adapter.
- `database/Adapters/PostgresAdapter.php`: PostgreSQL schema adapter.
- `database/Schema/TypeNormalizer.php`: canonical type normalization across engines.
- `database/Schema/SchemaInspector.php`: central schema inspection service.
- `database/Metadata/EntityIntrospector.php`: central entity metadata service built on top of normalized schema inspection.
- `database/Metadata/EntityDefinition.php`: semantic entity representation for the core.
- `database/Metadata/FieldDefinition.php`: semantic field representation with required, readonly, nullable, and default metadata.
- `database/Migrations/MigrationRunner.php`: central migration orchestration for apply, status, and rollback.
- `database/Migrations/MigrationRepository.php`: migration history tracking in the database.
- `database/Migrations/FilesystemMigrationLoader.php`: migration discovery from `database/migration-files/`.
- `database/Seeds/SeedRunner.php`: central seed orchestration for status and execution.
- `database/Seeds/SeedRepository.php`: seed execution tracking in the database.
- `database/Seeds/FilesystemSeedLoader.php`: seed discovery from `database/seed-files/`.
- `database/Schema/SystemTableBlueprint.php`: shared conventions for PachyBase system tables.

## Normalized schema model

The schema layer exposes stable objects for:

- tables
- columns
- primary keys
- indexes
- relations

This allows CRUD generation and future automation to depend on one internal representation instead of vendor-specific SQL.

## Entity metadata model

Phase 4 adds the semantic bridge between raw table schema and the core runtime:

- `EntityDefinition` represents an internal entity with name, table, schema, primary field, and field list.
- `FieldDefinition` captures normalized type, nullable, default, required, readonly, primary, and auto-increment metadata.
- `EntityIntrospector` applies stable conventions such as:
  - removing `pb_` and `pachybase_` prefixes from entity names
  - singularizing the last table segment when safe
  - marking primary keys and system timestamps as readonly
  - marking non-null fields without defaults as required
- `InMemoryMetadataCache` keeps entity metadata warm during the current runtime.

## Database migrations

The database layer now ships a reusable migration layer for both supported engines.

- Migration files live in `database/migration-files/`.
- Each migration file returns an instance of `PachyBase\Database\Migrations\MigrationInterface`.
- `AbstractSqlMigration` can be extended to declare driver-aware SQL statements with less boilerplate.
- Executed migrations are recorded in the `pachybase_migrations` table.

The default PachyBase schema currently standardizes these base tables:

- `pb_system_settings`
- `pb_api_tokens`

Example:

```php
use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Migrations\AbstractSqlMigration;

return new class extends AbstractSqlMigration {
    public function version(): string
    {
        return '202603170001';
    }

    public function description(): string
    {
        return 'Create users table';
    }

    protected function upStatements(DatabaseAdapterInterface $adapter): array
    {
        $table = $adapter->quoteIdentifier('users');

        return match ($adapter->driver()) {
            'mysql' => [
                "CREATE TABLE {$table} (`id` BIGINT AUTO_INCREMENT PRIMARY KEY, `email` VARCHAR(190) NOT NULL)"
            ],
            default => [
                "CREATE TABLE {$table} (\"id\" BIGSERIAL PRIMARY KEY, \"email\" VARCHAR(190) NOT NULL)"
            ],
        };
    }

    protected function downStatements(DatabaseAdapterInterface $adapter): array
    {
        return [
            'DROP TABLE IF EXISTS ' . $adapter->quoteIdentifier('users'),
        ];
    }
};
```

## Example usage

```php
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Schema\SchemaInspector;

$inspector = new SchemaInspector(AdapterFactory::make());
$database = $inspector->inspectDatabase();
$users = $database->table('users');
```

## Safe query execution

Use `PdoQueryExecutor` for parameterized queries:

```php
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;

$executor = new PdoQueryExecutor(Connection::getInstance()->getPDO());
$user = $executor->selectOne(
    'SELECT id, email FROM users WHERE id = :id',
    ['id' => 1]
);
```

## CLI inspection

PachyBase also ships with a schema inspection helper:

```bash
docker compose -f docker/docker-compose.yml exec php php scripts/inspect-schema.php
```

The metadata layer also ships with an entity inspection helper:

```bash
docker compose -f docker/docker-compose.yml exec php php scripts/inspect-entities.php
```

Migration commands are also available:

```bash
docker compose -f docker/docker-compose.yml exec php php scripts/migrate.php status
docker compose -f docker/docker-compose.yml exec php php scripts/migrate.php up
docker compose -f docker/docker-compose.yml exec php php scripts/migrate.php down
```

## Seeds and bootstrap

The local workflow also includes minimum seed support and a one-shot database bootstrap:

- Seed files live in `database/seed-files/`.
- Each seed file returns an instance of `PachyBase\Database\Seeds\SeederInterface`.
- Executed seeds are tracked in `pachybase_seeders`.
- `scripts/bootstrap-database.php` waits for the database, applies pending migrations, and runs pending seeds.

Commands:

```bash
docker compose -f docker/docker-compose.yml exec php php scripts/seed.php status
docker compose -f docker/docker-compose.yml exec php php scripts/seed.php run
docker compose -f docker/docker-compose.yml exec php php scripts/bootstrap-database.php
```

## Validation

The database layer is covered by:

- unit tests for type normalization
- unit tests for MySQL and PostgreSQL adapters
- integration tests for query execution
- integration tests for schema inspection on the active database driver
- unit tests for entity metadata mapping heuristics
- integration tests for entity metadata introspection and runtime cache behavior
- unit tests for filesystem migration discovery
- integration tests for migration apply/rollback on the active database driver
- unit tests for filesystem seed discovery
- integration tests for seed execution on the active database driver
