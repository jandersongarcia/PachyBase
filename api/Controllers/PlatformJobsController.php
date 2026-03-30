<?php

declare(strict_types=1);

namespace PachyBase\Api\Controllers;

use PachyBase\Auth\AuthorizationService;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;
use PachyBase\Services\Platform\AsyncJobService;

final class PlatformJobsController
{
    public function __construct(
        private readonly ?AsyncJobService $jobs = null,
        private readonly ?AuthorizationService $authorization = null
    ) {
    }

    public function index(Request $request): void
    {
        $this->authorization()->authorize($request, ['jobs:*', 'jobs:read']);
        ApiResponse::success(
            ['items' => $this->service()->list($this->tenantId($request), (int) $request->query('limit', 50))],
            ['resource' => 'platform.jobs.index']
        );
    }

    public function show(Request $request, string $id): void
    {
        $this->authorization()->authorize($request, ['jobs:*', 'jobs:read']);
        ApiResponse::success(
            $this->service()->show($this->tenantId($request), (int) $id),
            ['resource' => 'platform.jobs.show']
        );
    }

    public function store(Request $request): void
    {
        $this->authorization()->authorize($request, ['jobs:*', 'jobs:write']);
        ApiResponse::success(
            $this->service()->enqueue($this->tenantId($request), $request->json()),
            ['resource' => 'platform.jobs.store'],
            201
        );
    }

    public function run(Request $request): void
    {
        $this->authorization()->authorize($request, ['jobs:*', 'jobs:run']);
        ApiResponse::success(
            ['items' => $this->service()->runDue($this->tenantId($request), (int) $request->json('limit', 10))],
            ['resource' => 'platform.jobs.run']
        );
    }

    private function service(): AsyncJobService
    {
        return $this->jobs ?? new AsyncJobService();
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
