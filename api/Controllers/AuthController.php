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

final class AuthController
{
    public function __construct(
        private readonly ?AuthService $authService = null,
        private readonly ?AuthorizationService $authorization = null
    ) {
    }

    public function login(Request $request): void
    {
        ApiResponse::success(
            $this->service()->login($request->json()),
            ['resource' => 'auth.login']
        );
    }

    public function refresh(Request $request): void
    {
        ApiResponse::success(
            $this->service()->refresh($request->json()),
            ['resource' => 'auth.refresh']
        );
    }

    public function revoke(Request $request): void
    {
        $refreshToken = trim((string) $request->json('refresh_token', ''));

        if ($refreshToken !== '') {
            ApiResponse::success(
                $this->service()->revokeRefreshToken($refreshToken),
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

        ApiResponse::success(
            $this->service()->revokeCurrent($principal),
            ['resource' => 'auth.revoke']
        );
    }

    public function me(Request $request): void
    {
        /** @var AuthPrincipal $principal */
        $principal = $request->attribute('auth.principal');

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

        ApiResponse::success(
            $this->service()->issueApiToken($principal, $request->json()),
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

        ApiResponse::success(
            $this->service()->revokeApiToken($principal, (int) $id),
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
}
