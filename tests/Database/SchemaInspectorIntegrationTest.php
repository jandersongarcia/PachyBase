<?php

declare(strict_types=1);

namespace Tests\Database;

use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Schema\SchemaInspector;
use PHPUnit\Framework\TestCase;

class SchemaInspectorIntegrationTest extends TestCase
{
    private ?PdoQueryExecutor $executor = null;
    private ?string $parentTable = null;
    private ?string $childTable = null;

    protected function setUp(): void
    {
        Config::load(dirname(__DIR__, 2));
        $this->executor = new PdoQueryExecutor(Connection::getInstance()->getPDO());
        $suffix = bin2hex(random_bytes(4));
        $this->parentTable = 'pb_phase2_parent_' . $suffix;
        $this->childTable = 'pb_phase2_child_' . $suffix;
    }

    protected function tearDown(): void
    {
        if ($this->executor !== null && $this->parentTable !== null && $this->childTable !== null) {
            $driver = Connection::getInstance()->driver();

            if ($driver === 'pgsql') {
                $this->executor->execute(sprintf('DROP TABLE IF EXISTS "%s"', $this->childTable));
                $this->executor->execute(sprintf('DROP TABLE IF EXISTS "%s"', $this->parentTable));
            } else {
                $this->executor->execute(sprintf('DROP TABLE IF EXISTS `%s`', $this->childTable));
                $this->executor->execute(sprintf('DROP TABLE IF EXISTS `%s`', $this->parentTable));
            }
        }

        Connection::reset();
        Config::reset();
    }

    public function testInspectsTablesColumnsKeysIndexesAndRelations(): void
    {
        $driver = Connection::getInstance()->driver();

        if ($driver === 'pgsql') {
            $this->createPostgresFixture();
        } else {
            $this->createMySqlFixture();
        }

        $inspector = new SchemaInspector(AdapterFactory::make());
        $databaseSchema = $inspector->inspectDatabase();
        $childSchema = $databaseSchema->table((string) $this->childTable);
        $parentSchema = $databaseSchema->table((string) $this->parentTable);

        $this->assertNotNull($parentSchema);
        $this->assertNotNull($childSchema);
        $this->assertSame(['id'], $parentSchema?->primaryKey?->columns);
        $this->assertNotNull($parentSchema?->column('name'));
        $this->assertNotNull($childSchema?->column('parent_id'));
        $this->assertNotEmpty($parentSchema?->indexes);
        $this->assertNotEmpty($childSchema?->relations);
        $this->assertSame((string) $this->parentTable, $childSchema?->relations[0]->referencedTable);
        $this->assertSame(['parent_id'], $childSchema?->relations[0]->localColumns);
    }

    private function createPostgresFixture(): void
    {
        $this->executor?->execute(
            sprintf(
                'CREATE TABLE "%s" (id BIGSERIAL PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(190) UNIQUE)',
                $this->parentTable
            )
        );
        $this->executor?->execute(
            sprintf(
                'CREATE TABLE "%s" (id BIGSERIAL PRIMARY KEY, parent_id BIGINT NOT NULL, label VARCHAR(120) NOT NULL, CONSTRAINT "%s_fk" FOREIGN KEY (parent_id) REFERENCES "%s"(id) ON DELETE CASCADE ON UPDATE CASCADE)',
                $this->childTable,
                $this->childTable,
                $this->parentTable
            )
        );
        $this->executor?->execute(
            sprintf('CREATE INDEX "%s_parent_idx" ON "%s" (parent_id)', $this->childTable, $this->childTable)
        );
    }

    private function createMySqlFixture(): void
    {
        $this->executor?->execute(
            sprintf(
                'CREATE TABLE `%s` (id BIGINT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(190) NULL, UNIQUE KEY `%s_email_unique` (email))',
                $this->parentTable,
                $this->parentTable
            )
        );
        $this->executor?->execute(
            sprintf(
                'CREATE TABLE `%s` (id BIGINT AUTO_INCREMENT PRIMARY KEY, parent_id BIGINT NOT NULL, label VARCHAR(120) NOT NULL, INDEX `%s_parent_idx` (parent_id), CONSTRAINT `%s_fk` FOREIGN KEY (parent_id) REFERENCES `%s`(id) ON DELETE CASCADE ON UPDATE CASCADE)',
                $this->childTable,
                $this->childTable,
                $this->childTable,
                $this->parentTable
            )
        );
    }
}
