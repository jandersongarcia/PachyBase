<?php

declare(strict_types=1);

namespace Tests\Database;

use PachyBase\Config;
use PachyBase\Database\Metadata\EntityDefinition;
use PachyBase\Database\Metadata\FieldDefinition;
use PachyBase\Database\Metadata\FileMetadataCache;
use PHPUnit\Framework\TestCase;

class FileMetadataCacheTest extends TestCase
{
    private string $cacheDirectory;

    protected function setUp(): void
    {
        $this->cacheDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-metadata-cache-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        Config::reset();

        if (!is_dir($this->cacheDirectory)) {
            return;
        }

        $files = glob($this->cacheDirectory . DIRECTORY_SEPARATOR . '*');

        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        @rmdir($this->cacheDirectory);
    }

    public function testPersistsEntityDefinitionsAcrossCacheInstances(): void
    {
        $firstCache = new FileMetadataCache($this->cacheDirectory, 'test-schema');
        $entity = new EntityDefinition(
            'system_setting',
            'pb_system_settings',
            'public',
            'id',
            [
                new FieldDefinition('id', 'id', 'bigint', 'int8', true, false, true, false, null, true),
                new FieldDefinition('setting_key', 'setting_key', 'string', 'varchar', false, true, false, false, null, false, 120),
            ]
        );

        $firstCache->put($entity);

        $secondCache = new FileMetadataCache($this->cacheDirectory, 'test-schema');
        $cached = $secondCache->get('pb_system_settings');

        $this->assertInstanceOf(EntityDefinition::class, $cached);
        $this->assertSame('system_setting', $cached?->name);
        $this->assertSame('id', $cached?->primaryField);
        $this->assertSame(['setting_key'], $cached?->requiredFields());
    }

    public function testClearRemovesPersistedDefinitions(): void
    {
        $cache = new FileMetadataCache($this->cacheDirectory, 'test-schema');
        $cache->put(
            new EntityDefinition(
                'system_setting',
                'pb_system_settings',
                'public',
                'id',
                [new FieldDefinition('id', 'id', 'bigint', 'int8', true, false, true, false)]
            )
        );

        $cache->clear();

        $this->assertNull((new FileMetadataCache($this->cacheDirectory, 'test-schema'))->get('pb_system_settings'));
    }

    public function testInvalidPersistedPayloadIsDiscarded(): void
    {
        Config::override([
            'DB_DRIVER' => 'mysql',
            'DB_DATABASE' => 'pachybase',
        ]);

        $cache = new FileMetadataCache($this->cacheDirectory, 'test-schema');
        $cache->put(
            new EntityDefinition(
                'system_setting',
                'pb_system_settings',
                'public',
                'id',
                [new FieldDefinition('id', 'id', 'bigint', 'int8', true, false, true, false)]
            )
        );

        $cacheFile = glob($this->cacheDirectory . DIRECTORY_SEPARATOR . '*.json')[0] ?? null;

        $this->assertNotNull($cacheFile);

        file_put_contents((string) $cacheFile, '{"invalid":true}');

        $this->assertNull((new FileMetadataCache($this->cacheDirectory, 'test-schema'))->get('pb_system_settings'));
        $this->assertFileDoesNotExist((string) $cacheFile);
    }
}
