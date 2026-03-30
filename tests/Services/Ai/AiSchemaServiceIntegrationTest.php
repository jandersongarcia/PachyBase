<?php

declare(strict_types=1);

namespace Tests\Services\Ai;

use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Metadata\EntityIntrospector;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Schema\SchemaInspector;
use PachyBase\Modules\Crud\CrudEntity;
use PachyBase\Modules\Crud\CrudEntityRegistry;
use PachyBase\Services\Ai\AiSchemaService;
use PHPUnit\Framework\TestCase;

class AiSchemaServiceIntegrationTest extends TestCase
{
    private ?PdoQueryExecutor $executor = null;
    private ?string $tableName = null;

    protected function setUp(): void
    {
        Config::load(dirname(__DIR__, 3));

        $this->executor = new PdoQueryExecutor(Connection::getInstance()->getPDO());
        $this->tableName = 'pb_phase10_records_' . bin2hex(random_bytes(4));
        $this->createFixture();
    }

    protected function tearDown(): void
    {
        if ($this->executor !== null && $this->tableName !== null) {
            $adapter = AdapterFactory::make();
            $this->executor->execute(sprintf('DROP TABLE IF EXISTS %s', $adapter->quoteIdentifier($this->tableName)));
        }

        Connection::reset();
        Config::reset();
    }

    public function testBuildsMachineReadableSchemaForConfiguredEntity(): void
    {
        $service = $this->service();

        $schema = $service->buildSchema();
        $list = $service->listEntities();
        $entity = $service->describeEntity('phase10-records');
        $fields = [];

        foreach ($entity['fields'] as $field) {
            $fields[$field['name']] = $field;
        }

        $operations = [];
        foreach ($entity['operations'] as $operation) {
            $operations[$operation['name']] = $operation;
        }

        $this->assertSame('1.0', $schema['schema_version']);
        $this->assertSame('/ai/entities', $schema['navigation']['entities_url']);
        $this->assertSame('/openapi.json', $schema['openapi_compatibility']['document_url']);
        $this->assertCount(1, $schema['entities']);

        $this->assertSame(1, $list['count']);
        $this->assertSame('phase10-records', $list['items'][0]['name']);
        $this->assertContains('create', $list['items'][0]['operations_available']);

        $this->assertSame('phase10-records', $entity['name']);
        $this->assertSame('/api/phase10-records', $entity['paths']['collection']);
        $this->assertSame('/api/phase10-records/{id}', $entity['paths']['item']);
        $this->assertSame(40, $entity['pagination']['max_per_page']);
        $this->assertSame(['title', 'status'], $entity['filters']['field_filters']['fields']);
        $this->assertSame(['title', 'notes'], $entity['filters']['search_parameter']['fields']);
        $this->assertSame(['-title'], $entity['filters']['sort_parameter']['default']);
        $this->assertSame('#/components/schemas/CrudPhase10RecordsItem', $entity['openapi']['component_refs']['item']);

        $this->assertTrue($fields['title']['required']);
        $this->assertTrue($fields['title']['required_on_create']);
        $this->assertTrue($fields['published_on']['required_on_replace']);
        $this->assertFalse($fields['published_on']['required_on_patch']);
        $this->assertTrue($fields['created_at']['readonly']);
        $this->assertFalse($fields['created_at']['writable']);
        $this->assertTrue($fields['title']['filterable']);
        $this->assertTrue($fields['title']['searchable']);
        $this->assertTrue($fields['title']['sortable']);
        $this->assertSame('date', $fields['published_on']['openapi']['format']);

        $this->assertTrue($operations['list']['available']);
        $this->assertSame(['crud:read', 'entity:phase10-records:read'], $operations['list']['required_scopes']);
        $this->assertFalse($operations['delete']['available']);
        $this->assertSame(['crud:delete', 'entity:phase10-records:delete'], $operations['delete']['required_scopes']);
    }

    private function service(): AiSchemaService
    {
        $adapter = AdapterFactory::make();
        $registry = new CrudEntityRegistry([
            new CrudEntity(
                slug: 'phase10-records',
                table: (string) $this->tableName,
                searchableFields: ['title', 'notes'],
                filterableFields: ['title', 'status'],
                sortableFields: ['title', 'created_at'],
                hiddenFields: [],
                defaultSort: ['-title'],
                validationRules: [
                    'title' => ['min' => 3, 'max' => 120],
                    'status' => ['enum' => ['draft', 'published']],
                    'published_on' => ['required_on_replace' => true],
                ],
                exposed: true,
                allowDelete: false,
                allowedFields: ['title', 'notes', 'status', 'published_on'],
                maxPerPage: 40,
                readOnlyFields: ['created_at', 'updated_at']
            ),
        ]);

        return new AiSchemaService(
            $registry,
            new EntityIntrospector(new SchemaInspector($adapter))
        );
    }

    private function createFixture(): void
    {
        $driver = Connection::getInstance()->driver();

        if ($driver === 'pgsql') {
            $this->executor?->execute(
                sprintf(
                    "CREATE TABLE \"%s\" (\"id\" BIGSERIAL PRIMARY KEY, \"title\" VARCHAR(120) NOT NULL, \"notes\" TEXT NULL, \"status\" VARCHAR(30) NOT NULL DEFAULT 'draft', \"published_on\" DATE NULL, \"created_at\" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, \"updated_at\" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP)",
                    $this->tableName
                )
            );

            return;
        }

        $this->executor?->execute(
            sprintf(
                "CREATE TABLE `%s` (`id` BIGINT AUTO_INCREMENT PRIMARY KEY, `title` VARCHAR(120) NOT NULL, `notes` TEXT NULL, `status` VARCHAR(30) NOT NULL DEFAULT 'draft', `published_on` DATE NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)",
                $this->tableName
            )
        );
    }
}
