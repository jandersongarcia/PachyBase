<?php

declare(strict_types=1);

namespace Tests\Auth;

use PachyBase\Auth\AuthPrincipal;
use PachyBase\Auth\AuthorizationService;
use PachyBase\Http\AuthorizationException;
use PachyBase\Http\Request;
use PHPUnit\Framework\TestCase;

class AuthorizationServiceTest extends TestCase
{
    public function testAcceptsWildcardScopesForEntityAuthorization(): void
    {
        $request = (new Request('GET', '/api/system-settings'))
            ->setAttribute('auth.principal', new AuthPrincipal('jwt', 'user', 1, 1, ['entity:system-settings:*']));

        $principal = (new AuthorizationService())->authorizeEntityAction($request, 'system-settings', 'read');

        $this->assertSame(1, $principal->userId);
    }

    public function testDeniesEntityActionByDefault(): void
    {
        $request = (new Request('DELETE', '/api/system-settings/1'))
            ->setAttribute('auth.principal', new AuthPrincipal('jwt', 'user', 1, 1, ['crud:read']));

        $this->expectException(AuthorizationException::class);

        (new AuthorizationService())->authorizeEntityAction($request, 'system-settings', 'delete');
    }
}
