<?php

declare(strict_types=1);

namespace Tests\Services\Tenancy;

use PachyBase\Auth\AuthPrincipal;
use PachyBase\Http\Request;
use PachyBase\Services\Tenancy\TenantRepository;
use PachyBase\Services\Tenancy\TenantRequestResolver;
use RuntimeException;
use Tests\Support\DatabaseIntegrationTestCase;

class TenantRequestResolverTest extends DatabaseIntegrationTestCase
{
    public function testReferencePrefersHeaderBeforePayload(): void
    {
        $resolver = new TenantRequestResolver();
        $request = new Request('POST', '/api/test', [], ['X-Tenant-Id' => 'header-tenant']);

        $reference = $resolver->reference($request, ['tenant' => 'payload-tenant']);

        $this->assertSame('header-tenant', $reference);
    }

    public function testResolveSupportsHeaderAndPayloadReferences(): void
    {
        $tenant = $this->createTenant();
        $resolver = new TenantRequestResolver(new TenantRepository($this->executor));

        $headerResolved = $resolver->resolve(new Request('GET', '/api/test', [], ['X-Tenant-Id' => $tenant['slug']]));
        $payloadResolved = $resolver->resolve(new Request('POST', '/api/test'), ['tenant' => (string) $tenant['id']]);

        $this->assertSame($tenant['id'], (int) $headerResolved['id']);
        $this->assertSame($tenant['slug'], $payloadResolved['slug']);
    }

    public function testAssertMatchesPrincipalAllowsMatchingTenantAndRejectsMismatches(): void
    {
        $tenant = $this->createTenant();
        $resolver = new TenantRequestResolver();
        $principal = new AuthPrincipal(
            provider: 'jwt',
            subjectType: 'user',
            subjectId: 10,
            userId: 10,
            scopes: ['*'],
            tenantId: $tenant['id'],
            tenantSlug: $tenant['slug'],
            tokenId: null,
            email: 'tenant@example.com',
            name: 'Tenant User',
            role: 'admin'
        );

        $resolver->assertMatchesPrincipal(new Request('GET', '/api/test', [], ['X-Tenant-Id' => $tenant['slug']]), $principal);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(403);

        $resolver->assertMatchesPrincipal(new Request('GET', '/api/test', [], ['X-Tenant-Id' => 'wrong-tenant']), $principal);
    }
}
