<?php

declare(strict_types=1);

namespace Tests\Api;

use PachyBase\Api\HttpKernel;
use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\ResponseCaptured;
use PHPUnit\Framework\TestCase;

class AuthHttpKernelTest extends TestCase
{
    private ?PdoQueryExecutor $executor = null;
    private string $email = '';
    private ?int $userId = null;
    private ?int $tenantId = null;

    protected function setUp(): void
    {
        Config::load(dirname(__DIR__, 2));
        $this->executor = new PdoQueryExecutor(Connection::getInstance()->getPDO());
        $this->email = 'auth.kernel.' . bin2hex(random_bytes(4)) . '@example.com';
        $this->tenantId = $this->defaultTenantId();

        $this->executor->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, name, email, password_hash, role, scopes, is_active) VALUES (:tenant_id, :name, :email, :password_hash, :role, :scopes, :is_active)',
                AdapterFactory::make()->quoteIdentifier('pb_users')
            ),
            [
                'tenant_id' => $this->tenantId,
                'name' => 'Auth Kernel Test',
                'email' => $this->email,
                'password_hash' => password_hash('phase7-password', PASSWORD_DEFAULT),
                'role' => 'admin',
                'scopes' => json_encode(['*', 'auth:manage'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'is_active' => true,
            ]
        );

        $this->userId = (int) Connection::getInstance()->getPDO()->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->userId !== null) {
            $this->executor?->execute(
                sprintf('DELETE FROM %s WHERE user_id = :user_id', AdapterFactory::make()->quoteIdentifier('pb_auth_sessions')),
                ['user_id' => $this->userId]
            );
            $this->executor?->execute(
                sprintf('DELETE FROM %s WHERE user_id = :user_id', AdapterFactory::make()->quoteIdentifier('pb_api_tokens')),
                ['user_id' => $this->userId]
            );
            $this->executor?->execute(
                sprintf('DELETE FROM %s WHERE id = :id', AdapterFactory::make()->quoteIdentifier('pb_users')),
                ['id' => $this->userId]
            );
        }

        ApiResponse::disableCapture();
        $_SERVER = [];
        $_GET = [];
        $_POST = [];
        Connection::reset();
        Config::reset();
    }

    public function testKernelHandlesLoginAndProtectedMeEndpoint(): void
    {
        $loginPayload = $this->dispatch('POST', '/api/auth/login', [
            'email' => $this->email,
            'password' => 'phase7-password',
        ]);
        $accessToken = (string) $loginPayload['data']['access_token'];
        $mePayload = $this->dispatch('GET', '/api/auth/me', [], $accessToken);

        $this->assertTrue($loginPayload['success']);
        $this->assertSame('auth.login', $loginPayload['meta']['resource']);
        $this->assertSame($this->email, $loginPayload['data']['user']['email']);
        $this->assertSame($this->tenantId, $loginPayload['data']['user']['tenant']['id']);
        $this->assertTrue($mePayload['data']['authenticated']);
        $this->assertSame('auth.me', $mePayload['meta']['resource']);
        $this->assertSame('jwt', $mePayload['data']['principal']['provider']);
    }

    public function testKernelIssuesApiTokenAndAllowsProtectedCrudWithIt(): void
    {
        $loginPayload = $this->dispatch('POST', '/api/auth/login', [
            'email' => $this->email,
            'password' => 'phase7-password',
        ]);
        $accessToken = (string) $loginPayload['data']['access_token'];
        $apiTokenPayload = $this->dispatch('POST', '/api/auth/tokens', [
            'name' => 'Kernel API Token',
            'scopes' => ['entity:system-settings:read'],
        ], $accessToken);
        $crudPayload = $this->dispatch('GET', '/api/system-settings', ['per_page' => 1], (string) $apiTokenPayload['data']['token']);

        $this->assertSame(201, $apiTokenPayload['meta']['status_code'] ?? 201);
        $this->assertSame('auth.tokens.store', $apiTokenPayload['meta']['resource']);
        $this->assertTrue($crudPayload['success']);
        $this->assertSame('crud.index', $crudPayload['meta']['resource']);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function dispatch(string $method, string $uri, array $payload = [], ?string $bearerToken = null): array
    {
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_GET = $method === 'GET' ? $payload : [];
        $_POST = $method === 'GET' ? [] : $payload;

        if ($bearerToken !== null) {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $bearerToken;
        } else {
            unset($_SERVER['HTTP_AUTHORIZATION']);
        }

        try {
            (new HttpKernel(dirname(__DIR__, 2)))->handle();
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $response = $captured->getPayload();
            $response['meta']['status_code'] = $captured->getStatusCode();

            return $response;
        } finally {
            ApiResponse::disableCapture();
            $_GET = [];
            $_POST = [];
        }
    }

    private function defaultTenantId(): int
    {
        return (int) ($this->executor?->scalar(
            sprintf(
                'SELECT id AS aggregate FROM %s WHERE slug = :slug LIMIT 1',
                AdapterFactory::make()->quoteIdentifier('pb_tenants')
            ),
            ['slug' => 'default']
        ) ?? 0);
    }
}
