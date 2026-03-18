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
}
