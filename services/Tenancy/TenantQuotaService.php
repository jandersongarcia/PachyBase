<?php

declare(strict_types=1);

namespace PachyBase\Services\Tenancy;

use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Query\QueryExecutorInterface;
use PachyBase\Http\Request;
use PachyBase\Modules\Crud\CrudEntityRegistry;
use RuntimeException;

final class TenantQuotaService
{
    private readonly QueryExecutorInterface $queryExecutor;
    private readonly string $quotasTable;
    private readonly string $usageTable;

    public function __construct(
        ?QueryExecutorInterface $queryExecutor = null,
        private readonly ?CrudEntityRegistry $registry = null
    ) {
        $connection = Connection::getInstance();
        $adapter = AdapterFactory::make($connection);

        $this->queryExecutor = $queryExecutor ?? new PdoQueryExecutor($connection->getPDO());
        $this->quotasTable = $adapter->quoteIdentifier('pb_tenant_quotas');
        $this->usageTable = $adapter->quoteIdentifier('pb_tenant_quota_usage');
    }

    public function consumeRequest(int $tenantId): void
    {
        $quota = $this->quota($tenantId);
        $periodKey = gmdate('Y-m');
        $metric = 'requests';
        $limit = isset($quota['max_requests_per_month']) ? (int) $quota['max_requests_per_month'] : null;

        $this->queryExecutor->transaction(function (QueryExecutorInterface $queryExecutor) use ($tenantId, $periodKey, $metric, $limit): void {
            $row = $queryExecutor->selectOne(
                sprintf(
                    'SELECT * FROM %s WHERE tenant_id = :tenant_id AND metric = :metric AND period_key = :period_key FOR UPDATE',
                    $this->usageTable
                ),
                [
                    'tenant_id' => $tenantId,
                    'metric' => $metric,
                    'period_key' => $periodKey,
                ]
            );

            if ($row === null) {
                $queryExecutor->execute(
                    sprintf(
                        'INSERT INTO %s (tenant_id, metric, period_key, used_value) VALUES (:tenant_id, :metric, :period_key, :used_value)',
                        $this->usageTable
                    ),
                    [
                        'tenant_id' => $tenantId,
                        'metric' => $metric,
                        'period_key' => $periodKey,
                        'used_value' => 0,
                    ]
                );

                $row = ['used_value' => 0];
            }

            $nextValue = (int) ($row['used_value'] ?? 0) + 1;

            if ($limit !== null && $limit > 0 && $nextValue > $limit) {
                throw new RuntimeException('The tenant request quota has been exhausted for the current month.', 429);
            }

            $queryExecutor->execute(
                sprintf(
                    'UPDATE %s SET used_value = :used_value, updated_at = :updated_at WHERE tenant_id = :tenant_id AND metric = :metric AND period_key = :period_key',
                    $this->usageTable
                ),
                [
                    'tenant_id' => $tenantId,
                    'metric' => $metric,
                    'period_key' => $periodKey,
                    'used_value' => $nextValue,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]
            );
        });
    }

    public function assertCanIssueApiToken(int $tenantId): void
    {
        $quota = $this->quota($tenantId);
        $limit = isset($quota['max_tokens']) ? (int) $quota['max_tokens'] : null;

        if ($limit === null || $limit < 1) {
            return;
        }

        $activeTokens = (int) ($this->queryExecutor->scalar(
            'SELECT COUNT(*) AS aggregate FROM ' . AdapterFactory::make()->quoteIdentifier('pb_api_tokens') . ' WHERE tenant_id = :tenant_id AND is_active = :is_active',
            [
                'tenant_id' => $tenantId,
                'is_active' => true,
            ]
        ) ?? 0);

        if ($activeTokens >= $limit) {
            throw new RuntimeException('The tenant API token quota has been exhausted.', 429);
        }
    }

    public function assertCanCreateEntity(int $tenantId): void
    {
        $quota = $this->quota($tenantId);
        $limit = isset($quota['max_entities']) ? (int) $quota['max_entities'] : null;

        if ($limit === null || $limit < 1) {
            return;
        }

        $total = 0;

        foreach (($this->registry ?? new CrudEntityRegistry())->all() as $entity) {
            if (!$entity->tenantScoped || !$entity->isExposed()) {
                continue;
            }

            $total += (int) ($this->queryExecutor->scalar(
                'SELECT COUNT(*) AS aggregate FROM ' . AdapterFactory::make()->quoteIdentifier($entity->table) . ' WHERE tenant_id = :tenant_id',
                ['tenant_id' => $tenantId]
            ) ?? 0);
        }

        if ($total >= $limit) {
            throw new RuntimeException('The tenant entity quota has been exhausted.', 429);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(int $tenantId): array
    {
        $quota = $this->quota($tenantId);
        $requestsUsed = (int) ($this->queryExecutor->scalar(
            sprintf(
                'SELECT used_value FROM %s WHERE tenant_id = :tenant_id AND metric = :metric AND period_key = :period_key LIMIT 1',
                $this->usageTable
            ),
            [
                'tenant_id' => $tenantId,
                'metric' => 'requests',
                'period_key' => gmdate('Y-m'),
            ]
        ) ?? 0);

        return [
            'requests' => [
                'limit' => isset($quota['max_requests_per_month']) ? (int) $quota['max_requests_per_month'] : null,
                'used' => $requestsUsed,
                'period' => gmdate('Y-m'),
            ],
            'tokens' => [
                'limit' => isset($quota['max_tokens']) ? (int) $quota['max_tokens'] : null,
            ],
            'entities' => [
                'limit' => isset($quota['max_entities']) ? (int) $quota['max_entities'] : null,
            ],
            'storage' => [
                'limit' => isset($quota['max_storage_bytes']) ? (int) $quota['max_storage_bytes'] : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function quota(int $tenantId): array
    {
        return $this->queryExecutor->selectOne(
            sprintf('SELECT * FROM %s WHERE tenant_id = :tenant_id LIMIT 1', $this->quotasTable),
            ['tenant_id' => $tenantId]
        ) ?? [];
    }
}
