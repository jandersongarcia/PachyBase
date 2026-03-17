<?php

declare(strict_types=1);

namespace PachyBase\Auth\Middleware;

use PachyBase\Auth\BearerTokenAuthenticator;
use PachyBase\Http\Request;

final class RequireBearerToken
{
    public function __construct(
        private readonly ?BearerTokenAuthenticator $authenticator = null
    ) {
    }

    public function handle(Request $request, callable $next): void
    {
        $principal = ($this->authenticator ?? new BearerTokenAuthenticator())->authenticate($request);
        $request->setAttribute('auth.principal', $principal);
        $next();
    }
}
