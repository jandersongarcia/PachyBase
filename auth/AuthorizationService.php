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
        return $this->authorize(
            $request,
            [
                sprintf('crud:%s', $action),
                sprintf('entity:%s:%s', $entity, $action),
            ],
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
