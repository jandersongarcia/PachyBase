<?php

declare(strict_types=1);

namespace Tests\Database;

use PachyBase\Config;
use PachyBase\Database\Metadata\EntityIntrospector;
use PachyBase\Database\Metadata\FileMetadataCache;
use PachyBase\Database\Metadata\InMemoryMetadataCache;
use PachyBase\Database\Schema\ColumnDefinition;
use PachyBase\Database\Schema\PrimaryKeyDefinition;
use PachyBase\Database\Schema\TableDefinition;
use PachyBase\Database\Schema\TableSchema;
use PHPUnit\Framework\TestCase;

class EntityIntrospectorTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::reset();
    }

    public function testBuildsEntityMetadataFromRawTableSchema(): void
    {
        $tableSchema = new TableSchema(
            new TableDefinition('pb_system_settings', 'public'),
            [
                new ColumnDefinition('id', 'bigint', 'bigint', false, null, true),
                new ColumnDefinition('setting_key', 'varchar', 'string', false, null, false, 120),
                new ColumnDefinition('setting_value', 'text', 'text', true),
                new ColumnDefinition('value_type', 'varchar', 'string', false, 'string', false, 40),
                new ColumnDefinition('created_at', 'timestamptz', 'datetime', false, 'CURRENT_TIMESTAMP'),
                new ColumnDefinition('updated_at', 'timestamptz', 'datetime', false, 'CURRENT_TIMESTAMP'),
            ],
            new PrimaryKeyDefinition('pb_system_settings_pkey', ['id']),
            [],
            []
        );

        $entity = (new EntityIntrospector())->buildDefinition($tableSchema);

        $this->assertSame('system_setting', $entity->name);
        $this->assertSame('pb_system_settings', $entity->table);
        $this->assertSame('id', $entity->primaryField);
        $this->assertSame(['setting_key'], $entity->requiredFields());
        $this->assertSame(['id', 'created_at', 'updated_at'], $entity->readOnlyFields());
        $this->assertTrue($entity->field('id')?->primary);
        $this->assertTrue($entity->field('id')?->readOnly);
        $this->assertFalse($entity->field('id')?->required);
        $this->assertTrue($entity->field('setting_key')?->required);
        $this->assertFalse($entity->field('setting_key')?->readOnly);
        $this->assertTrue($entity->field('setting_value')?->nullable);
        $this->assertSame('string', $entity->field('value_type')?->defaultValue);
        $this->assertTrue($entity->field('created_at')?->readOnly);
    }

    public function testPreservesStableEntityNamesForSystemTables(): void
    {
        $tableSchema = new TableSchema(
            new TableDefinition('pachybase_migrations', 'public'),
            [
                new ColumnDefinition('id', 'bigint', 'bigint', false, null, true),
            ],
            new PrimaryKeyDefinition('pachybase_migrations_pkey', ['id']),
            [],
            []
        );

        $entity = (new EntityIntrospector())->buildDefinition($tableSchema);

        $this->assertSame('migration', $entity->name);
    }

    public function testUsesInMemoryCacheOutsideProduction(): void
    {
        Config::override(['APP_ENV' => 'development']);

        $cache = $this->cacheFor(new EntityIntrospector());

        $this->assertInstanceOf(InMemoryMetadataCache::class, $cache);
    }

    public function testUsesFileCacheInProduction(): void
    {
        Config::override(['APP_ENV' => 'production']);

        $cache = $this->cacheFor(new EntityIntrospector());

        $this->assertInstanceOf(FileMetadataCache::class, $cache);
    }

    private function cacheFor(EntityIntrospector $introspector): object
    {
        $reflection = new \ReflectionProperty($introspector, 'cache');

        return $reflection->getValue($introspector);
    }
}
