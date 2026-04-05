<?php

declare(strict_types=1);

namespace Tests\Services\Platform;

use PachyBase\Services\Platform\ProjectPlatformService;
use PachyBase\Services\Platform\StorageService;
use RuntimeException;
use Tests\Support\DatabaseIntegrationTestCase;

class ProjectPlatformServiceTest extends DatabaseIntegrationTestCase
{
    public function testProvisionProjectCreatesTenantAdminQuotaAndBootstrapToken(): void
    {
        $service = new ProjectPlatformService($this->executor);
        $slug = 'project-' . bin2hex(random_bytes(3));

        $result = $service->provisionProject([
            'name' => 'Project Platform Test',
            'slug' => $slug,
            'admin_email' => 'admin@' . $slug . '.example',
            'admin_name' => 'Platform Admin',
            'admin_password' => 'phase9-password',
            'settings' => ['feature.mode' => 'integration'],
            'quotas' => [
                'max_requests_per_month' => 2500,
                'max_tokens' => 12,
                'max_entities' => 80,
                'max_storage_bytes' => 4096,
            ],
        ]);

        $tenantId = (int) $result['project']['id'];
        $this->trackTenant($tenantId, $slug);

        $quota = $this->executor?->selectOne(
            sprintf('SELECT * FROM %s WHERE tenant_id = :tenant_id LIMIT 1', $this->table('pb_tenant_quotas')),
            ['tenant_id' => $tenantId]
        );
        $admin = $this->executor?->selectOne(
            sprintf('SELECT * FROM %s WHERE tenant_id = :tenant_id AND email = :email LIMIT 1', $this->table('pb_users')),
            ['tenant_id' => $tenantId, 'email' => 'admin@' . $slug . '.example']
        );
        $setting = $this->executor?->selectOne(
            sprintf('SELECT * FROM %s WHERE tenant_id = :tenant_id AND setting_key = :setting_key LIMIT 1', $this->table('pb_system_settings')),
            ['tenant_id' => $tenantId, 'setting_key' => 'feature.mode']
        );
        $token = $this->executor?->selectOne(
            sprintf('SELECT * FROM %s WHERE tenant_id = :tenant_id LIMIT 1', $this->table('pb_api_tokens')),
            ['tenant_id' => $tenantId]
        );

        $this->assertSame($slug, $result['project']['slug']);
        $this->assertSame('Platform Admin', $result['bootstrap_admin']['name']);
        $this->assertSame('admin@' . $slug . '.example', $result['bootstrap_admin']['email']);
        $this->assertSame('phase9-password', $result['bootstrap_admin']['password']);
        $this->assertStringStartsWith('pbt_', (string) $result['bootstrap_token']);
        $this->assertSame('X-Tenant-Id', $result['tenant_header']);
        $this->assertSame(2500, (int) ($quota['max_requests_per_month'] ?? 0));
        $this->assertSame(12, (int) ($quota['max_tokens'] ?? 0));
        $this->assertSame(80, (int) ($quota['max_entities'] ?? 0));
        $this->assertSame(4096, (int) ($quota['max_storage_bytes'] ?? 0));
        $this->assertSame('Platform Admin', $admin['name']);
        $this->assertSame('integration', $setting['setting_value']);
        $this->assertNotNull($token);
    }

    public function testProvisionProjectRejectsDuplicateSlug(): void
    {
        $slug = 'duplicate-' . bin2hex(random_bytes(3));
        $tenant = $this->createTenant($slug, 'Duplicate Project');
        $this->assertSame($slug, $tenant['slug']);

        $service = new ProjectPlatformService($this->executor);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(409);

        $service->provisionProject([
            'name' => 'Duplicate',
            'slug' => $slug,
        ]);
    }

    public function testBackupRestoreSecretCrudAndFilesystemCleanupWorkTogether(): void
    {
        $service = new ProjectPlatformService($this->executor);
        $storage = new StorageService($this->executor);
        $slug = 'restore-' . bin2hex(random_bytes(3));

        $project = $service->provisionProject([
            'name' => 'Restore Integration',
            'slug' => $slug,
            'quotas' => [
                'max_tokens' => 10,
                'max_storage_bytes' => 8192,
            ],
        ]);

        $tenantId = (int) $project['project']['id'];
        $this->trackTenant($tenantId, $slug);

        $created = $service->putSecret($slug, 'stripe_key', 'secret-v1');
        $updated = $service->putSecret($slug, 'stripe_key', 'secret-v2');
        $revealed = $service->revealSecret($slug, 'stripe_key');
        $listed = $service->listSecrets($slug);
        $storedFile = $storage->store($tenantId, $slug, [
            'filename' => 'state.json',
            'content_type' => 'application/json',
            'content_base64' => base64_encode('{"version":1}'),
        ]);
        $backup = $service->createBackup($slug, null, 'baseline');

        $this->assertTrue($created['updated']);
        $this->assertTrue($updated['updated']);
        $this->assertSame('secret-v2', $revealed['value']);
        $this->assertCount(1, $listed);
        $this->assertFileExists($this->absolutePath((string) $backup['file_path']));

        $service->deleteSecret($slug, 'stripe_key');
        file_put_contents($this->absolutePath((string) $storedFile['relative_path']), '{"version":999}');
        $stalePath = $this->absolutePath('build/storage/projects/' . $slug . '/stale/orphan.txt');
        $staleDirectory = dirname($stalePath);

        if (!is_dir($staleDirectory)) {
            mkdir($staleDirectory, 0777, true);
        }

        file_put_contents($stalePath, 'stale');
        $this->upsertQuota($tenantId, [
            'max_tokens' => 1,
            'max_storage_bytes' => 1024,
        ]);

        $restored = $service->restoreBackup($slug, (int) $backup['id']);
        $restoredSecret = $service->revealSecret($slug, 'stripe_key');
        $download = $storage->download($tenantId, (int) $storedFile['id']);
        $quota = $this->executor?->selectOne(
            sprintf('SELECT * FROM %s WHERE tenant_id = :tenant_id LIMIT 1', $this->table('pb_tenant_quotas')),
            ['tenant_id' => $tenantId]
        );
        $backups = $service->listBackups($slug, 5);

        $this->assertTrue($restored['restored']);
        $this->assertSame('secret-v2', $restoredSecret['value']);
        $this->assertSame(base64_encode('{"version":1}'), $download['content_base64']);
        $this->assertSame(10, (int) ($quota['max_tokens'] ?? 0));
        $this->assertSame(8192, (int) ($quota['max_storage_bytes'] ?? 0));
        $this->assertNotNull($backups[0]['restored_at'] ?? null);
        $this->assertFileDoesNotExist($stalePath);
    }
}
