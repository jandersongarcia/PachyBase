<?php

declare(strict_types=1);

namespace PachyBase\Api\Controllers;

use PachyBase\Auth\AuthorizationService;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;
use PachyBase\Services\Platform\StorageService;

final class PlatformStorageController
{
    public function __construct(
        private readonly ?StorageService $storage = null,
        private readonly ?AuthorizationService $authorization = null
    ) {
    }

    public function index(Request $request): void
    {
        $this->authorization()->authorize($request, ['storage:*', 'storage:read']);
        ApiResponse::success(
            ['items' => $this->service()->list($this->tenantId($request), (int) $request->query('limit', 50))],
            ['resource' => 'platform.storage.index']
        );
    }

    public function show(Request $request, string $id): void
    {
        $this->authorization()->authorize($request, ['storage:*', 'storage:read']);
        ApiResponse::success(
            $this->service()->show($this->tenantId($request), (int) $id),
            ['resource' => 'platform.storage.show']
        );
    }

    public function store(Request $request): void
    {
        $principal = $this->authorization()->authorize($request, ['storage:*', 'storage:write']);
        ApiResponse::success(
            $this->service()->store($this->tenantId($request), (string) $principal->tenantSlug, $request->json()),
            ['resource' => 'platform.storage.store'],
            201
        );
    }

    public function download(Request $request, string $id): void
    {
        $this->authorization()->authorize($request, ['storage:*', 'storage:read']);
        ApiResponse::success(
            $this->service()->download($this->tenantId($request), (int) $id),
            ['resource' => 'platform.storage.download']
        );
    }

    private function service(): StorageService
    {
        return $this->storage ?? new StorageService();
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
