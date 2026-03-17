<?php

declare(strict_types=1);

namespace PachyBase\Auth;

use PachyBase\Http\AuthenticationException;
use PachyBase\Http\Request;

class BearerTokenAuthenticator
{
    public function __construct(
        private readonly ?AuthService $authService = null
    ) {
    }

    public function authenticate(Request $request): AuthPrincipal
    {
        $authorization = trim((string) $request->header('Authorization', ''));

        if ($authorization === '' || !preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            throw new AuthenticationException(
                'Bearer token is missing or invalid.',
                'INVALID_TOKEN'
            );
        }

        return ($this->authService ?? new AuthService())->authenticateBearerToken(trim($matches[1]));
    }
}
