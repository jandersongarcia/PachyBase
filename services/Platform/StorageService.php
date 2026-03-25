<?php

declare(strict_types=1);

namespace PachyBase\Services\Platform;

use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Query\QueryExecutorInterface;
use PachyBase\Utils\Json;
use RuntimeException;

final class StorageService
{
    private readonly QueryExecutorInterface $queryExecutor;
    private readonly string $filesTable;
    private readonly string $quotasTable;

    public function __construct(?QueryExecutorInterface $queryExecutor = null)
    {
        $connection = Connection::getInstance();
        $this->queryExecutor = $queryExecutor ?? new PdoQueryExecutor($connection->getPDO());
        $adapter = AdapterFactory::make($connection);
        $this->filesTable = $adapter->quoteIdentifier('pb_file_objects');
        $this->quotasTable = $adapter->quoteIdentifier('pb_tenant_quotas');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(int $tenantId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        return $this->queryExecutor->select(
            sprintf('SELECT * FROM %s WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT %d', $this->filesTable, $limit),
            ['tenant_id' => $tenantId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $tenantId, int $fileId): array
    {
        $row = $this->queryExecutor->selectOne(
            sprintf('SELECT * FROM %s WHERE id = :id AND tenant_id = :tenant_id LIMIT 1', $this->filesTable),
            ['id' => $fileId, 'tenant_id' => $tenantId]
        );

        if ($row === null) {
            throw new RuntimeException('Stored file not found for the authenticated project.', 404);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function store(int $tenantId, string $tenantSlug, array $payload): array
    {
        $originalName = trim((string) ($payload['filename'] ?? ''));
        $contentBase64 = (string) ($payload['content_base64'] ?? '');

        if ($originalName === '' || $contentBase64 === '') {
            throw new RuntimeException('Storage uploads require filename and content_base64.', 422);
        }

        $binary = base64_decode($contentBase64, true);

        if (!is_string($binary)) {
            throw new RuntimeException('The storage payload is not valid base64.', 422);
        }

        $this->assertQuota($tenantId, strlen($binary));

        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $originalName) ?? $originalName;
        $objectKey = gmdate('Y/m') . '/' . bin2hex(random_bytes(8)) . '-' . trim($safeName, '-');
        $relativePath = 'build/storage/projects/' . $tenantSlug . '/' . $objectKey;
        $absolutePath = $this->absolutePath($relativePath);
        $directory = dirname($absolutePath);

        if (!is_dir($directory) && !mkdir($concurrentDirectory = $directory, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Failed to create the storage directory.', 500);
        }

        file_put_contents($absolutePath, $binary);
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

        $this->queryExecutor->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, disk, object_key, original_name, content_type, size_bytes, checksum_sha256, relative_path, metadata_json) VALUES (:tenant_id, :disk, :object_key, :original_name, :content_type, :size_bytes, :checksum_sha256, :relative_path, :metadata_json)',
                $this->filesTable
            ),
            [
                'tenant_id' => $tenantId,
                'disk' => 'local',
                'object_key' => $objectKey,
                'original_name' => $originalName,
                'content_type' => trim((string) ($payload['content_type'] ?? 'application/octet-stream')),
                'size_bytes' => strlen($binary),
                'checksum_sha256' => hash('sha256', $binary),
                'relative_path' => $relativePath,
                'metadata_json' => Json::encode($metadata),
            ]
        );

        return $this->show($tenantId, (int) Connection::getInstance()->getPDO()->lastInsertId());
    }

    /**
     * @return array<string, mixed>
     */
    public function download(int $tenantId, int $fileId): array
    {
        $row = $this->show($tenantId, $fileId);
        $absolutePath = $this->absolutePath((string) ($row['relative_path'] ?? ''));
        $content = is_file($absolutePath) ? file_get_contents($absolutePath) : false;

        if (!is_string($content)) {
            throw new RuntimeException('Stored file content is no longer available on disk.', 500);
        }

        return [
            'id' => (int) $row['id'],
            'object_key' => (string) $row['object_key'],
            'filename' => (string) $row['original_name'],
            'content_type' => (string) $row['content_type'],
            'size_bytes' => (int) $row['size_bytes'],
            'checksum_sha256' => (string) $row['checksum_sha256'],
            'content_base64' => base64_encode($content),
        ];
    }

    private function assertQuota(int $tenantId, int $newBytes): void
    {
        $limit = $this->queryExecutor->scalar(
            sprintf('SELECT max_storage_bytes AS aggregate FROM %s WHERE tenant_id = :tenant_id LIMIT 1', $this->quotasTable),
            ['tenant_id' => $tenantId]
        );

        if ($limit === null || (int) $limit < 1) {
            return;
        }

        $used = (int) ($this->queryExecutor->scalar(
            sprintf('SELECT COALESCE(SUM(size_bytes), 0) AS aggregate FROM %s WHERE tenant_id = :tenant_id', $this->filesTable),
            ['tenant_id' => $tenantId]
        ) ?? 0);

        if (($used + $newBytes) > (int) $limit) {
            throw new RuntimeException('The project storage quota has been exhausted.', 429);
        }
    }

    private function absolutePath(string $relativePath): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }
}
