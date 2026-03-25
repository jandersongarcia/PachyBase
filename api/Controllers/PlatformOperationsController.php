<?php

declare(strict_types=1);

namespace PachyBase\Api\Controllers;

use PachyBase\Auth\AuthorizationService;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;
use PachyBase\Services\Platform\OperationsOverviewService;

final class PlatformOperationsController
{
    public function __construct(
        private readonly ?OperationsOverviewService $overview = null,
        private readonly ?AuthorizationService $authorization = null
    ) {
    }

    public function overview(Request $request): void
    {
        $this->authorization()->authorize($request, ['platform:*', 'operations:read']);
        ApiResponse::success(
            $this->service()->overview(),
            ['resource' => 'platform.operations.overview']
        );
    }

    private function service(): OperationsOverviewService
    {
        return $this->overview ?? new OperationsOverviewService();
    }

    private function authorization(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService();
    }
}
