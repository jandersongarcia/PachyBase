<?php

declare(strict_types=1);

namespace Tests\Api;

use PachyBase\Api\HttpKernel;
use PachyBase\Auth\AuthService;
use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\ResponseCaptured;
use PHPUnit\Framework\TestCase;

class PlatformHttpKernelTest extends TestCase
{
    private ?PdoQueryExecutor $executor = null;
    private ?int $defaultTenantId = null;
    private ?int $operatorUserId = null;
    private string $operatorEmail = '';
    private ?int $projectTenantId = null;
    private string $projectSlug = '';

    protected function setUp(): void
    {
        Config::load(dirname(__DIR__, 2));
        $this->executor = new PdoQueryExecutor(Connection::getInstance()->getPDO());
        $this->defaultTenantId = $this->defaultTenantId();
        $this->operatorEmail = 'platform.kernel.' . bin2hex(random_bytes(4)) . '@example.com';

        $this->executor->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, name, email, password_hash, role, scopes, is_active) VALUES (:tenant_id, :name, :email, :password_hash, :role, :scopes, :is_active)',
                AdapterFactory::make()->quoteIdentifier('pb_users')
            ),
            [
                'tenant_id' => $this->defaultTenantId,
                'name' => 'Platform Operator',
                'email' => $this->operatorEmail,
                'password_hash' => password_hash('phase7-password', PASSWORD_DEFAULT),
                'role' => 'admin',
                'scopes' => json_encode(['*'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'is_active' => true,
            ]
        );

        $this->operatorUserId = (int) Connection::getInstance()->getPDO()->lastInsertId();
    }

    protected function tearDown(): void
    {
        if ($this->projectTenantId !== null) {
            foreach ([
                'pb_auth_sessions',
                'pb_api_tokens',
                'pb_webhook_deliveries',
                'pb_webhooks',
                'pb_async_jobs',
                'pb_file_objects',
                'pb_project_secrets',
                'pb_project_backups',
                'pb_system_settings',
                'pb_users',
            ] as $table) {
                $this->executor?->execute(
                    sprintf('DELETE FROM %s WHERE tenant_id = :tenant_id', AdapterFactory::make()->quoteIdentifier($table)),
                    ['tenant_id' => $this->projectTenantId]
                );
            }

            $this->executor?->execute(
                sprintf('DELETE FROM %s WHERE tenant_id = :tenant_id', AdapterFactory::make()->quoteIdentifier('pb_tenant_quotas')),
                ['tenant_id' => $this->projectTenantId]
            );
            $this->executor?->execute(
                sprintf('DELETE FROM %s WHERE id = :id', AdapterFactory::make()->quoteIdentifier('pb_tenants')),
                ['id' => $this->projectTenantId]
            );
        }

        if ($this->projectSlug !== '') {
            $this->deleteDirectory(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $this->projectSlug);
            $this->deleteDirectory(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $this->projectSlug);
        }

        if ($this->operatorUserId !== null) {
            $this->executor?->execute(
                sprintf('DELETE FROM %s WHERE user_id = :user_id', AdapterFactory::make()->quoteIdentifier('pb_auth_sessions')),
                ['user_id' => $this->operatorUserId]
            );
            $this->executor?->execute(
                sprintf('DELETE FROM %s WHERE user_id = :user_id', AdapterFactory::make()->quoteIdentifier('pb_api_tokens')),
                ['user_id' => $this->operatorUserId]
            );
            $this->executor?->execute(
                sprintf('DELETE FROM %s WHERE id = :id', AdapterFactory::make()->quoteIdentifier('pb_users')),
                ['id' => $this->operatorUserId]
            );
        }

        ApiResponse::disableCapture();
        $_SERVER = [];
        $_GET = [];
        $_POST = [];
        Connection::reset();
        Config::reset();
    }

    public function testOperatorCanProvisionProjectManageSecretAndCreateBackup(): void
    {
        $operatorToken = $this->accessToken();
        $this->projectSlug = 'proj-' . bin2hex(random_bytes(3));

        $provision = $this->dispatch('POST', '/api/platform/projects', [
            'name' => 'Platform Kernel',
            'slug' => $this->projectSlug,
            'admin_email' => 'admin@' . $this->projectSlug . '.example',
            'quotas' => [
                'max_requests_per_month' => 1000,
                'max_storage_bytes' => 1024 * 1024,
            ],
        ], $operatorToken);

        $this->projectTenantId = (int) $provision['data']['project']['id'];

        $secret = $this->dispatch(
            'PUT',
            '/api/platform/projects/' . $this->projectSlug . '/secrets/stripe_key',
            ['value' => 'sk_test_123'],
            $operatorToken
        );
        $revealed = $this->dispatch(
            'GET',
            '/api/platform/projects/' . $this->projectSlug . '/secrets/stripe_key',
            [],
            $operatorToken
        );
        $backup = $this->dispatch(
            'POST',
            '/api/platform/projects/' . $this->projectSlug . '/backups',
            ['label' => 'kernel-checkpoint'],
            $operatorToken
        );

        $this->assertTrue($provision['success']);
        $this->assertSame('platform.projects.provision', $provision['meta']['resource']);
        $this->assertSame($this->projectSlug, $provision['data']['project']['slug']);
        $this->assertNotEmpty($provision['data']['bootstrap_token']);
        $this->assertTrue($secret['data']['updated']);
        $this->assertSame('sk_test_123', $revealed['data']['value']);
        $this->assertSame('kernel-checkpoint', $backup['data']['label']);
    }

    public function testProjectTokenCanUseJobsStorageAndWebhookEndpoints(): void
    {
        $operatorToken = $this->accessToken();
        $this->projectSlug = 'tenant-' . bin2hex(random_bytes(3));
        $provision = $this->dispatch('POST', '/api/platform/projects', [
            'name' => 'Tenant Surface',
            'slug' => $this->projectSlug,
        ], $operatorToken);

        $this->projectTenantId = (int) $provision['data']['project']['id'];
        $projectToken = (string) $provision['data']['bootstrap_token'];

        $job = $this->dispatch('POST', '/api/platform/jobs', [
            'type' => 'noop',
            'payload' => ['message' => 'queued'],
        ], $projectToken);
        $secret = $this->dispatch(
            'PUT',
            '/api/platform/projects/' . $this->projectSlug . '/secrets/runtime_secret',
            ['value' => 'tenant-owned'],
            $projectToken
        );
        $revealedSecret = $this->dispatch(
            'GET',
            '/api/platform/projects/' . $this->projectSlug . '/secrets/runtime_secret',
            [],
            $projectToken
        );
        $ran = $this->dispatch('POST', '/api/platform/jobs/run', ['limit' => 5], $projectToken);
        $file = $this->dispatch('POST', '/api/platform/storage', [
            'filename' => 'hello.txt',
            'content_base64' => base64_encode('hello world'),
            'content_type' => 'text/plain',
        ], $projectToken);
        $download = $this->dispatch('GET', '/api/platform/storage/' . $file['data']['id'] . '/download', [], $projectToken);
        $webhook = $this->dispatch('POST', '/api/platform/webhooks', [
            'name' => 'Demo Hook',
            'event_name' => 'webhook.test',
            'target_url' => 'http://example.invalid/endpoint',
            'secret' => 'top-secret',
        ], $projectToken);
        $queuedDelivery = $this->dispatch('POST', '/api/platform/webhooks/' . $webhook['data']['id'] . '/test', [], $projectToken);

        $this->assertSame('pending', $job['data']['status']);
        $this->assertTrue($secret['data']['updated']);
        $this->assertSame('tenant-owned', $revealedSecret['data']['value']);
        $this->assertNotEmpty($ran['data']['items']);
        $this->assertSame('completed', $ran['data']['items'][0]['status']);
        $this->assertSame('hello.txt', $file['data']['original_name']);
        $this->assertSame(base64_encode('hello world'), $download['data']['content_base64']);
        $this->assertSame('Demo Hook', $webhook['data']['name']);
        $this->assertTrue($queuedDelivery['data']['queued']);
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

    private function accessToken(): string
    {
        $login = (new AuthService())->login([
            'email' => $this->operatorEmail,
            'password' => 'phase7-password',
        ]);

        return (string) $login['access_token'];
    }

    private function defaultTenantId(): int
    {
        $id = (int) ($this->executor?->scalar(
            sprintf(
                'SELECT id AS aggregate FROM %s WHERE slug = :slug LIMIT 1',
                AdapterFactory::make()->quoteIdentifier('pb_tenants')
            ),
            ['slug' => 'default']
        ) ?? 0);

        if ($id > 0) {
            return $id;
        }

        $this->executor?->execute(
            sprintf(
                'INSERT INTO %s (name, slug, is_active) VALUES (:name, :slug, :is_active)',
                AdapterFactory::make()->quoteIdentifier('pb_tenants')
            ),
            [
                'name' => 'Default Workspace',
                'slug' => 'default',
                'is_active' => true,
            ]
        );

        return (int) Connection::getInstance()->getPDO()->lastInsertId();
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
