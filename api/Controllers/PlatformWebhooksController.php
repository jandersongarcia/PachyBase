<?php

declare(strict_types=1);

namespace PachyBase\Api\Controllers;

use PachyBase\Auth\AuthorizationService;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;
use PachyBase\Services\Platform\WebhookService;

final class PlatformWebhooksController
{
    public function __construct(
        private readonly ?WebhookService $webhooks = null,
        private readonly ?AuthorizationService $authorization = null
    ) {
    }

    public function index(Request $request): void
    {
        $this->authorization()->authorize($request, ['webhooks:*', 'webhooks:read']);
        ApiResponse::success(
            ['items' => $this->service()->list($this->tenantId($request), (int) $request->query('limit', 50))],
            ['resource' => 'platform.webhooks.index']
        );
    }

    public function deliveries(Request $request): void
    {
        $this->authorization()->authorize($request, ['webhooks:*', 'webhooks:read']);
        ApiResponse::success(
            ['items' => $this->service()->listDeliveries($this->tenantId($request), (int) $request->query('limit', 50))],
            ['resource' => 'platform.webhooks.deliveries.index']
        );
    }

    public function show(Request $request, string $id): void
    {
        $this->authorization()->authorize($request, ['webhooks:*', 'webhooks:read']);
        ApiResponse::success(
            $this->service()->show($this->tenantId($request), (int) $id),
            ['resource' => 'platform.webhooks.show']
        );
    }

    public function store(Request $request): void
    {
        $this->authorization()->authorize($request, ['webhooks:*', 'webhooks:write']);
        ApiResponse::success(
            $this->service()->create($this->tenantId($request), $request->json()),
            ['resource' => 'platform.webhooks.store'],
            201
        );
    }

    public function update(Request $request, string $id): void
    {
        $this->authorization()->authorize($request, ['webhooks:*', 'webhooks:write']);
        ApiResponse::success(
            $this->service()->update($this->tenantId($request), (int) $id, $request->json()),
            ['resource' => 'platform.webhooks.update']
        );
    }

    public function destroy(Request $request, string $id): void
    {
        $this->authorization()->authorize($request, ['webhooks:*', 'webhooks:write']);
        ApiResponse::success(
            $this->service()->delete($this->tenantId($request), (int) $id),
            ['resource' => 'platform.webhooks.destroy']
        );
    }

    public function test(Request $request, string $id): void
    {
        $this->authorization()->authorize($request, ['webhooks:*', 'webhooks:write', 'webhooks:deliver']);
        ApiResponse::success(
            $this->service()->queueTest($this->tenantId($request), (int) $id),
            ['resource' => 'platform.webhooks.test'],
            202
        );
    }

    private function service(): WebhookService
    {
        return $this->webhooks ?? new WebhookService();
    }

    private function authorization(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService();
    }

    private function tenantId(Request $request): int
    {
        return (int) $request->attribute('auth.tenant_id');
    }
}
