<?php

declare(strict_types=1);

namespace PachyBase\Services\Platform;

use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Query\QueryExecutorInterface;

final class OperationsOverviewService
{
    private readonly QueryExecutorInterface $queryExecutor;
    private readonly string $tenantsTable;
    private readonly string $backupsTable;
    private readonly string $jobsTable;
    private readonly string $filesTable;
    private readonly string $auditTable;

    public function __construct(?QueryExecutorInterface $queryExecutor = null)
    {
        $connection = Connection::getInstance();
        $this->queryExecutor = $queryExecutor ?? new PdoQueryExecutor($connection->getPDO());
        $adapter = AdapterFactory::make($connection);
        $this->tenantsTable = $adapter->quoteIdentifier('pb_tenants');
        $this->backupsTable = $adapter->quoteIdentifier('pb_project_backups');
        $this->jobsTable = $adapter->quoteIdentifier('pb_async_jobs');
        $this->filesTable = $adapter->quoteIdentifier('pb_file_objects');
        $this->auditTable = $adapter->quoteIdentifier('pb_audit_logs');
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return [
            'projects' => [
                'total' => (int) ($this->queryExecutor->scalar(sprintf('SELECT COUNT(*) AS aggregate FROM %s', $this->tenantsTable)) ?? 0),
                'active' => (int) ($this->queryExecutor->scalar(
                    sprintf('SELECT COUNT(*) AS aggregate FROM %s WHERE is_active = :is_active', $this->tenantsTable),
                    ['is_active' => true]
                ) ?? 0),
            ],
            'backups' => [
                'total' => (int) ($this->queryExecutor->scalar(sprintf('SELECT COUNT(*) AS aggregate FROM %s', $this->backupsTable)) ?? 0),
                'last_24h' => (int) ($this->queryExecutor->scalar(
                    sprintf('SELECT COUNT(*) AS aggregate FROM %s WHERE created_at >= :cutoff', $this->backupsTable),
                    ['cutoff' => gmdate('Y-m-d H:i:s', time() - 86400)]
                ) ?? 0),
            ],
            'jobs' => [
                'pending' => (int) ($this->queryExecutor->scalar(
                    sprintf('SELECT COUNT(*) AS aggregate FROM %s WHERE status = :status', $this->jobsTable),
                    ['status' => 'pending']
                ) ?? 0),
                'failed' => (int) ($this->queryExecutor->scalar(
                    sprintf('SELECT COUNT(*) AS aggregate FROM %s WHERE status = :status', $this->jobsTable),
                    ['status' => 'failed']
                ) ?? 0),
            ],
            'storage' => [
                'total_bytes' => (int) ($this->queryExecutor->scalar(
                    sprintf('SELECT COALESCE(SUM(size_bytes), 0) AS aggregate FROM %s', $this->filesTable)
                ) ?? 0),
            ],
            'audit' => [
                'last_24h' => (int) ($this->queryExecutor->scalar(
                    sprintf('SELECT COUNT(*) AS aggregate FROM %s WHERE occurred_at >= :cutoff', $this->auditTable),
                    ['cutoff' => gmdate('Y-m-d H:i:s', time() - 86400)]
                ) ?? 0),
                'errors_last_24h' => (int) ($this->queryExecutor->scalar(
                    sprintf('SELECT COUNT(*) AS aggregate FROM %s WHERE occurred_at >= :cutoff AND level = :level', $this->auditTable),
                    ['cutoff' => gmdate('Y-m-d H:i:s', time() - 86400), 'level' => 'error']
                ) ?? 0),
            ],
            'recent_backups' => $this->queryExecutor->select(
                sprintf('SELECT id, tenant_id, label, status, size_bytes, created_at FROM %s ORDER BY id DESC LIMIT 10', $this->backupsTable)
            ),
        ];
    }
}
