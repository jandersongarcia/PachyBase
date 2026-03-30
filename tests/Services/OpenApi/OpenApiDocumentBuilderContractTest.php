<?php

declare(strict_types=1);

namespace Tests\Services\OpenApi;

use PachyBase\Config;
use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Metadata\EntityIntrospector;
use PachyBase\Database\Schema\ColumnDefinition;
use PachyBase\Database\Schema\DatabaseSchema;
use PachyBase\Database\Schema\PrimaryKeyDefinition;
use PachyBase\Database\Schema\SchemaInspector;
use PachyBase\Database\Schema\TableDefinition;
use PachyBase\Database\Schema\TableSchema;
use PachyBase\Modules\Crud\CrudEntity;
use PachyBase\Modules\Crud\CrudEntityRegistry;
use PachyBase\Services\OpenApi\OpenApiDocumentBuilder;
use PHPUnit\Framework\TestCase;

class OpenApiDocumentBuilderContractTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::reset();
        $_SERVER = [];
    }

    public function testCrudCollectionParametersDescribeRichFilters(): void
    {
        Config::override(['APP_NAME' => 'PachyBase']);

        $builder = new OpenApiDocumentBuilder(
            new CrudEntityRegistry([
                new CrudEntity(
                    slug: 'agent-records',
                    table: 'agent_records',
                    searchableFields: ['title'],
                    filterableFields: ['title', 'priority', 'is_active'],
                    sortableFields: ['title', 'priority']
                ),
            ]),
            new EntityIntrospector(
                new SchemaInspector(
                    new OpenApiStaticAdapter(
                        new TableSchema(
                            new TableDefinition('agent_records', 'public'),
                            [
                                new ColumnDefinition('id', 'int4', 'integer', false, null, true),
                                new ColumnDefinition('title', 'varchar', 'string', false),
                                new ColumnDefinition('priority', 'int4', 'integer', false),
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

        $document = $builder->build();
        $parameters = $document['paths']['/api/agent-records']['get']['parameters'];
        $filterParameter = null;

        foreach ($parameters as $parameter) {
            if (($parameter['name'] ?? null) === 'filter') {
                $filterParameter = $parameter;
                break;
            }
        }

        $this->assertIsArray($filterParameter);
        $this->assertStringContainsString('filter[field][operator]=value', $filterParameter['description']);
        $this->assertArrayHasKey('oneOf', $filterParameter['schema']['properties']['title']);
        $this->assertArrayHasKey(
            'contains',
            $filterParameter['schema']['properties']['title']['oneOf'][1]['properties']
        );
        $this->assertArrayHasKey(
            'gte',
            $filterParameter['schema']['properties']['priority']['oneOf'][1]['properties']
        );
        $this->assertArrayNotHasKey(
            'contains',
            $filterParameter['schema']['properties']['is_active']['oneOf'][1]['properties']
        );
    }
}

final class OpenApiStaticAdapter implements DatabaseAdapterInterface
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
