<?php

declare(strict_types=1);

namespace Tests\Services\Platform;

use PachyBase\Services\Platform\StorageService;
use RuntimeException;
use Tests\Support\DatabaseIntegrationTestCase;

class StorageServiceTest extends DatabaseIntegrationTestCase
{
    public function testStoreAndDownloadPersistBinaryMetadataAndSanitizeObjectKey(): void
    {
        $tenant = $this->createTenant(quota: ['max_storage_bytes' => 8192]);
        $service = new StorageService($this->executor);

        $stored = $service->store($tenant['id'], $tenant['slug'], [
            'filename' => 'hello world?.txt',
            'content_type' => 'text/plain',
            'content_base64' => base64_encode('hello storage'),
            'metadata' => ['source' => 'phpunit'],
        ]);
        $download = $service->download($tenant['id'], (int) $stored['id']);

        $this->assertSame('hello world?.txt', $stored['original_name']);
        $this->assertStringContainsString($tenant['slug'], (string) $stored['relative_path']);
        $this->assertStringNotContainsString(' ', (string) $stored['object_key']);
        $this->assertStringNotContainsString('?', (string) $stored['object_key']);
        $this->assertFileExists($this->absolutePath((string) $stored['relative_path']));
        $this->assertSame(base64_encode('hello storage'), $download['content_base64']);
    }

    public function testStoreRejectsInvalidBase64Payload(): void
    {
        $tenant = $this->createTenant();
        $service = new StorageService($this->executor);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(422);

        $service->store($tenant['id'], $tenant['slug'], [
            'filename' => 'invalid.txt',
            'content_base64' => '%%%not-base64%%%',
        ]);
    }

    public function testStoreRejectsWhenQuotaWouldBeExceeded(): void
    {
        $tenant = $this->createTenant(quota: ['max_storage_bytes' => 4]);
        $service = new StorageService($this->executor);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(429);

        $service->store($tenant['id'], $tenant['slug'], [
            'filename' => 'quota.txt',
            'content_base64' => base64_encode('12345'),
        ]);
    }

    public function testDownloadFailsWhenBlobIsMissingFromDisk(): void
    {
        $tenant = $this->createTenant(quota: ['max_storage_bytes' => 8192]);
        $service = new StorageService($this->executor);
        $stored = $service->store($tenant['id'], $tenant['slug'], [
            'filename' => 'gone.txt',
            'content_base64' => base64_encode('gone soon'),
        ]);

        unlink($this->absolutePath((string) $stored['relative_path']));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(500);

        $service->download($tenant['id'], (int) $stored['id']);
    }
}
