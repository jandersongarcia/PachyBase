<?php

declare(strict_types=1);

namespace PachyBase\Services\Platform;

use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Query\QueryExecutorInterface;
use PachyBase\Utils\Crypto;
use PachyBase\Utils\Json;
use RuntimeException;

final class WebhookService
{
    private readonly QueryExecutorInterface $queryExecutor;
    private readonly string $webhooksTable;
    private readonly string $deliveriesTable;
    private readonly string $jobsTable;

    public function __construct(?QueryExecutorInterface $queryExecutor = null)
    {
        $connection = Connection::getInstance();
        $this->queryExecutor = $queryExecutor ?? new PdoQueryExecutor($connection->getPDO());
        $adapter = AdapterFactory::make($connection);
        $this->webhooksTable = $adapter->quoteIdentifier('pb_webhooks');
        $this->deliveriesTable = $adapter->quoteIdentifier('pb_webhook_deliveries');
        $this->jobsTable = $adapter->quoteIdentifier('pb_async_jobs');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(int $tenantId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        return $this->queryExecutor->select(
            sprintf(
                'SELECT id, tenant_id, name, event_name, target_url, is_active, created_at, updated_at
                 FROM %s
                 WHERE tenant_id = :tenant_id
                 ORDER BY id DESC
                 LIMIT %d',
                $this->webhooksTable,
                $limit
            ),
            ['tenant_id' => $tenantId]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDeliveries(int $tenantId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));

        return $this->queryExecutor->select(
            sprintf('SELECT * FROM %s WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT %d', $this->deliveriesTable, $limit),
            ['tenant_id' => $tenantId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $tenantId, int $webhookId): array
    {
        $row = $this->queryExecutor->selectOne(
            sprintf(
                'SELECT id, tenant_id, name, event_name, target_url, is_active, created_at, updated_at
                 FROM %s
                 WHERE id = :id AND tenant_id = :tenant_id
                 LIMIT 1',
                $this->webhooksTable
            ),
            ['id' => $webhookId, 'tenant_id' => $tenantId]
        );

        if ($row === null) {
            throw new RuntimeException('Webhook not found for the authenticated project.', 404);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(int $tenantId, array $payload): array
    {
        $data = $this->normalizePayload($payload);
        $this->queryExecutor->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, name, event_name, target_url, signing_secret_encrypted, is_active) VALUES (:tenant_id, :name, :event_name, :target_url, :signing_secret_encrypted, :is_active)',
                $this->webhooksTable
            ),
            array_merge(['tenant_id' => $tenantId], $data)
        );

        return $this->show($tenantId, (int) Connection::getInstance()->getPDO()->lastInsertId());
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(int $tenantId, int $webhookId, array $payload): array
    {
        $existing = $this->rawWebhook($tenantId, $webhookId);
        $data = $this->normalizePayload(array_merge($existing, $payload), true, (string) ($existing['signing_secret_encrypted'] ?? ''));
        $this->queryExecutor->execute(
            sprintf(
                'UPDATE %s SET name = :name, event_name = :event_name, target_url = :target_url, signing_secret_encrypted = :signing_secret_encrypted, is_active = :is_active, updated_at = :updated_at WHERE id = :id AND tenant_id = :tenant_id',
                $this->webhooksTable
            ),
            array_merge(
                ['tenant_id' => $tenantId, 'id' => $webhookId, 'updated_at' => gmdate('Y-m-d H:i:s')],
                $data
            )
        );

        return $this->show($tenantId, $webhookId);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(int $tenantId, int $webhookId): array
    {
        $deleted = $this->queryExecutor->execute(
            sprintf('DELETE FROM %s WHERE id = :id AND tenant_id = :tenant_id', $this->webhooksTable),
            ['id' => $webhookId, 'tenant_id' => $tenantId]
        );

        return ['deleted' => $deleted > 0, 'id' => $webhookId];
    }

    /**
     * @return array<string, mixed>
     */
    public function queueTest(int $tenantId, int $webhookId): array
    {
        $webhook = $this->rawWebhook($tenantId, $webhookId);
        $this->queryExecutor->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, type, status, payload_json, available_at, max_attempts) VALUES (:tenant_id, :type, :status, :payload_json, :available_at, :max_attempts)',
                $this->jobsTable
            ),
            [
                'tenant_id' => $tenantId,
                'type' => 'webhook.delivery',
                'status' => 'pending',
                'payload_json' => Json::encode([
                    'webhook_id' => $webhookId,
                    'event' => 'webhook.test',
                    'payload' => [
                        'message' => 'PachyBase webhook connectivity test',
                        'webhook' => [
                            'id' => (int) $webhook['id'],
                            'name' => (string) $webhook['name'],
                        ],
                    ],
                ]),
                'available_at' => gmdate('Y-m-d H:i:s'),
                'max_attempts' => 3,
            ]
        );

        return [
            'queued' => true,
            'job_id' => (int) Connection::getInstance()->getPDO()->lastInsertId(),
            'webhook_id' => $webhookId,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function deliverFromJobPayload(array $payload): array
    {
        $webhookId = (int) ($payload['webhook_id'] ?? 0);
        $event = trim((string) ($payload['event'] ?? ''));
        $bodyPayload = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];

        if ($webhookId < 1 || $event === '') {
            throw new RuntimeException('Webhook delivery jobs require webhook_id and event.', 422);
        }

        $webhook = $this->queryExecutor->selectOne(
            sprintf('SELECT * FROM %s WHERE id = :id AND is_active = :is_active LIMIT 1', $this->webhooksTable),
            ['id' => $webhookId, 'is_active' => true]
        );

        if ($webhook === null) {
            throw new RuntimeException('Webhook not found or inactive.', 404);
        }

        $body = Json::encode([
            'event' => $event,
            'data' => $bodyPayload,
            'timestamp' => gmdate('c'),
        ]);
        $secret = trim((string) ($webhook['signing_secret_encrypted'] ?? ''));
        $signature = $secret !== ''
            ? 'sha256=' . hash_hmac('sha256', $body, Crypto::decryptString($secret))
            : '';
        $headers = [
            'Content-Type: application/json',
            'User-Agent: PachyBase Webhooks/1.0',
            'X-PachyBase-Event: ' . $event,
        ];

        if ($signature !== '') {
            $headers[] = 'X-PachyBase-Signature: ' . $signature;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ]);
        $responseBody = @file_get_contents((string) $webhook['target_url'], false, $context);
        $statusLine = is_array($http_response_header ?? null) ? (string) ($http_response_header[0] ?? '') : '';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $statusCode = isset($matches[1]) ? (int) $matches[1] : 0;

        $this->queryExecutor->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, webhook_id, event_name, status, request_payload, response_status_code, response_body, attempted_at) VALUES (:tenant_id, :webhook_id, :event_name, :status, :request_payload, :response_status_code, :response_body, :attempted_at)',
                $this->deliveriesTable
            ),
            [
                'tenant_id' => (int) $webhook['tenant_id'],
                'webhook_id' => $webhookId,
                'event_name' => $event,
                'status' => $statusCode >= 200 && $statusCode < 300 ? 'delivered' : 'failed',
                'request_payload' => $body,
                'response_status_code' => $statusCode > 0 ? $statusCode : null,
                'response_body' => $responseBody === false ? '' : $responseBody,
                'attempted_at' => gmdate('Y-m-d H:i:s'),
            ]
        );

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf('Webhook delivery failed with status code %d.', $statusCode), 502);
        }

        return [
            'delivered' => true,
            'webhook_id' => $webhookId,
            'event' => $event,
            'status_code' => $statusCode,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload, bool $allowEmptySecret = false, string $fallbackSecret = ''): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $event = trim((string) ($payload['event_name'] ?? ''));
        $targetUrl = trim((string) ($payload['target_url'] ?? ''));
        $secret = (string) ($payload['secret'] ?? '');

        if ($name === '' || $event === '' || $targetUrl === '') {
            throw new RuntimeException('Webhook name, event_name, and target_url are required.', 422);
        }

        if (!$allowEmptySecret && trim($secret) === '') {
            throw new RuntimeException('Webhook secret is required.', 422);
        }

        return [
            'name' => $name,
            'event_name' => $event,
            'target_url' => $targetUrl,
            'signing_secret_encrypted' => trim($secret) !== '' ? Crypto::encryptString($secret) : $fallbackSecret,
            'is_active' => (bool) ($payload['is_active'] ?? true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rawWebhook(int $tenantId, int $webhookId): array
    {
        $row = $this->queryExecutor->selectOne(
            sprintf('SELECT * FROM %s WHERE id = :id AND tenant_id = :tenant_id LIMIT 1', $this->webhooksTable),
            ['id' => $webhookId, 'tenant_id' => $tenantId]
        );

        if ($row === null) {
            throw new RuntimeException('Webhook not found for the authenticated project.', 404);
        }

        return $row;
    }
}
