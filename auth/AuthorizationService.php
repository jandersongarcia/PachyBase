<?php

declare(strict_types=1);

namespace PachyBase\Auth;

use PachyBase\Http\AuthorizationException;
use PachyBase\Http\Request;

final class AuthorizationService
{
    /**
     * @param array<int, string> $requiredScopes
     */
    public function authorize(Request $request, array $requiredScopes, ?string $message = null): AuthPrincipal
    {
        $principal = $request->attribute('auth.principal');

        if (!$principal instanceof AuthPrincipal) {
            throw new AuthorizationException(
                'The authenticated principal is not available for authorization.',
                'AUTH_PRINCIPAL_MISSING'
            );
        }

        $requestTenantId = $request->attribute('auth.tenant_id');
        if (
            $requestTenantId !== null
            && $principal->tenantId !== null
            && (int) $requestTenantId !== $principal->tenantId
        ) {
            throw new AuthorizationException(
                'The authenticated principal cannot access resources from a different tenant.',
                'TENANT_ACCESS_DENIED'
            );
        }

        if (!$this->hasAnyScope($principal, $requiredScopes)) {
            throw new AuthorizationException(
                $message ?? 'You do not have permission to perform this action.',
                'INSUFFICIENT_PERMISSIONS'
            );
        }

        return $principal;
    }

    public function authorizeEntityAction(Request $request, string $entity, string $action): AuthPrincipal
    {
        $tenantId = $request->attribute('auth.tenant_id');
        $tenantScopes = [];

        if ($tenantId !== null) {
            $tenantScopes = [
                sprintf('tenant:%d:*', (int) $tenantId),
                sprintf('tenant:%d:crud:*', (int) $tenantId),
                sprintf('tenant:%d:crud:%s', (int) $tenantId, $action),
                sprintf('tenant:%d:entity:%s:*', (int) $tenantId, $entity),
                sprintf('tenant:%d:entity:%s:%s', (int) $tenantId, $entity, $action),
            ];
        }

        return $this->authorize(
            $request,
            array_merge([
                sprintf('crud:%s', $action),
                'crud:*',
                sprintf('entity:%s:%s', $entity, $action),
                sprintf('entity:%s:*', $entity),
            ], $tenantScopes),
            sprintf('You do not have permission to %s the "%s" entity.', $action, $entity)
        );
    }

    /**
     * @param array<int, string> $requestedScopes
     */
    public function ensureGrantable(AuthPrincipal $principal, array $requestedScopes): void
    {
        if ($requestedScopes === []) {
            return;
        }

        foreach ($requestedScopes as $scope) {
            if (!$this->hasAnyScope($principal, [$scope])) {
                throw new AuthorizationException(
                    sprintf('You do not have permission to grant the "%s" scope.', $scope),
                    'SCOPE_GRANT_NOT_ALLOWED'
                );
            }
        }
    }

    /**
     * @param array<int, string> $requiredScopes
     */
    public function hasAnyScope(AuthPrincipal $principal, array $requiredScopes): bool
    {
        if ($requiredScopes === []) {
            return true;
        }

        foreach ($principal->scopes as $grantedScope) {
            if ($grantedScope === '*' || $grantedScope === 'admin') {
                return true;
            }

            foreach ($requiredScopes as $requiredScope) {
                if ($grantedScope === $requiredScope) {
                    return true;
                }

                if (str_ends_with($grantedScope, '*') && str_starts_with($requiredScope, rtrim($grantedScope, '*'))) {
                    return true;
                }
            }
        }

        return false;
    }
}
