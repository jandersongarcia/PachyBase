<?php

declare(strict_types=1);

namespace Tests\Services\Tenancy;

use PachyBase\Database\Connection;
use PachyBase\Modules\Crud\CrudEntity;
use PachyBase\Modules\Crud\CrudEntityRegistry;
use PachyBase\Services\Tenancy\TenantQuotaService;
use RuntimeException;
use Tests\Support\DatabaseIntegrationTestCase;

class TenantQuotaServiceTest extends DatabaseIntegrationTestCase
{
    private ?string $entityTable = null;

    protected function tearDown(): void
    {
        if ($this->entityTable !== null && $this->executor !== null) {
            $this->executor->execute(sprintf('DROP TABLE IF EXISTS %s', $this->table($this->entityTable)));
        }

        $this->entityTable = null;

        parent::tearDown();
    }

    public function testConsumeRequestTracksUsageAndSnapshot(): void
    {
        $tenant = $this->createTenant(quota: ['max_requests_per_month' => 5]);
        $service = new TenantQuotaService($this->executor);

        $service->consumeRequest($tenant['id']);
        $service->consumeRequest($tenant['id']);
        $snapshot = $service->snapshot($tenant['id']);

        $this->assertSame(5, $snapshot['requests']['limit']);
        $this->assertSame(2, $snapshot['requests']['used']);
        $this->assertSame(gmdate('Y-m'), $snapshot['requests']['period']);
    }

    public function testConsumeRequestThrowsWhenMonthlyQuotaIsExceeded(): void
    {
        $tenant = $this->createTenant(quota: ['max_requests_per_month' => 1]);
        $service = new TenantQuotaService($this->executor);

        $service->consumeRequest($tenant['id']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(429);

        $service->consumeRequest($tenant['id']);
    }

    public function testAssertCanIssueApiTokenRejectsExhaustedTokenQuota(): void
    {
        $tenant = $this->createTenant(quota: ['max_tokens' => 1]);
        $service = new TenantQuotaService($this->executor);

        $this->executor?->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, name, token_hash, token_prefix, scopes, is_active) VALUES (:tenant_id, :name, :token_hash, :token_prefix, :scopes, :is_active)',
                $this->table('pb_api_tokens')
            ),
            [
                'tenant_id' => $tenant['id'],
                'name' => 'Quota Token',
                'token_hash' => hash('sha256', 'quota-token-' . $tenant['slug']),
                'token_prefix' => 'quota-token',
                'scopes' => '["*"]',
                'is_active' => true,
            ]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(429);

        $service->assertCanIssueApiToken($tenant['id']);
    }

    public function testAssertCanCreateEntityRejectsWhenTenantEntityQuotaIsReached(): void
    {
        $tenant = $this->createTenant(quota: ['max_entities' => 1]);
        $this->entityTable = 'pb_quota_entities_' . bin2hex(random_bytes(3));
        $this->createEntityFixtureTable($this->entityTable);
        $this->executor?->execute(
            sprintf('INSERT INTO %s (tenant_id, name) VALUES (:tenant_id, :name)', $this->table($this->entityTable)),
            ['tenant_id' => $tenant['id'], 'name' => 'Quota Row']
        );

        $registry = new CrudEntityRegistry([
            new CrudEntity(
                slug: 'quota-entities',
                table: $this->entityTable,
                tenantScoped: true
            ),
        ]);
        $service = new TenantQuotaService($this->executor, $registry);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(429);

        $service->assertCanCreateEntity($tenant['id']);
    }

    private function createEntityFixtureTable(string $tableName): void
    {
        if (Connection::getInstance()->driver() === 'pgsql') {
            $this->executor?->execute(
                sprintf(
                    'CREATE TABLE "%s" ("id" BIGSERIAL PRIMARY KEY, "tenant_id" BIGINT NOT NULL, "name" VARCHAR(120) NOT NULL)',
                    $tableName
                )
            );

            return;
        }

        $this->executor?->execute(
            sprintf(
                'CREATE TABLE `%s` (`id` BIGINT AUTO_INCREMENT PRIMARY KEY, `tenant_id` BIGINT NOT NULL, `name` VARCHAR(120) NOT NULL)',
                $tableName
            )
        );
    }
}
