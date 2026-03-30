<?php

declare(strict_types=1);

namespace PachyBase\Api\Controllers;

use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;
use PachyBase\Services\SystemStatusService;

final class SystemController
{
    public function __construct(
        private readonly ?SystemStatusService $statusService = null
    ) {
    }

    public function status(Request $request): void
    {
        $service = $this->statusService ?? new SystemStatusService();

        ApiResponse::success(
            $service->buildStatusPayload($request),
            ['resource' => 'system.status']
        );
    }

    public function health(Request $request): void
    {
        $service = $this->statusService ?? new SystemStatusService();
        $payload = $service->buildHealthPayload($request);

        ApiResponse::success(
            $payload,
            ['resource' => 'system.health'],
            200
        );
    }

    public function deepHealth(Request $request): void
    {
        $service = $this->statusService ?? new SystemStatusService();
        $payload = $service->buildHealthPayload($request, true);

        ApiResponse::success(
            $payload,
            ['resource' => 'system.health.deep'],
            ($payload['status'] ?? 'degraded') === 'ok' ? 200 : 503
        );
    }
}
