<?php

declare(strict_types=1);

namespace PachyBase\Api\Controllers;

use PachyBase\Auth\AuthorizationService;
use PachyBase\Http\ApiResponse;
use PachyBase\Http\Request;
use PachyBase\Services\Audit\AuditLogger;
use PachyBase\Services\Crud\EntityCrudService;

final class CrudController
{
    public function __construct(
        private readonly ?EntityCrudService $service = null,
        private readonly ?AuthorizationService $authorization = null,
        private readonly ?AuditLogger $auditLogger = null
    ) {
    }

    public function index(Request $request, string $entity): void
    {
        $this->authorize($request, $entity, 'read');
        $result = ($this->service ?? new EntityCrudService())->list($entity, $request);
        $this->audit()->logCrud('crud.index.succeeded', $request, [
            'resource' => 'crud.index',
            'entity' => $result['entity'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total' => $result['total'],
            'item_count' => count($result['items']),
        ], 200);

        ApiResponse::paginated(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page'],
            [
                'resource' => 'crud.index',
                'entity' => $result['entity'],
            ]
        );
    }

    public function show(Request $request, string $entity, string $id): void
    {
        $this->authorize($request, $entity, 'read');
        $result = ($this->service ?? new EntityCrudService())->show($entity, $id);
        $this->audit()->logCrud('crud.show.succeeded', $request, [
            'resource' => 'crud.show',
            'entity' => $entity,
            'record_id' => $result['id'] ?? $id,
        ], 200);

        ApiResponse::success(
            $result,
            [
                'resource' => 'crud.show',
                'entity' => $entity,
            ]
        );
    }

    public function store(Request $request, string $entity): void
    {
        $this->authorize($request, $entity, 'create');
        $result = ($this->service ?? new EntityCrudService())->create($entity, $request->json());
        $this->audit()->logCrud('crud.record.created', $request, [
            'resource' => 'crud.store',
            'entity' => $entity,
            'record_id' => $result['id'] ?? null,
        ], 201);

        ApiResponse::success(
            $result,
            [
                'resource' => 'crud.store',
                'entity' => $entity,
            ],
            201
        );
    }

    public function replace(Request $request, string $entity, string $id): void
    {
        $this->authorize($request, $entity, 'update');
        $result = ($this->service ?? new EntityCrudService())->replace($entity, $id, $request->json());
        $this->audit()->logCrud('crud.record.replaced', $request, [
            'resource' => 'crud.replace',
            'entity' => $entity,
            'record_id' => $result['id'] ?? $id,
        ], 200);

        ApiResponse::success(
            $result,
            [
                'resource' => 'crud.replace',
                'entity' => $entity,
            ]
        );
    }

    public function update(Request $request, string $entity, string $id): void
    {
        $this->authorize($request, $entity, 'update');
        $result = ($this->service ?? new EntityCrudService())->patch($entity, $id, $request->json());
        $this->audit()->logCrud('crud.record.updated', $request, [
            'resource' => 'crud.update',
            'entity' => $entity,
            'record_id' => $result['id'] ?? $id,
        ], 200);

        ApiResponse::success(
            $result,
            [
                'resource' => 'crud.update',
                'entity' => $entity,
            ]
        );
    }

    public function destroy(Request $request, string $entity, string $id): void
    {
        $this->authorize($request, $entity, 'delete');
        $result = ($this->service ?? new EntityCrudService())->delete($entity, $id);
        $this->audit()->logCrud('crud.record.deleted', $request, [
            'resource' => 'crud.destroy',
            'entity' => $entity,
            'record_id' => $id,
            'result' => $result,
        ], 200);

        ApiResponse::success(
            $result,
            [
                'resource' => 'crud.destroy',
                'entity' => $entity,
            ]
        );
    }

    private function authorize(Request $request, string $entity, string $action): void
    {
        ($this->authorization ?? new AuthorizationService())->authorizeEntityAction($request, $entity, $action);
    }

    private function audit(): AuditLogger
    {
        return $this->auditLogger ?? new AuditLogger();
    }
}
