<?php

declare(strict_types=1);

namespace PachyBase\Api\Controllers;

use PachyBase\Auth\AuthorizationService;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;
use PachyBase\Services\Platform\ProjectPlatformService;

final class PlatformProjectsController
{
    public function __construct(
        private readonly ?ProjectPlatformService $projects = null,
        private readonly ?AuthorizationService $authorization = null
    ) {
    }

    public function index(Request $request): void
    {
        $this->authorization()->authorize($request, ['platform:*', 'projects:read']);
        ApiResponse::success(
            ['items' => $this->service()->listProjects((int) $request->query('limit', 100))],
            ['resource' => 'platform.projects.index']
        );
    }

    public function show(Request $request, string $project): void
    {
        $this->authorization()->authorize($request, ['platform:*', 'projects:read']);
        ApiResponse::success(
            $this->service()->showProject($project),
            ['resource' => 'platform.projects.show']
        );
    }

    public function provision(Request $request): void
    {
        $this->authorization()->authorize($request, ['platform:*', 'projects:write', 'projects:provision']);
        ApiResponse::success(
            $this->service()->provisionProject($request->json()),
            ['resource' => 'platform.projects.provision'],
            201
        );
    }

    public function backups(Request $request, string $project): void
    {
        $this->authorization()->authorize($request, ['platform:*', 'projects:read', 'projects:backup']);
        ApiResponse::success(
            ['items' => $this->service()->listBackups($project, (int) $request->query('limit', 20))],
            ['resource' => 'platform.projects.backups.index']
        );
    }

    public function backup(Request $request, string $project): void
    {
        $principal = $this->authorization()->authorize($request, ['platform:*', 'projects:backup', 'projects:write']);
        ApiResponse::success(
            $this->service()->createBackup(
                $project,
                $principal->userId,
                trim((string) $request->json('label', '')) ?: null
            ),
            ['resource' => 'platform.projects.backups.store'],
            201
        );
    }

    public function restore(Request $request, string $project): void
    {
        $this->authorization()->authorize($request, ['platform:*', 'projects:restore', 'projects:write']);
        ApiResponse::success(
            $this->service()->restoreBackup($project, (int) $request->json('backup_id', 0)),
            ['resource' => 'platform.projects.restore']
        );
    }

    public function listSecrets(Request $request, string $project): void
    {
        $this->authorization()->authorize($request, ['platform:*', 'projects:read', 'secrets:*', 'secrets:read']);
        ApiResponse::success(
            ['items' => $this->service()->listSecrets($project)],
            ['resource' => 'platform.projects.secrets.index']
        );
    }

    public function revealSecret(Request $request, string $project, string $key): void
    {
        $this->authorization()->authorize($request, ['platform:*', 'secrets:*', 'secrets:read']);
        ApiResponse::success(
            $this->service()->revealSecret($project, $key),
            ['resource' => 'platform.projects.secrets.show']
        );
    }

    public function putSecret(Request $request, string $project, string $key): void
    {
        $this->authorization()->authorize($request, ['platform:*', 'secrets:*', 'secrets:write']);
        ApiResponse::success(
            $this->service()->putSecret($project, $key, (string) $request->json('value', '')),
            ['resource' => 'platform.projects.secrets.put']
        );
    }

    public function deleteSecret(Request $request, string $project, string $key): void
    {
        $this->authorization()->authorize($request, ['platform:*', 'secrets:*', 'secrets:write']);
        ApiResponse::success(
            $this->service()->deleteSecret($project, $key),
            ['resource' => 'platform.projects.secrets.destroy']
        );
    }

    private function service(): ProjectPlatformService
    {
        return $this->projects ?? new ProjectPlatformService();
    }

    private function authorization(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService();
    }
}
