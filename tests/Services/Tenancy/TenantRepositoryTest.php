<?php

declare(strict_types=1);

namespace Tests\Services\Tenancy;

use PachyBase\Config\TenancyConfig;
use PachyBase\Services\Tenancy\TenantRepository;
use RuntimeException;
use Tests\Support\DatabaseIntegrationTestCase;

class TenantRepositoryTest extends DatabaseIntegrationTestCase
{
    public function testDefaultTenantResolvesAndEnsuresBaselineSettings(): void
    {
        $repository = new TenantRepository($this->executor);
        $tenant = $repository->defaultTenant();
        $settings = $this->executor?->select(
            sprintf('SELECT setting_key FROM %s WHERE tenant_id = :tenant_id ORDER BY setting_key ASC', $this->table('pb_system_settings')),
            ['tenant_id' => (int) $tenant['id']]
        ) ?? [];

        $this->assertSame(TenancyConfig::defaultSlug(), $tenant['slug']);
        $this->assertContains('app.name', array_column($settings, 'setting_key'));
        $this->assertContains('api.contract_version', array_column($settings, 'setting_key'));
        $this->assertContains('auth.guard', array_column($settings, 'setting_key'));
        $this->assertContains('database.default_driver', array_column($settings, 'setting_key'));
    }

    public function testResolveReferenceSupportsTenantIdAndSlug(): void
    {
        $tenant = $this->createTenant();
        $repository = new TenantRepository($this->executor);

        $bySlug = $repository->resolveReference($tenant['slug']);
        $byId = $repository->resolveReference((string) $tenant['id']);

        $this->assertSame($tenant['id'], (int) $bySlug['id']);
        $this->assertSame($tenant['slug'], $byId['slug']);
    }

    public function testResolveReferenceThrowsWhenTenantDoesNotExist(): void
    {
        $repository = new TenantRepository($this->executor);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(404);

        $repository->resolveReference('missing-tenant-' . bin2hex(random_bytes(3)));
    }
}
