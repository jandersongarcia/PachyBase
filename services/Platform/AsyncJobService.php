<?php

declare(strict_types=1);

namespace PachyBase\Services\Platform;

use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Query\QueryExecutorInterface;
use PachyBase\Utils\Json;
use RuntimeException;

final class AsyncJobService
{
    private readonly QueryExecutorInterface $queryExecutor;
    private readonly string $jobsTable;

    public function __construct(
        ?QueryExecutorInterface $queryExecutor = null,
        private readonly ?WebhookService $webhooks = null
    ) {
        $connection = Connection::getInstance();
        $this->queryExecutor = $queryExecutor ?? new PdoQueryExecutor($connection->getPDO());
        $this->jobsTable = AdapterFactory::make($connection)->quoteIdentifier('pb_async_jobs');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(int $tenantId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        return $this->queryExecutor->select(
            sprintf('SELECT * FROM %s WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT %d', $this->jobsTable, $limit),
            ['tenant_id' => $tenantId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $tenantId, int $jobId): array
    {
        $row = $this->queryExecutor->selectOne(
            sprintf('SELECT * FROM %s WHERE id = :id AND tenant_id = :tenant_id LIMIT 1', $this->jobsTable),
            ['id' => $jobId, 'tenant_id' => $tenantId]
        );

        if ($row === null) {
            throw new RuntimeException('Job not found for the authenticated project.', 404);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function enqueue(int $tenantId, array $payload): array
    {
        $type = trim((string) ($payload['type'] ?? ''));
        $jobPayload = $payload['payload'] ?? [];
        $availableAt = trim((string) ($payload['available_at'] ?? '')) ?: gmdate('Y-m-d H:i:s');
        $maxAttempts = max(1, min((int) ($payload['max_attempts'] ?? 3), 10));

        if ($type === '') {
            throw new RuntimeException('Job type is required.', 422);
        }

        if (!is_array($jobPayload)) {
            throw new RuntimeException('Job payload must be a JSON object.', 422);
        }

        $this->queryExecutor->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, type, status, payload_json, available_at, max_attempts) VALUES (:tenant_id, :type, :status, :payload_json, :available_at, :max_attempts)',
                $this->jobsTable
            ),
            [
                'tenant_id' => $tenantId,
                'type' => $type,
                'status' => 'pending',
                'payload_json' => Json::encode($jobPayload),
                'available_at' => $availableAt,
                'max_attempts' => $maxAttempts,
            ]
        );

        return $this->show($tenantId, (int) Connection::getInstance()->getPDO()->lastInsertId());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function runDue(int $tenantId, int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));
        $results = [];

        for ($index = 0; $index < $limit; $index++) {
            $job = $this->claimNext($tenantId);

            if ($job === null) {
                break;
            }

            $results[] = $this->process($job);
        }

        return $results;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function claimNext(int $tenantId): ?array
    {
        return $this->queryExecutor->transaction(function (QueryExecutorInterface $queryExecutor) use ($tenantId): ?array {
            $row = $queryExecutor->selectOne(
                sprintf(
                    'SELECT * FROM %s WHERE tenant_id = :tenant_id AND status = :status AND available_at <= :available_at ORDER BY id ASC LIMIT 1',
                    $this->jobsTable
                ),
                [
                    'tenant_id' => $tenantId,
                    'status' => 'pending',
                    'available_at' => gmdate('Y-m-d H:i:s'),
                ]
            );

            if ($row === null) {
                return null;
            }

            $queryExecutor->execute(
                sprintf('UPDATE %s SET status = :next_status, started_at = :started_at, updated_at = :updated_at WHERE id = :id', $this->jobsTable),
                [
                    'id' => (int) $row['id'],
                    'next_status' => 'running',
                    'started_at' => gmdate('Y-m-d H:i:s'),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]
            );

            $row['status'] = 'running';

            return $row;
        });
    }

    /**
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    private function process(array $job): array
    {
        $payload = json_decode((string) ($job['payload_json'] ?? '{}'), true);

        if (!is_array($payload)) {
            $payload = [];
        }

        try {
            $result = match ((string) $job['type']) {
                'noop' => ['handled' => true, 'payload' => $payload],
                'http.request' => $this->dispatchHttpRequest($payload),
                'webhook.delivery' => $this->webhooks()->deliverFromJobPayload($payload),
                default => throw new RuntimeException(sprintf('Unsupported job type "%s".', (string) $job['type']), 422),
            };

            $this->queryExecutor->execute(
                sprintf(
                    'UPDATE %s SET status = :status, result_json = :result_json, attempts = :attempts, finished_at = :finished_at, last_error = :last_error, updated_at = :updated_at WHERE id = :id',
                    $this->jobsTable
                ),
                [
                    'id' => (int) $job['id'],
                    'status' => 'completed',
                    'result_json' => Json::encode($result),
                    'attempts' => ((int) ($job['attempts'] ?? 0)) + 1,
                    'finished_at' => gmdate('Y-m-d H:i:s'),
                    'last_error' => null,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]
            );

            return $this->show((int) $job['tenant_id'], (int) $job['id']);
        } catch (RuntimeException $exception) {
            $attempts = ((int) ($job['attempts'] ?? 0)) + 1;
            $terminal = $attempts >= (int) ($job['max_attempts'] ?? 3);

            $this->queryExecutor->execute(
                sprintf(
                    'UPDATE %s SET status = :status, attempts = :attempts, last_error = :last_error, finished_at = :finished_at, updated_at = :updated_at WHERE id = :id',
                    $this->jobsTable
                ),
                [
                    'id' => (int) $job['id'],
                    'status' => $terminal ? 'failed' : 'pending',
                    'attempts' => $attempts,
                    'last_error' => $exception->getMessage(),
                    'finished_at' => $terminal ? gmdate('Y-m-d H:i:s') : null,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]
            );

            return $this->show((int) $job['tenant_id'], (int) $job['id']);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function dispatchHttpRequest(array $payload): array
    {
        $url = trim((string) ($payload['url'] ?? ''));

        if ($url === '') {
            throw new RuntimeException('The http.request job requires a URL.', 422);
        }

        $method = strtoupper(trim((string) ($payload['method'] ?? 'POST')));
        $headers = is_array($payload['headers'] ?? null) ? $payload['headers'] : [];
        $body = Json::encode($payload['body'] ?? []);
        $headerLines = ['Content-Type: application/json'];

        foreach ($headers as $name => $value) {
            if (!is_string($name)) {
                continue;
            }

            $headerLines[] = $name . ': ' . (string) $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        $statusLine = is_array($http_response_header ?? null) ? (string) ($http_response_header[0] ?? '') : '';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $statusCode = isset($matches[1]) ? (int) $matches[1] : 0;

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf('HTTP job failed with status code %d.', $statusCode), 502);
        }

        return [
            'status_code' => $statusCode,
            'response_body' => $responseBody === false ? '' : $responseBody,
        ];
    }

    private function webhooks(): WebhookService
    {
        return $this->webhooks ?? new WebhookService($this->queryExecutor);
    }
}
