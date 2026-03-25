<?php

declare(strict_types=1);

namespace PachyBase\Api\Controllers;

use PachyBase\Auth\AuthPrincipal;
use PachyBase\Auth\AuthService;
use PachyBase\Auth\AuthorizationService;
use PachyBase\Auth\BearerTokenAuthenticator;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;
use PachyBase\Http\ValidationException;
use PachyBase\Services\Audit\AuditLogger;

final class AuthController
{
    public function __construct(
        private readonly ?AuthService $authService = null,
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?AuditLogger $auditLogger = null
    ) {
    }

    public function login(Request $request): void
    {
        $result = $this->service()->login($request->json());
        $this->audit()->logAuth('auth.login.succeeded', $request, [
            'resource' => 'auth.login',
            'user' => $result['user'] ?? null,
        ], 200);

        ApiResponse::success(
            $result,
            ['resource' => 'auth.login']
        );
    }

    public function refresh(Request $request): void
    {
        $result = $this->service()->refresh($request->json());
        $this->audit()->logAuth('auth.refresh.succeeded', $request, [
            'resource' => 'auth.refresh',
            'user' => $result['user'] ?? null,
        ], 200);

        ApiResponse::success(
            $result,
            ['resource' => 'auth.refresh']
        );
    }

    public function revoke(Request $request): void
    {
        $refreshToken = trim((string) $request->json('refresh_token', ''));

        if ($refreshToken !== '') {
            $result = $this->service()->revokeRefreshToken($refreshToken);
            $this->audit()->logAuth('auth.revoke.refresh_token', $request, [
                'resource' => 'auth.revoke',
                'result' => $result,
            ], 200);

            ApiResponse::success(
                $result,
                ['resource' => 'auth.revoke']
            );
        }

        $principal = $request->attribute('auth.principal');

        if (!$principal instanceof AuthPrincipal) {
            $authorization = trim((string) $request->header('Authorization', ''));

            if ($authorization !== '') {
                $principal = (new BearerTokenAuthenticator($this->service()))->authenticate($request);
            }
        }

        if (!$principal instanceof AuthPrincipal) {
            throw new ValidationException(details: [[
                'field' => 'refresh_token',
                'code' => 'required',
                'message' => 'Provide a refresh_token or an authenticated bearer token to revoke credentials.',
            ]]);
        }

        $result = $this->service()->revokeCurrent($principal);
        $this->audit()->logAuth('auth.revoke.current', $request, [
            'resource' => 'auth.revoke',
            'result' => $result,
        ], 200);

        ApiResponse::success(
            $result,
            ['resource' => 'auth.revoke']
        );
    }

    public function me(Request $request): void
    {
        /** @var AuthPrincipal $principal */
        $principal = $request->attribute('auth.principal');
        $this->audit()->logAuth('auth.me.succeeded', $request, [
            'resource' => 'auth.me',
            'principal' => $principal->toArray(),
        ], 200);

        ApiResponse::success(
            [
                'authenticated' => true,
                'principal' => $principal->toArray(),
            ],
            ['resource' => 'auth.me']
        );
    }

    public function issueApiToken(Request $request): void
    {
        $principal = $this->authorization()->authorize(
            $request,
            ['auth:tokens:create', 'auth:manage'],
            'You do not have permission to create API tokens.'
        );

        $result = $this->service()->issueApiToken($principal, $request->json());
        $this->audit()->logAuth('auth.tokens.created', $request, [
            'resource' => 'auth.tokens.store',
            'token_id' => $result['token_id'] ?? null,
            'name' => $result['name'] ?? null,
            'expires_at' => $result['expires_at'] ?? null,
        ], 201);

        ApiResponse::success(
            $result,
            ['resource' => 'auth.tokens.store'],
            201
        );
    }

    public function revokeApiToken(Request $request, string $id): void
    {
        $principal = $this->authorization()->authorize(
            $request,
            ['auth:tokens:revoke', 'auth:manage'],
            'You do not have permission to revoke API tokens.'
        );

        $result = $this->service()->revokeApiToken($principal, (int) $id);
        $this->audit()->logAuth('auth.tokens.revoked', $request, [
            'resource' => 'auth.tokens.destroy',
            'token_id' => (int) $id,
            'result' => $result,
        ], 200);

        ApiResponse::success(
            $result,
            ['resource' => 'auth.tokens.destroy']
        );
    }

    private function service(): AuthService
    {
        return $this->authService ?? new AuthService();
    }

    private function authorization(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService();
    }

    private function audit(): AuditLogger
    {
        return $this->auditLogger ?? new AuditLogger();
    }
}
