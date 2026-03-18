<?php

declare(strict_types=1);

namespace Tests\Database;

use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Metadata\EntityIntrospector;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Schema\SchemaInspector;
use PHPUnit\Framework\TestCase;

class EntityIntrospectorIntegrationTest extends TestCase
{
    private ?PdoQueryExecutor $executor = null;
    private string $table = 'pb_phase4_records';

    protected function setUp(): void
    {
        Config::load(dirname(__DIR__, 2));
        $this->executor = new PdoQueryExecutor(Connection::getInstance()->getPDO());
        $this->dropFixture();
        $this->createFixture();
    }

    protected function tearDown(): void
    {
        $this->dropFixture();
        Connection::reset();
        Config::reset();
    }

    public function testInspectsEntityMetadataFromActiveDatabaseDriver(): void
    {
        $introspector = new EntityIntrospector(new SchemaInspector(AdapterFactory::make()));
        $entity = $introspector->inspectTable($this->table);

        $this->assertSame('phase4_record', $entity->name);
        $this->assertSame($this->table, $entity->table);
        $this->assertSame('id', $entity->primaryField);
        $this->assertSame(['title'], $entity->requiredFields());
        $this->assertSame(['id', 'created_at', 'updated_at'], $entity->readOnlyFields());
        $this->assertSame('string', $entity->field('title')?->type);
        $this->assertTrue($entity->field('notes')?->nullable);
        $this->assertSame('draft', $entity->field('status')?->defaultValue);
    }

    public function testCachesDefinitionsWithinSingleRuntime(): void
    {
        $introspector = new EntityIntrospector(new SchemaInspector(AdapterFactory::make()));

        $first = $introspector->inspectTable($this->table);
        $second = $introspector->inspectTable($this->table);

        $this->assertSame($first, $second);
    }

    private function createFixture(): void
    {
        $driver = Connection::getInstance()->driver();

        if ($driver === 'pgsql') {
            $this->executor?->execute(
                <<<SQL
                CREATE TABLE "pb_phase4_records" (
                    "id" BIGSERIAL PRIMARY KEY,
                    "title" VARCHAR(120) NOT NULL,
                    "notes" TEXT NULL,
                    "status" VARCHAR(30) NOT NULL DEFAULT 'draft',
                    "created_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    "updated_at" TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
                SQL
            );

            return;
        }

        $this->executor?->execute(
            <<<SQL
            CREATE TABLE `pb_phase4_records` (
                `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(120) NOT NULL,
                `notes` TEXT NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL
        );
    }

    private function dropFixture(): void
    {
        if ($this->executor === null) {
            return;
        }

        $driver = Connection::getInstance()->driver();

        if ($driver === 'pgsql') {
            $this->executor->execute('DROP TABLE IF EXISTS "pb_phase4_records"');

            return;
        }

        $this->executor->execute('DROP TABLE IF EXISTS `pb_phase4_records`');
    }
}
