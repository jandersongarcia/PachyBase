<?php

declare(strict_types=1);

namespace PachyBase\Auth\Middleware;

use PachyBase\Auth\BearerTokenAuthenticator;
use PachyBase\Http\Request;
use PachyBase\Services\Tenancy\TenantQuotaService;
use PachyBase\Services\Tenancy\TenantRequestResolver;

final class RequireBearerToken
{
    public function __construct(
        private readonly ?BearerTokenAuthenticator $authenticator = null,
        private readonly ?TenantRequestResolver $tenants = null,
        private readonly ?TenantQuotaService $quotas = null
    ) {
    }

    public function handle(Request $request, callable $next): void
    {
        $principal = ($this->authenticator ?? new BearerTokenAuthenticator())->authenticate($request);
        ($this->tenants ?? new TenantRequestResolver())->assertMatchesPrincipal($request, $principal);
        $request->setAttribute('auth.principal', $principal);
        $request->setAttribute('auth.tenant_id', $principal->tenantId);
        $request->setAttribute('auth.tenant_slug', $principal->tenantSlug);

        if ($principal->tenantId !== null) {
            ($this->quotas ?? new TenantQuotaService())->consumeRequest($principal->tenantId);
        }

        $next();
    }
}
