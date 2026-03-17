<?php

declare(strict_types=1);

namespace Tests\Auth;

use PachyBase\Auth\AuthService;
use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PHPUnit\Framework\TestCase;

class AuthServiceIntegrationTest extends TestCase
{
    private ?PdoQueryExecutor $executor = null;
    private string $email = '';
    private ?int $userId = null;

    protected function setUp(): void
    {
        Config::load(dirname(__DIR__, 2));
        $this->executor = new PdoQueryExecutor(Connection::getInstance()->getPDO());
        $this->email = 'phase7.' . bin2hex(random_bytes(4)) . '@example.com';

        $this->executor->execute(
            sprintf(
                'INSERT INTO %s (name, email, password_hash, role, scopes, is_active) VALUES (:name, :email, :password_hash, :role, :scopes, :is_active)',
                AdapterFactory::make()->quoteIdentifier('pb_users')
            ),
            [
                'name' => 'Phase 7 Test User',
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
        if ($this->executor !== null && $this->userId !== null) {
            $this->executor->execute(
                sprintf('DELETE FROM %s WHERE user_id = :user_id', AdapterFactory::make()->quoteIdentifier('pb_auth_sessions')),
                ['user_id' => $this->userId]
            );
            $this->executor->execute(
                sprintf('DELETE FROM %s WHERE user_id = :user_id', AdapterFactory::make()->quoteIdentifier('pb_api_tokens')),
                ['user_id' => $this->userId]
            );
            $this->executor->execute(
                sprintf('DELETE FROM %s WHERE id = :id', AdapterFactory::make()->quoteIdentifier('pb_users')),
                ['id' => $this->userId]
            );
        }

        Connection::reset();
        Config::reset();
    }

    public function testLoginRefreshAndApiTokenFlow(): void
    {
        $service = new AuthService();
        $login = $service->login([
            'email' => $this->email,
            'password' => 'phase7-password',
        ]);

        $jwtPrincipal = $service->authenticateBearerToken((string) $login['access_token']);
        $apiToken = $service->issueApiToken($jwtPrincipal, [
            'name' => 'Phase 7 Integration Token',
            'scopes' => ['entity:system-settings:read'],
        ]);
        $apiTokenPrincipal = $service->authenticateBearerToken((string) $apiToken['token']);
        $refresh = $service->refresh([
            'refresh_token' => $login['refresh_token'],
        ]);
        $revokedApiToken = $service->revokeCurrent($apiTokenPrincipal);
        $revokedRefresh = $service->revokeRefreshToken((string) $refresh['refresh_token']);

        $this->assertSame($this->email, $login['user']['email']);
        $this->assertSame('jwt', $jwtPrincipal->provider);
        $this->assertSame('api_token', $apiTokenPrincipal->provider);
        $this->assertSame(['entity:system-settings:read'], $apiToken['scopes']);
        $this->assertArrayHasKey('access_token', $refresh);
        $this->assertTrue($revokedApiToken['revoked']);
        $this->assertTrue($revokedRefresh['revoked']);
    }
}
