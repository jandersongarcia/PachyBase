<?php

declare(strict_types=1);

namespace Tests\Api;

use PachyBase\Api\HttpKernel;
use PachyBase\Auth\AuthService;
use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Http\AuthenticationException;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\ErrorHandler;
use PachyBase\Http\ResponseCaptured;
use PachyBase\Http\ValidationException;
use PHPUnit\Framework\TestCase;

class CrudHttpKernelTest extends TestCase
{
    private ?PdoQueryExecutor $executor = null;
    private string $createdKey = '';
    private string $userEmail = '';
    private ?int $userId = null;
    private ?int $tenantId = null;

    protected function setUp(): void
    {
        Config::load(dirname(__DIR__, 2));
        $this->executor = new PdoQueryExecutor(Connection::getInstance()->getPDO());
        $this->userEmail = 'crud.kernel.' . bin2hex(random_bytes(4)) . '@example.com';
        $this->tenantId = $this->defaultTenantId();
        $this->executor->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, name, email, password_hash, role, scopes, is_active) VALUES (:tenant_id, :name, :email, :password_hash, :role, :scopes, :is_active)',
                AdapterFactory::make()->quoteIdentifier('pb_users')
            ),
            [
                'tenant_id' => $this->tenantId,
                'name' => 'CRUD Kernel Test',
                'email' => $this->userEmail,
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
        if ($this->createdKey !== '') {
            $this->executor?->execute(
                sprintf(
                    'DELETE FROM %s WHERE setting_key = :setting_key',
                    AdapterFactory::make()->quoteIdentifier('pb_system_settings')
                ),
                ['setting_key' => $this->createdKey]
            );
        }

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

    public function testKernelDispatchesCrudIndexEndpoint(): void
    {
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/system-settings';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->accessToken();
        $_GET = ['per_page' => 2, 'sort' => 'setting_key'];

        try {
            (new HttpKernel(dirname(__DIR__, 2)))->handle();
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();

            $this->assertSame(200, $captured->getStatusCode());
            $this->assertTrue($payload['success']);
            $this->assertSame('crud.index', $payload['meta']['resource']);
            $this->assertSame('system-settings', $payload['meta']['entity']);
            $this->assertArrayHasKey('pagination', $payload['meta']);
            $this->assertNotEmpty($payload['data']);
        }
    }

    public function testKernelDispatchesCrudCreateEndpoint(): void
    {
        $this->createdKey = 'phase5.kernel.' . bin2hex(random_bytes(4));

        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/system-settings';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->accessToken();
        $_POST = [
            'setting_key' => $this->createdKey,
            'setting_value' => 'Kernel Created',
            'value_type' => 'string',
            'is_public' => '1',
        ];

        try {
            (new HttpKernel(dirname(__DIR__, 2)))->handle();
            $this->fail('Expected captured response.');
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();

            $this->assertSame(201, $captured->getStatusCode());
            $this->assertTrue($payload['success']);
            $this->assertSame('crud.store', $payload['meta']['resource']);
            $this->assertSame($this->createdKey, $payload['data']['setting_key']);
            $this->assertTrue($payload['data']['is_public']);
        }
    }

    public function testKernelReturnsStructuredValidationErrorForInvalidCrudPayload(): void
    {
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/system-settings';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->accessToken();
        $_POST = [
            'setting_key' => 'ab',
            'value_type' => 'unknown',
        ];

        try {
            (new HttpKernel(dirname(__DIR__, 2)))->handle();
            $this->fail('Expected captured validation response.');
        } catch (ValidationException $exception) {
            try {
                ErrorHandler::renderException($exception);
            } catch (ResponseCaptured $captured) {
                $payload = $captured->getPayload();
                $codes = array_column($payload['error']['details'], 'code');

                $this->assertSame(422, $captured->getStatusCode());
                $this->assertFalse($payload['success']);
                $this->assertSame('validation_error', $payload['error']['type']);
                $this->assertContains('min', $codes);
                $this->assertContains('enum', $codes);
            }
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();
            $codes = array_column($payload['error']['details'], 'code');

            $this->assertSame(422, $captured->getStatusCode());
            $this->assertFalse($payload['success']);
            $this->assertSame('validation_error', $payload['error']['type']);
            $this->assertContains('min', $codes);
            $this->assertContains('enum', $codes);
        }
    }

    public function testKernelRejectsProtectedCrudEndpointWithoutAuthentication(): void
    {
        ApiResponse::enableCapture();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/system-settings';

        try {
            (new HttpKernel(dirname(__DIR__, 2)))->handle();
            $this->fail('Expected captured auth response.');
        } catch (AuthenticationException $exception) {
            try {
                ErrorHandler::renderException($exception);
            } catch (ResponseCaptured $captured) {
                $payload = $captured->getPayload();

                $this->assertSame(401, $captured->getStatusCode());
                $this->assertFalse($payload['success']);
                $this->assertSame('authentication_error', $payload['error']['type']);
            }
        } catch (ResponseCaptured $captured) {
            $payload = $captured->getPayload();

            $this->assertSame(401, $captured->getStatusCode());
            $this->assertFalse($payload['success']);
            $this->assertSame('authentication_error', $payload['error']['type']);
        }
    }

    private function accessToken(): string
    {
        $login = (new AuthService())->login([
            'email' => $this->userEmail,
            'password' => 'phase7-password',
        ]);

        return (string) $login['access_token'];
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
