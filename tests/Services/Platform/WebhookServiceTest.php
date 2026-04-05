<?php

declare(strict_types=1);

namespace Tests\Services\Platform;

use PachyBase\Services\Platform\WebhookService;
use RuntimeException;
use Tests\Support\DatabaseIntegrationTestCase;
use Tests\Support\TemporaryHttpServer;

class WebhookServiceTest extends DatabaseIntegrationTestCase
{
    private ?TemporaryHttpServer $server = null;

    protected function tearDown(): void
    {
        $this->server?->stop();
        $this->server = null;

        parent::tearDown();
    }

    public function testCreateUpdateDeleteAndQueueTestManageWebhookRecords(): void
    {
        $tenant = $this->createTenant();
        $this->server = TemporaryHttpServer::start();
        $service = new WebhookService($this->executor);

        $created = $service->create($tenant['id'], [
            'name' => 'Orders Hook',
            'event_name' => 'order.created',
            'target_url' => $this->server->baseUrl() . '/ok',
            'secret' => 'super-secret',
        ]);
        $updated = $service->update($tenant['id'], (int) $created['id'], [
            'name' => 'Orders Hook v2',
            'event_name' => 'order.updated',
            'target_url' => $this->server->baseUrl() . '/ok',
            'secret' => 'new-secret',
            'is_active' => false,
        ]);
        $queued = $service->queueTest($tenant['id'], (int) $created['id']);
        $job = $this->executor?->selectOne(
            sprintf('SELECT * FROM %s WHERE id = :id LIMIT 1', $this->table('pb_async_jobs')),
            ['id' => $queued['job_id']]
        );
        $deleted = $service->delete($tenant['id'], (int) $created['id']);

        $this->assertSame('Orders Hook', $created['name']);
        $this->assertSame('Orders Hook v2', $updated['name']);
        $this->assertFalse((bool) $updated['is_active']);
        $this->assertTrue($queued['queued']);
        $this->assertStringContainsString('webhook.test', (string) ($job['payload_json'] ?? ''));
        $this->assertTrue($deleted['deleted']);
    }

    public function testCreateRequiresWebhookSecret(): void
    {
        $tenant = $this->createTenant();
        $service = new WebhookService($this->executor);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(422);

        $service->create($tenant['id'], [
            'name' => 'Missing Secret',
            'event_name' => 'order.created',
            'target_url' => 'http://127.0.0.1/ignored',
        ]);
    }

    public function testDeliverFromJobPayloadRejectsInactiveWebhooks(): void
    {
        $tenant = $this->createTenant();
        $this->server = TemporaryHttpServer::start();
        $service = new WebhookService($this->executor);
        $webhook = $service->create($tenant['id'], [
            'name' => 'Inactive Hook',
            'event_name' => 'order.created',
            'target_url' => $this->server->baseUrl() . '/ok',
            'secret' => 'inactive-secret',
        ]);

        $service->update($tenant['id'], (int) $webhook['id'], [
            'name' => 'Inactive Hook',
            'event_name' => 'order.created',
            'target_url' => $this->server->baseUrl() . '/ok',
            'is_active' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(404);

        $service->deliverFromJobPayload([
            'webhook_id' => (int) $webhook['id'],
            'event' => 'order.created',
            'payload' => ['id' => 55],
        ]);
    }

    public function testDeliverFromJobPayloadPersistsFailedDeliveryWhenTargetReturnsNon2xx(): void
    {
        $tenant = $this->createTenant();
        $this->server = TemporaryHttpServer::start();
        $service = new WebhookService($this->executor);
        $webhook = $service->create($tenant['id'], [
            'name' => 'Failing Hook',
            'event_name' => 'order.created',
            'target_url' => $this->server->baseUrl() . '/fail',
            'secret' => 'failure-secret',
        ]);

        try {
            $service->deliverFromJobPayload([
                'webhook_id' => (int) $webhook['id'],
                'event' => 'order.created',
                'payload' => ['id' => 77],
            ]);
            $this->fail('Expected delivery failure.');
        } catch (RuntimeException $exception) {
            $delivery = $this->executor?->selectOne(
                sprintf('SELECT * FROM %s WHERE webhook_id = :webhook_id ORDER BY id DESC LIMIT 1', $this->table('pb_webhook_deliveries')),
                ['webhook_id' => (int) $webhook['id']]
            );

            $this->assertSame(502, $exception->getCode());
            $this->assertSame('failed', $delivery['status']);
            $this->assertSame(500, (int) ($delivery['response_status_code'] ?? 0));
            $this->assertStringContainsString('order.created', (string) $delivery['request_payload']);
        }
    }
}
