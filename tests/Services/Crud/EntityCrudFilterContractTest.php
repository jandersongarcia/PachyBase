<?php

declare(strict_types=1);

namespace Tests\Services\Crud;

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Metadata\EntityDefinition;
use PachyBase\Database\Metadata\FieldDefinition;
use PachyBase\Database\Schema\ColumnDefinition;
use PachyBase\Database\Schema\DatabaseSchema;
use PachyBase\Database\Schema\IndexDefinition;
use PachyBase\Database\Schema\PrimaryKeyDefinition;
use PachyBase\Database\Schema\RelationDefinition;
use PachyBase\Database\Schema\TableDefinition;
use PachyBase\Database\Schema\TableSchema;
use PachyBase\Http\Request;
use PachyBase\Http\ValidationException;
use PachyBase\Modules\Crud\CrudEntity;
use PachyBase\Services\Crud\EntityCrudService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class EntityCrudFilterContractTest extends TestCase
{
    public function testBuildWhereClauseSupportsScalarAndStructuredOperators(): void
    {
        $service = $this->makeService(new FakeDatabaseAdapter('pgsql'));
        $resource = new CrudEntity(
            slug: 'agent-records',
            table: 'agent_records',
            searchableFields: ['title'],
            filterableFields: ['status', 'priority', 'title', 'published_on', 'last_used_at']
        );
        $entity = new EntityDefinition(
            'AgentRecord',
            'agent_records',
            'public',
            'id',
            [
                new FieldDefinition('id', 'id', 'integer', 'int4', true, true, true, false),
                new FieldDefinition('status', 'status', 'string', 'varchar', false, true, false, false),
                new FieldDefinition('priority', 'priority', 'integer', 'int4', false, true, false, false),
                new FieldDefinition('title', 'title', 'string', 'varchar', false, true, false, false),
                new FieldDefinition('published_on', 'published_on', 'date', 'date', false, false, false, true),
                new FieldDefinition('last_used_at', 'last_used_at', 'datetime', 'timestamp', false, false, false, true),
            ]
        );
        $request = new Request('GET', '/api/agent-records', [
            'filter' => [
                'status' => 'draft',
                'priority' => ['gte' => '3', 'lt' => '10'],
                'title' => ['contains' => 'Alpha'],
                'published_on' => ['in' => '2026-03-01,2026-03-02'],
                'last_used_at' => ['null' => 'true'],
            ],
            'search' => 'agent',
        ]);

        [$whereSql, $bindings] = $this->invokeBuildWhereClause($service, $resource, $entity, $request);

        $this->assertStringContainsString('"status" = :filter_status_eq', $whereSql);
        $this->assertStringContainsString('"priority" >= :filter_priority_gte', $whereSql);
        $this->assertStringContainsString('"priority" < :filter_priority_lt', $whereSql);
        $this->assertStringContainsString('LOWER(CAST("title" AS TEXT)) LIKE :filter_title_contains', $whereSql);
        $this->assertStringContainsString('"published_on" IN (:filter_published_on_in_0, :filter_published_on_in_1)', $whereSql);
        $this->assertStringContainsString('"last_used_at" IS NULL', $whereSql);
        $this->assertStringContainsString('(LOWER(CAST("title" AS TEXT)) LIKE :search_0)', $whereSql);

        $this->assertSame('draft', $bindings['filter_status_eq']);
        $this->assertSame(3, $bindings['filter_priority_gte']);
        $this->assertSame(10, $bindings['filter_priority_lt']);
        $this->assertSame('%alpha%', $bindings['filter_title_contains']);
        $this->assertSame('2026-03-01', $bindings['filter_published_on_in_0']);
        $this->assertSame('2026-03-02', $bindings['filter_published_on_in_1']);
        $this->assertSame('%agent%', $bindings['search_0']);
    }

    public function testBuildWhereClauseRejectsUnsupportedOperators(): void
    {
        $service = $this->makeService(new FakeDatabaseAdapter('mysql'));
        $resource = new CrudEntity(
            slug: 'agent-records',
            table: 'agent_records',
            filterableFields: ['is_active']
        );
        $entity = new EntityDefinition(
            'AgentRecord',
            'agent_records',
            'pachybase',
            'id',
            [
                new FieldDefinition('id', 'id', 'integer', 'bigint', true, true, true, false),
                new FieldDefinition('is_active', 'is_active', 'boolean', 'tinyint', false, true, false, false),
            ]
        );
        $request = new Request('GET', '/api/agent-records', [
            'filter' => [
                'is_active' => ['contains' => 'yes'],
            ],
        ]);

        try {
            $this->invokeBuildWhereClause($service, $resource, $entity, $request);
            $this->fail('Expected validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame('invalid_filter_operator', $exception->details()[0]['code']);
            $this->assertSame('is_active', $exception->details()[0]['field']);
        }
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function invokeBuildWhereClause(
        EntityCrudService $service,
        CrudEntity $resource,
        EntityDefinition $entity,
        Request $request
    ): array {
        $method = (new ReflectionClass($service))->getMethod('buildWhereClause');
        $method->setAccessible(true);

        /** @var array{0: string, 1: array<string, mixed>} $result */
        $result = $method->invoke($service, $resource, $entity, $request);

        return $result;
    }

    private function makeService(DatabaseAdapterInterface $adapter): EntityCrudService
    {
        $reflection = new ReflectionClass(EntityCrudService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $property = $reflection->getProperty('adapter');
        $property->setAccessible(true);
        $property->setValue($service, $adapter);

        return $service;
    }
}

final class FakeDatabaseAdapter implements DatabaseAdapterInterface
{
    public function __construct(private readonly string $driverName)
    {
    }

    public function driver(): string
    {
        return $this->driverName;
    }

    public function databaseName(): string
    {
        return 'pachybase';
    }

    public function schemaName(): string
    {
        return $this->driverName === 'pgsql' ? 'public' : 'pachybase';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return $this->driverName === 'pgsql'
            ? '"' . $identifier . '"'
            : '`' . $identifier . '`';
    }

    public function listTables(): array
    {
        return [];
    }

    public function listColumns(string $table): array
    {
        return [];
    }

    public function listPrimaryKey(string $table): ?PrimaryKeyDefinition
    {
        return null;
    }

    public function listIndexes(string $table): array
    {
        return [];
    }

    public function listRelations(string $table): array
    {
        return [];
    }

    public function inspectTable(string $table): TableSchema
    {
        return new TableSchema(new TableDefinition($table, $this->schemaName()), [], null, [], []);
    }

    public function inspectDatabase(): DatabaseSchema
    {
        return new DatabaseSchema($this->driverName, $this->databaseName(), $this->schemaName(), []);
    }
}
