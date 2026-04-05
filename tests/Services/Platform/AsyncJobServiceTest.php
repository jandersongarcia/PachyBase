<?php

declare(strict_types=1);

namespace Tests\Services\Platform;

use PachyBase\Services\Platform\AsyncJobService;
use PachyBase\Services\Platform\WebhookService;
use RuntimeException;
use Tests\Support\DatabaseIntegrationTestCase;
use Tests\Support\TemporaryHttpServer;

class AsyncJobServiceTest extends DatabaseIntegrationTestCase
{
    private ?TemporaryHttpServer $server = null;

    protected function tearDown(): void
    {
        $this->server?->stop();
        $this->server = null;

        parent::tearDown();
    }

    public function testEnqueueAndRunDueCompleteNoopJobs(): void
    {
        $tenant = $this->createTenant();
        $service = new AsyncJobService($this->executor);

        $job = $service->enqueue($tenant['id'], [
            'type' => 'noop',
            'payload' => ['message' => 'queued'],
        ]);
        $processed = $service->runDue($tenant['id'], 5);
        $stored = $service->show($tenant['id'], (int) $job['id']);

        $this->assertSame('pending', $job['status']);
        $this->assertCount(1, $processed);
        $this->assertSame('completed', $stored['status']);
        $this->assertStringContainsString('"handled":true', (string) $stored['result_json']);
    }

    public function testEnqueueRejectsMissingTypeAndInvalidPayloadObjects(): void
    {
        $tenant = $this->createTenant();
        $service = new AsyncJobService($this->executor);

        try {
            $service->enqueue($tenant['id'], ['payload' => []]);
            $this->fail('Expected missing type validation.');
        } catch (RuntimeException $exception) {
            $this->assertSame(422, $exception->getCode());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(422);

        $service->enqueue($tenant['id'], [
            'type' => 'noop',
            'payload' => 'invalid',
        ]);
    }

    public function testRunDueCompletesHttpRequestAndWebhookDeliveryJobs(): void
    {
        $tenant = $this->createTenant();
        $this->server = TemporaryHttpServer::start();
        $webhooks = new WebhookService($this->executor);
        $jobs = new AsyncJobService($this->executor, $webhooks);
        $webhook = $webhooks->create($tenant['id'], [
            'name' => 'Delivery Hook',
            'event_name' => 'order.created',
            'target_url' => $this->server->baseUrl() . '/ok',
            'secret' => 'shared-secret',
        ]);

        $httpJob = $jobs->enqueue($tenant['id'], [
            'type' => 'http.request',
            'payload' => [
                'url' => $this->server->baseUrl() . '/ok',
                'method' => 'POST',
                'body' => ['job' => 'http.request'],
            ],
        ]);
        $webhookJob = $jobs->enqueue($tenant['id'], [
            'type' => 'webhook.delivery',
            'payload' => [
                'webhook_id' => (int) $webhook['id'],
                'event' => 'order.created',
                'payload' => ['order_id' => 123],
            ],
        ]);

        $processed = $jobs->runDue($tenant['id'], 10);
        $storedHttp = $jobs->show($tenant['id'], (int) $httpJob['id']);
        $storedWebhook = $jobs->show($tenant['id'], (int) $webhookJob['id']);
        $delivery = $this->executor?->selectOne(
            sprintf('SELECT * FROM %s WHERE webhook_id = :webhook_id ORDER BY id DESC LIMIT 1', $this->table('pb_webhook_deliveries')),
            ['webhook_id' => (int) $webhook['id']]
        );

        $this->assertCount(2, $processed);
        $this->assertSame('completed', $storedHttp['status']);
        $this->assertStringContainsString('"status_code":200', (string) $storedHttp['result_json']);
        $this->assertSame('completed', $storedWebhook['status']);
        $this->assertStringContainsString('"delivered":true', (string) $storedWebhook['result_json']);
        $this->assertSame('delivered', $delivery['status']);
    }

    public function testUnsupportedJobsRetryAndEventuallyBecomeFailed(): void
    {
        $tenant = $this->createTenant();
        $service = new AsyncJobService($this->executor);
        $job = $service->enqueue($tenant['id'], [
            'type' => 'unsupported.job',
            'payload' => ['anything' => true],
            'max_attempts' => 2,
        ]);

        $firstPass = $service->runDue($tenant['id'], 1);
        $secondPass = $service->runDue($tenant['id'], 1);
        $stored = $service->show($tenant['id'], (int) $job['id']);

        $this->assertSame('pending', $firstPass[0]['status']);
        $this->assertSame(1, (int) $firstPass[0]['attempts']);
        $this->assertSame('failed', $secondPass[0]['status']);
        $this->assertSame(2, (int) $stored['attempts']);
        $this->assertSame('failed', $stored['status']);
        $this->assertStringContainsString('Unsupported job type', (string) $stored['last_error']);
    }
}
