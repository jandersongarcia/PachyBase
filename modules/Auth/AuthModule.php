<?php

declare(strict_types=1);

namespace PachyBase\Modules\Auth;

use PachyBase\Api\Controllers\AuthController;
use PachyBase\Auth\Middleware\RequireBearerToken;
use PachyBase\Http\Router;

final class AuthModule
{
    public function register(Router $router): void
    {
        $router->post('/api/auth/login', [AuthController::class, 'login']);
        $router->post('/api/auth/refresh', [AuthController::class, 'refresh']);
        $router->post('/api/auth/revoke', [AuthController::class, 'revoke']);
        $router->get('/api/auth/me', [AuthController::class, 'me'])->middleware([RequireBearerToken::class]);
        $router->post('/api/auth/tokens', [AuthController::class, 'issueApiToken'])->middleware([RequireBearerToken::class]);
        $router->delete('/api/auth/tokens/{id}', [AuthController::class, 'revokeApiToken'])->middleware([RequireBearerToken::class]);
    }
}
