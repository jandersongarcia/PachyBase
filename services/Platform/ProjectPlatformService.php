<?php

declare(strict_types=1);

namespace PachyBase\Services\Platform;

use PachyBase\Auth\ApiTokenRepository;
use PachyBase\Config\TenancyConfig;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Query\QueryExecutorInterface;
use PachyBase\Services\Tenancy\TenantRepository;
use PachyBase\Utils\Crypto;
use PachyBase\Utils\Json;
use RuntimeException;

final class ProjectPlatformService
{
    private const TENANT_ADMIN_SCOPES = [
        'crud:*',
        'auth:manage',
        'secrets:*',
        'jobs:*',
        'storage:*',
        'webhooks:*',
    ];

    private readonly QueryExecutorInterface $queryExecutor;
    private readonly DatabaseAdapterInterface $adapter;
    private readonly string $tenantsTable;
    private readonly string $usersTable;
    private readonly string $settingsTable;
    private readonly string $quotasTable;
    private readonly string $backupsTable;
    private readonly string $secretsTable;
    private readonly string $filesTable;
    private readonly string $jobsTable;

    public function __construct(
        ?QueryExecutorInterface $queryExecutor = null,
        private readonly ?TenantRepository $tenants = null
    ) {
        $connection = Connection::getInstance();
        $this->queryExecutor = $queryExecutor ?? new PdoQueryExecutor($connection->getPDO());
        $this->adapter = AdapterFactory::make($connection);
        $this->tenantsTable = $this->adapter->quoteIdentifier('pb_tenants');
        $this->usersTable = $this->adapter->quoteIdentifier('pb_users');
        $this->settingsTable = $this->adapter->quoteIdentifier('pb_system_settings');
        $this->quotasTable = $this->adapter->quoteIdentifier('pb_tenant_quotas');
        $this->backupsTable = $this->adapter->quoteIdentifier('pb_project_backups');
        $this->secretsTable = $this->adapter->quoteIdentifier('pb_project_secrets');
        $this->filesTable = $this->adapter->quoteIdentifier('pb_file_objects');
        $this->jobsTable = $this->adapter->quoteIdentifier('pb_async_jobs');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listProjects(int $limit = 100): array
    {
        $limit = max(1, min($limit, 200));
        $rows = $this->queryExecutor->select(
            sprintf(
                'SELECT t.*, q.max_requests_per_month, q.max_tokens, q.max_entities, q.max_storage_bytes,
                    (SELECT COUNT(*) FROM %s b WHERE b.tenant_id = t.id) AS backup_count,
                    (SELECT MAX(created_at) FROM %s b WHERE b.tenant_id = t.id) AS last_backup_at
                 FROM %s t
                 LEFT JOIN %s q ON q.tenant_id = t.id
                 ORDER BY t.id DESC
                 LIMIT %d',
                $this->backupsTable,
                $this->backupsTable,
                $this->tenantsTable,
                $this->quotasTable,
                $limit
            )
        );

        return array_map(fn(array $row): array => $this->formatProjectSummary($row), $rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function showProject(string $reference): array
    {
        $tenant = $this->resolveProject($reference);
        $quota = $this->quotaForTenant((int) $tenant['id']);
        $storageUsed = (int) ($this->queryExecutor->scalar(
            sprintf('SELECT COALESCE(SUM(size_bytes), 0) AS aggregate FROM %s WHERE tenant_id = :tenant_id', $this->filesTable),
            ['tenant_id' => (int) $tenant['id']]
        ) ?? 0);

        return [
            'project' => $this->formatProjectSummary(array_merge($tenant, $quota)),
            'recent_backups' => $this->listBackups($reference, 10),
            'storage' => [
                'used_bytes' => $storageUsed,
                'limit_bytes' => $quota['max_storage_bytes'] ?? null,
            ],
            'secrets' => [
                'count' => (int) ($this->queryExecutor->scalar(
                    sprintf('SELECT COUNT(*) AS aggregate FROM %s WHERE tenant_id = :tenant_id', $this->secretsTable),
                    ['tenant_id' => (int) $tenant['id']]
                ) ?? 0),
            ],
            'jobs' => [
                'pending' => (int) ($this->queryExecutor->scalar(
                    sprintf('SELECT COUNT(*) AS aggregate FROM %s WHERE tenant_id = :tenant_id AND status = :status', $this->jobsTable),
                    ['tenant_id' => (int) $tenant['id'], 'status' => 'pending']
                ) ?? 0),
                'failed' => (int) ($this->queryExecutor->scalar(
                    sprintf('SELECT COUNT(*) AS aggregate FROM %s WHERE tenant_id = :tenant_id AND status = :status', $this->jobsTable),
                    ['tenant_id' => (int) $tenant['id'], 'status' => 'failed']
                ) ?? 0),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function provisionProject(array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $slug = $this->normalizeSlug((string) ($payload['slug'] ?? ''));

        if ($name === '' || $slug === '') {
            throw new RuntimeException('Project provisioning requires both name and slug.', 422);
        }

        if ($this->repository()->findActiveBySlug($slug) !== null) {
            throw new RuntimeException(sprintf('A project with slug "%s" already exists.', $slug), 409);
        }

        $adminEmail = strtolower(trim((string) ($payload['admin_email'] ?? ('admin@' . $slug . '.pachybase.local'))));
        $adminPassword = (string) ($payload['admin_password'] ?? $this->randomPassword());
        $adminName = trim((string) ($payload['admin_name'] ?? ($name . ' Admin')));
        $settings = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
        $quotas = is_array($payload['quotas'] ?? null) ? $payload['quotas'] : [];
        $token = null;

        $this->queryExecutor->transaction(function () use (
            $name,
            $slug,
            $adminEmail,
            $adminPassword,
            $adminName,
            $settings,
            $quotas,
            &$token
        ): void {
            $this->queryExecutor->execute(
                sprintf('INSERT INTO %s (name, slug, is_active) VALUES (:name, :slug, :is_active)', $this->tenantsTable),
                ['name' => $name, 'slug' => $slug, 'is_active' => true]
            );

            $tenant = $this->repository()->resolveReference($slug);
            $tenantId = (int) $tenant['id'];
            $this->upsertQuota($tenantId, $quotas);

            $this->queryExecutor->execute(
                sprintf(
                    'INSERT INTO %s (tenant_id, name, email, password_hash, role, scopes, is_active) VALUES (:tenant_id, :name, :email, :password_hash, :role, :scopes, :is_active)',
                    $this->usersTable
                ),
                [
                    'tenant_id' => $tenantId,
                    'name' => $adminName,
                    'email' => $adminEmail,
                    'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
                    'role' => 'admin',
                    'scopes' => Json::encode(self::TENANT_ADMIN_SCOPES),
                    'is_active' => true,
                ]
            );

            $userId = (int) Connection::getInstance()->getPDO()->lastInsertId();
            $this->upsertSetting($tenantId, 'app.name', $name, 'string', true);

            foreach ($settings as $key => $value) {
                if (!is_string($key) || trim($key) === '') {
                    continue;
                }

                $this->upsertSetting($tenantId, $key, $value, $this->inferSettingType($value), false);
            }

            $plainToken = 'pbt_' . bin2hex(random_bytes(24));
            (new ApiTokenRepository($this->queryExecutor))->create(
                'Bootstrap Project Token',
                hash('sha256', $plainToken),
                substr($plainToken, 0, 12),
                self::TENANT_ADMIN_SCOPES,
                $userId,
                $tenantId,
                $userId,
                null
            );
            $token = $plainToken;
        });

        return [
            'project' => $this->showProject($slug)['project'],
            'bootstrap_admin' => [
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => $adminPassword,
            ],
            'bootstrap_token' => $token,
            'tenant_header' => TenancyConfig::headerName(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBackups(string $reference, int $limit = 20): array
    {
        $tenant = $this->resolveProject($reference);
        $limit = max(1, min($limit, 100));

        return $this->queryExecutor->select(
            sprintf(
                'SELECT id, tenant_id, label, status, file_path, size_bytes, restored_at, created_at, updated_at
                 FROM %s
                 WHERE tenant_id = :tenant_id
                 ORDER BY id DESC
                 LIMIT %d',
                $this->backupsTable,
                $limit
            ),
            ['tenant_id' => (int) $tenant['id']]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function createBackup(string $reference, ?int $triggeredByUserId = null, ?string $label = null): array
    {
        $tenant = $this->resolveProject($reference);
        $snapshot = $this->buildBackupSnapshot((int) $tenant['id']);
        $encoded = Json::encode($snapshot);
        $timestamp = gmdate('YmdHis');
        $filePath = 'build/backups/' . $tenant['slug'] . '/backup-' . $timestamp . '.json';
        $absolutePath = $this->absolutePath($filePath);
        $directory = dirname($absolutePath);

        if (!is_dir($directory) && !mkdir($concurrentDirectory = $directory, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Failed to create the backup directory.', 500);
        }

        file_put_contents($absolutePath, $encoded);
        $this->queryExecutor->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, label, status, file_path, backup_json, size_bytes, triggered_by_user_id) VALUES (:tenant_id, :label, :status, :file_path, :backup_json, :size_bytes, :triggered_by_user_id)',
                $this->backupsTable
            ),
            [
                'tenant_id' => (int) $tenant['id'],
                'label' => $label ?: sprintf('backup-%s', $timestamp),
                'status' => 'ready',
                'file_path' => $filePath,
                'backup_json' => $encoded,
                'size_bytes' => strlen($encoded),
                'triggered_by_user_id' => $triggeredByUserId,
            ]
        );

        return (array) $this->queryExecutor->selectOne(
            sprintf(
                'SELECT id, tenant_id, label, status, file_path, size_bytes, restored_at, created_at, updated_at
                 FROM %s
                 WHERE id = :id
                 LIMIT 1',
                $this->backupsTable
            ),
            ['id' => (int) Connection::getInstance()->getPDO()->lastInsertId()]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function restoreBackup(string $reference, int $backupId): array
    {
        $tenant = $this->resolveProject($reference);
        $backup = $this->queryExecutor->selectOne(
            sprintf('SELECT * FROM %s WHERE id = :id AND tenant_id = :tenant_id LIMIT 1', $this->backupsTable),
            ['id' => $backupId, 'tenant_id' => (int) $tenant['id']]
        );

        if ($backup === null) {
            throw new RuntimeException('Backup not found for the selected project.', 404);
        }

        $snapshot = json_decode((string) ($backup['backup_json'] ?? ''), true);

        if (!is_array($snapshot)) {
            throw new RuntimeException('The stored backup payload is invalid.', 500);
        }

        $tenantId = (int) $tenant['id'];
        $tenantSlug = (string) $tenant['slug'];

        $this->queryExecutor->transaction(function () use ($tenantId, $tenantSlug, $snapshot, $tenant): void {
            $this->cleanupTenantState($tenantId, $tenantSlug);
            $this->queryExecutor->execute(
                sprintf('UPDATE %s SET name = :name, is_active = :is_active WHERE id = :id', $this->tenantsTable),
                [
                    'id' => $tenantId,
                    'name' => (string) (($snapshot['project']['name'] ?? null) ?: $tenant['name']),
                    'is_active' => (bool) ($snapshot['project']['is_active'] ?? true),
                ]
            );
            $this->upsertQuota($tenantId, is_array($snapshot['quota'] ?? null) ? $snapshot['quota'] : []);

            foreach ((array) ($snapshot['settings'] ?? []) as $row) {
                $this->insertRow('pb_system_settings', $row);
            }

            foreach ((array) ($snapshot['users'] ?? []) as $row) {
                $this->insertRow('pb_users', $row);
            }

            foreach ((array) ($snapshot['api_tokens'] ?? []) as $row) {
                $this->insertRow('pb_api_tokens', $row);
            }

            foreach ((array) ($snapshot['auth_sessions'] ?? []) as $row) {
                $this->insertRow('pb_auth_sessions', $row);
            }

            foreach ((array) ($snapshot['secrets'] ?? []) as $row) {
                $this->insertRow('pb_project_secrets', $row);
            }

            foreach ((array) ($snapshot['webhooks'] ?? []) as $row) {
                $this->insertRow('pb_webhooks', $row);
            }

            foreach ((array) ($snapshot['files'] ?? []) as $row) {
                $content = (string) ($row['content_base64'] ?? '');
                unset($row['content_base64']);
                $this->insertRow('pb_file_objects', $row);
                $this->restoreFileBlob($row, $content);
            }
        });

        $this->queryExecutor->execute(
            sprintf('UPDATE %s SET restored_at = :restored_at, updated_at = :updated_at WHERE id = :id', $this->backupsTable),
            [
                'id' => $backupId,
                'restored_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]
        );

        return [
            'restored' => true,
            'backup_id' => $backupId,
            'project' => $this->showProject($reference)['project'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSecrets(string $reference): array
    {
        $tenant = $this->resolveProject($reference);

        return $this->queryExecutor->select(
            sprintf(
                'SELECT id, tenant_id, secret_key, created_at, updated_at
                 FROM %s
                 WHERE tenant_id = :tenant_id
                 ORDER BY secret_key ASC',
                $this->secretsTable
            ),
            ['tenant_id' => (int) $tenant['id']]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function revealSecret(string $reference, string $key): array
    {
        $tenant = $this->resolveProject($reference);
        $row = $this->queryExecutor->selectOne(
            sprintf(
                'SELECT * FROM %s WHERE tenant_id = :tenant_id AND secret_key = :secret_key LIMIT 1',
                $this->secretsTable
            ),
            ['tenant_id' => (int) $tenant['id'], 'secret_key' => trim($key)]
        );

        if ($row === null) {
            throw new RuntimeException('Secret not found for the selected project.', 404);
        }

        return [
            'id' => (int) $row['id'],
            'tenant_id' => (int) $row['tenant_id'],
            'secret_key' => (string) $row['secret_key'],
            'value' => Crypto::decryptString((string) $row['secret_value_encrypted']),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function putSecret(string $reference, string $key, string $value): array
    {
        $tenant = $this->resolveProject($reference);
        $secretKey = trim($key);

        if ($secretKey === '' || $value === '') {
            throw new RuntimeException('Secret key and value are required.', 422);
        }

        $existing = $this->queryExecutor->selectOne(
            sprintf('SELECT id FROM %s WHERE tenant_id = :tenant_id AND secret_key = :secret_key LIMIT 1', $this->secretsTable),
            ['tenant_id' => (int) $tenant['id'], 'secret_key' => $secretKey]
        );
        $encrypted = Crypto::encryptString($value);

        if ($existing === null) {
            $this->queryExecutor->execute(
                sprintf('INSERT INTO %s (tenant_id, secret_key, secret_value_encrypted) VALUES (:tenant_id, :secret_key, :secret_value_encrypted)', $this->secretsTable),
                ['tenant_id' => (int) $tenant['id'], 'secret_key' => $secretKey, 'secret_value_encrypted' => $encrypted]
            );
        } else {
            $this->queryExecutor->execute(
                sprintf('UPDATE %s SET secret_value_encrypted = :secret_value_encrypted, updated_at = :updated_at WHERE id = :id', $this->secretsTable),
                ['id' => (int) $existing['id'], 'secret_value_encrypted' => $encrypted, 'updated_at' => gmdate('Y-m-d H:i:s')]
            );
        }

        return ['secret_key' => $secretKey, 'updated' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteSecret(string $reference, string $key): array
    {
        $tenant = $this->resolveProject($reference);
        $deleted = $this->queryExecutor->execute(
            sprintf('DELETE FROM %s WHERE tenant_id = :tenant_id AND secret_key = :secret_key', $this->secretsTable),
            ['tenant_id' => (int) $tenant['id'], 'secret_key' => trim($key)]
        );

        return ['deleted' => $deleted > 0, 'secret_key' => trim($key)];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBackupSnapshot(int $tenantId): array
    {
        $tenant = $this->repository()->resolveReference((string) $tenantId);
        $files = $this->selectTenantRows('pb_file_objects', $tenantId);

        $files = array_map(function (array $row): array {
            $absolutePath = $this->absolutePath((string) ($row['relative_path'] ?? ''));
            $content = is_file($absolutePath) ? file_get_contents($absolutePath) : '';
            $row['content_base64'] = base64_encode($content === false ? '' : $content);

            return $row;
        }, $files);

        return [
            'project' => [
                'id' => (int) $tenant['id'],
                'name' => (string) $tenant['name'],
                'slug' => (string) $tenant['slug'],
                'is_active' => (bool) $tenant['is_active'],
            ],
            'quota' => $this->quotaForTenant($tenantId),
            'settings' => $this->selectTenantRows('pb_system_settings', $tenantId),
            'users' => $this->selectTenantRows('pb_users', $tenantId),
            'api_tokens' => $this->selectTenantRows('pb_api_tokens', $tenantId),
            'auth_sessions' => $this->selectTenantRows('pb_auth_sessions', $tenantId),
            'secrets' => $this->selectTenantRows('pb_project_secrets', $tenantId),
            'webhooks' => $this->selectTenantRows('pb_webhooks', $tenantId),
            'files' => $files,
        ];
    }

    private function cleanupTenantState(int $tenantId, string $tenantSlug): void
    {
        foreach ([
            'pb_auth_sessions',
            'pb_api_tokens',
            'pb_webhook_deliveries',
            'pb_webhooks',
            'pb_async_jobs',
            'pb_file_objects',
            'pb_project_secrets',
            'pb_system_settings',
            'pb_users',
        ] as $tableName) {
            $this->queryExecutor->execute(
                sprintf('DELETE FROM %s WHERE tenant_id = :tenant_id', $this->adapter->quoteIdentifier($tableName)),
                ['tenant_id' => $tenantId]
            );
        }

        $this->queryExecutor->execute(
            sprintf('DELETE FROM %s WHERE tenant_id = :tenant_id', $this->quotasTable),
            ['tenant_id' => $tenantId]
        );

        $storagePath = $this->absolutePath('build/storage/projects/' . $tenantSlug);

        if (is_dir($storagePath)) {
            $this->deleteDirectory($storagePath);
        }
    }

    /**
     * @param array<string, mixed> $quota
     */
    private function upsertQuota(int $tenantId, array $quota): void
    {
        $values = [
            'tenant_id' => $tenantId,
            'max_requests_per_month' => $this->nullableInteger($quota['max_requests_per_month'] ?? null),
            'max_tokens' => $this->nullableInteger($quota['max_tokens'] ?? null),
            'max_entities' => $this->nullableInteger($quota['max_entities'] ?? null),
            'max_storage_bytes' => $this->nullableInteger($quota['max_storage_bytes'] ?? null),
        ];
        $existing = $this->queryExecutor->selectOne(
            sprintf('SELECT id FROM %s WHERE tenant_id = :tenant_id LIMIT 1', $this->quotasTable),
            ['tenant_id' => $tenantId]
        );

        if ($existing === null) {
            $this->queryExecutor->execute(
                sprintf('INSERT INTO %s (tenant_id, max_requests_per_month, max_tokens, max_entities, max_storage_bytes) VALUES (:tenant_id, :max_requests_per_month, :max_tokens, :max_entities, :max_storage_bytes)', $this->quotasTable),
                $values
            );

            return;
        }

        $this->queryExecutor->execute(
            sprintf('UPDATE %s SET max_requests_per_month = :max_requests_per_month, max_tokens = :max_tokens, max_entities = :max_entities, max_storage_bytes = :max_storage_bytes, updated_at = :updated_at WHERE tenant_id = :tenant_id', $this->quotasTable),
            array_merge($values, ['updated_at' => gmdate('Y-m-d H:i:s')])
        );
    }

    private function upsertSetting(int $tenantId, string $key, mixed $value, string $type, bool $isPublic): void
    {
        $existing = $this->queryExecutor->selectOne(
            sprintf('SELECT id FROM %s WHERE tenant_id = :tenant_id AND setting_key = :setting_key LIMIT 1', $this->settingsTable),
            ['tenant_id' => $tenantId, 'setting_key' => $key]
        );
        $bindings = [
            'tenant_id' => $tenantId,
            'setting_key' => $key,
            'setting_value' => is_string($value) ? $value : Json::encode($value),
            'value_type' => $type,
            'is_public' => $isPublic,
        ];

        if ($existing === null) {
            $this->queryExecutor->execute(
                sprintf('INSERT INTO %s (tenant_id, setting_key, setting_value, value_type, is_public) VALUES (:tenant_id, :setting_key, :setting_value, :value_type, :is_public)', $this->settingsTable),
                $bindings
            );

            return;
        }

        $this->queryExecutor->execute(
            sprintf('UPDATE %s SET setting_value = :setting_value, value_type = :value_type, is_public = :is_public, updated_at = :updated_at WHERE id = :id', $this->settingsTable),
            array_merge($bindings, ['id' => (int) $existing['id'], 'updated_at' => gmdate('Y-m-d H:i:s')])
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function quotaForTenant(int $tenantId): array
    {
        return $this->queryExecutor->selectOne(
            sprintf('SELECT * FROM %s WHERE tenant_id = :tenant_id LIMIT 1', $this->quotasTable),
            ['tenant_id' => $tenantId]
        ) ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function selectTenantRows(string $tableName, int $tenantId): array
    {
        return $this->queryExecutor->select(
            sprintf('SELECT * FROM %s WHERE tenant_id = :tenant_id ORDER BY id ASC', $this->adapter->quoteIdentifier($tableName)),
            ['tenant_id' => $tenantId]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insertRow(string $tableName, array $row): void
    {
        $columns = array_keys($row);
        $bindings = [];
        $placeholders = [];

        foreach ($columns as $column) {
            $bindings[$column] = $row[$column];
            $placeholders[] = ':' . $column;
        }

        $this->queryExecutor->execute(
            sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $this->adapter->quoteIdentifier($tableName),
                implode(', ', array_map([$this->adapter, 'quoteIdentifier'], $columns)),
                implode(', ', $placeholders)
            ),
            $bindings
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function restoreFileBlob(array $row, string $contentBase64): void
    {
        $relativePath = (string) ($row['relative_path'] ?? '');

        if ($relativePath === '') {
            return;
        }

        $absolutePath = $this->absolutePath($relativePath);
        $directory = dirname($absolutePath);

        if (!is_dir($directory) && !mkdir($concurrentDirectory = $directory, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Failed to restore the storage directory.', 500);
        }

        file_put_contents($absolutePath, base64_decode($contentBase64, true) ?: '');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatProjectSummary(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'is_active' => (bool) $row['is_active'],
            'quotas' => [
                'max_requests_per_month' => $this->nullableInteger($row['max_requests_per_month'] ?? null),
                'max_tokens' => $this->nullableInteger($row['max_tokens'] ?? null),
                'max_entities' => $this->nullableInteger($row['max_entities'] ?? null),
                'max_storage_bytes' => $this->nullableInteger($row['max_storage_bytes'] ?? null),
            ],
            'backup_count' => isset($row['backup_count']) ? (int) $row['backup_count'] : null,
            'last_backup_at' => $row['last_backup_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function normalizeSlug(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9-]+/', '-', $normalized) ?? $normalized;

        return trim($normalized, '-');
    }

    private function inferSettingType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_array($value) => 'json',
            default => 'string',
        };
    }

    private function nullableInteger(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function randomPassword(): string
    {
        return bin2hex(random_bytes(10));
    }

    private function repository(): TenantRepository
    {
        return $this->tenants ?? new TenantRepository($this->queryExecutor);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveProject(string $reference): array
    {
        return $this->repository()->resolveReference($reference);
    }

    private function absolutePath(string $relativePath): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }

    private function deleteDirectory(string $directory): void
    {
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
