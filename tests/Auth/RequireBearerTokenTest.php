<?php

declare(strict_types=1);

namespace Tests\Auth;

use PachyBase\Auth\AuthPrincipal;
use PachyBase\Auth\BearerTokenAuthenticator;
use PachyBase\Auth\Middleware\RequireBearerToken;
use PachyBase\Http\AuthenticationException;
use PachyBase\Http\Request;
use PHPUnit\Framework\TestCase;

class RequireBearerTokenTest extends TestCase
{
    public function testThrowsWhenAuthorizationHeaderIsMissing(): void
    {
        $middleware = new RequireBearerToken(new class extends BearerTokenAuthenticator {
            public function authenticate(Request $request): AuthPrincipal
            {
                return parent::authenticate($request);
            }
        });

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Bearer token is missing or invalid.');

        $middleware->handle(new Request('GET', '/private'), static fn() => null);
    }

    public function testAllowsRequestWhenBearerTokenExists(): void
    {
        $principal = new AuthPrincipal('api_token', 'api_token', 7, 3, ['crud:read']);
        $middleware = new RequireBearerToken(new class($principal) extends BearerTokenAuthenticator {
            public function __construct(
                private readonly AuthPrincipal $principal
            ) {
            }

            public function authenticate(Request $request): AuthPrincipal
            {
                return $this->principal;
            }
        });
        $called = false;
        $request = new Request('GET', '/private', [], ['Authorization' => 'Bearer token-123']);

        $middleware->handle(
            $request,
            static function () use (&$called): void {
                $called = true;
            }
        );

        $this->assertTrue($called);
        $this->assertSame($principal, $request->attribute('auth.principal'));
    }
}
