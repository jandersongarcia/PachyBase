<?php

declare(strict_types=1);

namespace Tests\Services\Ai;

use PachyBase\Config;
use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Metadata\EntityDefinition;
use PachyBase\Database\Metadata\EntityIntrospector;
use PachyBase\Database\Schema\DatabaseSchema;
use PachyBase\Database\Schema\IndexDefinition;
use PachyBase\Database\Schema\PrimaryKeyDefinition;
use PachyBase\Database\Schema\RelationDefinition;
use PachyBase\Database\Schema\SchemaInspector;
use PachyBase\Database\Schema\TableDefinition;
use PachyBase\Database\Schema\TableSchema;
use PachyBase\Database\Schema\ColumnDefinition;
use PachyBase\Modules\Crud\CrudEntity;
use PachyBase\Modules\Crud\CrudEntityRegistry;
use PachyBase\Services\Ai\AiSchemaService;
use PHPUnit\Framework\TestCase;

class AiSchemaServiceContractTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::reset();
    }

    public function testDescribeEntityDocumentsRichFilterOperators(): void
    {
        Config::override(['APP_NAME' => 'PachyBase']);

        $service = new AiSchemaService(
            new CrudEntityRegistry([
                new CrudEntity(
                    slug: 'agent-records',
                    table: 'agent_records',
                    searchableFields: ['title'],
                    filterableFields: ['title', 'priority', 'published_on', 'is_active'],
                    sortableFields: ['title', 'priority']
                ),
            ]),
            new EntityIntrospector(
                new SchemaInspector(
                    new AiSchemaStaticAdapter(
                        new TableSchema(
                            new TableDefinition('agent_records', 'public'),
                            [
                                new ColumnDefinition('id', 'int4', 'integer', false, null, true),
                                new ColumnDefinition('title', 'varchar', 'string', false),
                                new ColumnDefinition('priority', 'int4', 'integer', false),
                                new ColumnDefinition('published_on', 'date', 'date', true),
                                new ColumnDefinition('is_active', 'boolean', 'boolean', false),
                            ],
                            new PrimaryKeyDefinition('agent_records_pkey', ['id']),
                            [],
                            []
                        )
                    )
                )
            )
        );

        $entity = $service->describeEntity('agent-records');
        $fields = [];

        foreach ($entity['fields'] as $field) {
            $fields[$field['name']] = $field;
        }

        $this->assertSame(
            'Use filter[field]=value for equality, or filter[field][operator]=value for explicit operators.',
            $entity['filters']['field_filters']['syntax']
        );
        $this->assertArrayHasKey('contains', $entity['filters']['field_filters']['operators']);
        $this->assertContains('filter[priority][gte]=3', $entity['filters']['field_filters']['examples']);
        $this->assertSame(['eq', 'ne', 'in', 'null', 'contains'], $fields['title']['filter_operators']);
        $this->assertSame(['eq', 'ne', 'in', 'null', 'gt', 'gte', 'lt', 'lte'], $fields['priority']['filter_operators']);
        $this->assertSame(['eq', 'ne', 'in', 'null'], $fields['is_active']['filter_operators']);
    }
}

final class AiSchemaStaticAdapter implements DatabaseAdapterInterface
{
    public function __construct(private readonly TableSchema $tableSchema)
    {
    }

    public function driver(): string
    {
        return 'pgsql';
    }

    public function databaseName(): string
    {
        return 'pachybase';
    }

    public function schemaName(): string
    {
        return 'public';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"' . $identifier . '"';
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
        return $this->tableSchema->primaryKey;
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
        return $this->tableSchema;
    }

    public function inspectDatabase(): DatabaseSchema
    {
        return new DatabaseSchema('pgsql', 'pachybase', 'public', [$this->tableSchema]);
    }
}
