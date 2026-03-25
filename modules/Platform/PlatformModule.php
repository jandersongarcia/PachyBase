<?php

declare(strict_types=1);

namespace PachyBase\Modules\Platform;

use PachyBase\Api\Controllers\PlatformJobsController;
use PachyBase\Api\Controllers\PlatformOperationsController;
use PachyBase\Api\Controllers\PlatformProjectsController;
use PachyBase\Api\Controllers\PlatformStorageController;
use PachyBase\Api\Controllers\PlatformWebhooksController;
use PachyBase\Auth\Middleware\RequireBearerToken;
use PachyBase\Http\Router;

final class PlatformModule
{
    public function register(Router $router): void
    {
        $router->get('/api/platform/projects', [PlatformProjectsController::class, 'index'])->middleware([RequireBearerToken::class]);
        $router->get('/api/platform/projects/{project}', [PlatformProjectsController::class, 'show'])->middleware([RequireBearerToken::class]);
        $router->post('/api/platform/projects', [PlatformProjectsController::class, 'provision'])->middleware([RequireBearerToken::class]);
        $router->get('/api/platform/projects/{project}/backups', [PlatformProjectsController::class, 'backups'])->middleware([RequireBearerToken::class]);
        $router->post('/api/platform/projects/{project}/backups', [PlatformProjectsController::class, 'backup'])->middleware([RequireBearerToken::class]);
        $router->post('/api/platform/projects/{project}/restore', [PlatformProjectsController::class, 'restore'])->middleware([RequireBearerToken::class]);
        $router->get('/api/platform/projects/{project}/secrets', [PlatformProjectsController::class, 'listSecrets'])->middleware([RequireBearerToken::class]);
        $router->get('/api/platform/projects/{project}/secrets/{key}', [PlatformProjectsController::class, 'revealSecret'])->middleware([RequireBearerToken::class]);
        $router->put('/api/platform/projects/{project}/secrets/{key}', [PlatformProjectsController::class, 'putSecret'])->middleware([RequireBearerToken::class]);
        $router->delete('/api/platform/projects/{project}/secrets/{key}', [PlatformProjectsController::class, 'deleteSecret'])->middleware([RequireBearerToken::class]);

        $router->get('/api/platform/operations/overview', [PlatformOperationsController::class, 'overview'])->middleware([RequireBearerToken::class]);

        $router->get('/api/platform/jobs', [PlatformJobsController::class, 'index'])->middleware([RequireBearerToken::class]);
        $router->get('/api/platform/jobs/{id}', [PlatformJobsController::class, 'show'])->middleware([RequireBearerToken::class]);
        $router->post('/api/platform/jobs', [PlatformJobsController::class, 'store'])->middleware([RequireBearerToken::class]);
        $router->post('/api/platform/jobs/run', [PlatformJobsController::class, 'run'])->middleware([RequireBearerToken::class]);

        $router->get('/api/platform/webhooks', [PlatformWebhooksController::class, 'index'])->middleware([RequireBearerToken::class]);
        $router->get('/api/platform/webhooks/{id}', [PlatformWebhooksController::class, 'show'])->middleware([RequireBearerToken::class]);
        $router->post('/api/platform/webhooks', [PlatformWebhooksController::class, 'store'])->middleware([RequireBearerToken::class]);
        $router->put('/api/platform/webhooks/{id}', [PlatformWebhooksController::class, 'update'])->middleware([RequireBearerToken::class]);
        $router->delete('/api/platform/webhooks/{id}', [PlatformWebhooksController::class, 'destroy'])->middleware([RequireBearerToken::class]);
        $router->post('/api/platform/webhooks/{id}/test', [PlatformWebhooksController::class, 'test'])->middleware([RequireBearerToken::class]);
        $router->get('/api/platform/webhook-deliveries', [PlatformWebhooksController::class, 'deliveries'])->middleware([RequireBearerToken::class]);

        $router->get('/api/platform/storage', [PlatformStorageController::class, 'index'])->middleware([RequireBearerToken::class]);
        $router->get('/api/platform/storage/{id}', [PlatformStorageController::class, 'show'])->middleware([RequireBearerToken::class]);
        $router->post('/api/platform/storage', [PlatformStorageController::class, 'store'])->middleware([RequireBearerToken::class]);
        $router->get('/api/platform/storage/{id}/download', [PlatformStorageController::class, 'download'])->middleware([RequireBearerToken::class]);
    }
}
