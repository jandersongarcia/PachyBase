<?php

declare(strict_types=1);

namespace PachyBase\Services\Tenancy;

use PachyBase\Auth\AuthPrincipal;
use PachyBase\Config\TenancyConfig;
use PachyBase\Http\Request;
use RuntimeException;

final class TenantRequestResolver
{
    public function __construct(
        private readonly ?TenantRepository $tenants = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function resolve(Request $request, array $payload = []): array
    {
        return $this->repository()->resolveReference($this->reference($request, $payload));
    }

    public function assertMatchesPrincipal(Request $request, AuthPrincipal $principal): void
    {
        $reference = $this->reference($request);

        if ($reference === null || trim($reference) === '') {
            return;
        }

        $normalized = strtolower(trim($reference));

        if (
            ($principal->tenantId !== null && ctype_digit($normalized) && (int) $normalized === $principal->tenantId)
            || ($principal->tenantSlug !== null && $normalized === strtolower($principal->tenantSlug))
        ) {
            return;
        }

        throw new RuntimeException('The tenant selected in the request does not match the authenticated credential.', 403);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function reference(Request $request, array $payload = []): ?string
    {
        $header = trim((string) $request->header(TenancyConfig::headerName(), ''));

        if ($header !== '') {
            return $header;
        }

        $payloadReference = trim((string) ($payload['tenant'] ?? ''));

        return $payloadReference !== '' ? $payloadReference : null;
    }

    private function repository(): TenantRepository
    {
        return $this->tenants ?? new TenantRepository();
    }
}
