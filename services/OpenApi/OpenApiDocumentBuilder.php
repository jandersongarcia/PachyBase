<?php

declare(strict_types=1);

namespace PachyBase\Services\OpenApi;

use PachyBase\Api\Controllers\AiController;
use PachyBase\Api\Controllers\AuthController;
use PachyBase\Api\Controllers\CrudController;
use PachyBase\Api\Controllers\OpenApiController;
use PachyBase\Api\Controllers\SystemController;
use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Metadata\EntityDefinition;
use PachyBase\Database\Metadata\EntityIntrospector;
use PachyBase\Database\Metadata\FieldDefinition;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Schema\SchemaInspector;
use PachyBase\Http\Request;
use PachyBase\Http\Route;
use PachyBase\Http\Router;
use PachyBase\Modules\Crud\CrudEntity;
use PachyBase\Modules\Crud\CrudEntityRegistry;
use PachyBase\Release\ProjectMetadata;
use RuntimeException;
use stdClass;

final class OpenApiDocumentBuilder
{
    private array $components = [];

    public function __construct(
        private readonly ?CrudEntityRegistry $registry = null,
        private readonly ?EntityIntrospector $introspector = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(?Request $request = null): array
    {
        $this->components = $this->baseComponents();
        $paths = [];
        $tags = [
            [
                'name' => 'System',
                'description' => 'Runtime and health endpoints.',
            ],
            [
                'name' => 'Documentation',
                'description' => 'Machine-readable API metadata.',
            ],
            [
                'name' => 'Authentication',
                'description' => 'Login, refresh, identity, and API token management.',
            ],
        ];

        foreach ($this->registeredRoutes() as $route) {
            if ($this->isCrudRoute($route)) {
                continue;
            }

            $operation = $this->describeStaticRoute($route);

            if ($operation === null) {
                continue;
            }

            $paths[$route->getPath()][strtolower($route->getMethod())] = $operation;
        }

        foreach ($this->resources() as $resource) {
            $entity = $this->metadata($resource);
            $tagName = $this->resourceTag($resource);
            $tags[] = [
                'name' => $tagName,
                'description' => sprintf('Automatic CRUD operations for the "%s" entity.', $resource->slug),
            ];

            $this->registerCrudSchemas($resource, $entity);
            $collectionPath = '/api/' . $resource->slug;
            $itemPath = $collectionPath . '/{id}';
            $paths[$collectionPath]['get'] = $this->crudIndexOperation($resource, $entity, $tagName);
            $paths[$collectionPath]['post'] = $this->crudStoreOperation($resource, $entity, $tagName);
            $paths[$itemPath]['get'] = $this->crudShowOperation($resource, $entity, $tagName);
            $paths[$itemPath]['put'] = $this->crudReplaceOperation($resource, $entity, $tagName);
            $paths[$itemPath]['patch'] = $this->crudPatchOperation($resource, $entity, $tagName);
            $paths[$itemPath]['delete'] = $this->crudDeleteOperation($resource, $entity, $tagName);
        }

        ksort($paths);
        usort(
            $tags,
            static fn(array $left, array $right): int => strcmp((string) $left['name'], (string) $right['name'])
        );

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => Config::get('APP_NAME', 'PachyBase') . ' API',
                'version' => ProjectMetadata::version(),
                'description' => 'Automatically generated from the registered routes, authentication layer, and CRUD entity metadata.',
            ],
            'servers' => [
                ['url' => $this->resolveServerUrl($request)],
            ],
            'tags' => $tags,
            'paths' => $paths,
            'components' => $this->components,
        ];
    }

    /**
     * @return array<int, Route>
     */
    private function registeredRoutes(): array
    {
        $router = new Router();
        $registerRoutes = require dirname(__DIR__, 2) . '/routes/api.php';

        if (!is_callable($registerRoutes)) {
            throw new RuntimeException('The API routes file must return a callable registrar.');
        }

        $registerRoutes($router);

        return $router->routes();
    }

    private function isCrudRoute(Route $route): bool
    {
        $handler = $route->getHandler();

        return is_array($handler)
            && ($handler[0] ?? null) === CrudController::class;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function describeStaticRoute(Route $route): ?array
    {
        $handler = $route->getHandler();

        if (!is_array($handler) || count($handler) !== 2) {
            return null;
        }

        [$class, $method] = $handler;

        return match ([$class, $method]) {
            [SystemController::class, 'status'] => [
                'operationId' => 'systemStatus',
                'summary' => 'Read runtime status',
                'description' => 'Returns the current application status and, in non-production environments, extra request and database diagnostics.',
                'tags' => ['System'],
                'responses' => [
                    '200' => $this->jsonResponse(
                        'Current runtime status.',
                        $this->successEnvelopeSchema(
                            ['$ref' => '#/components/schemas/SystemStatus'],
                            ['resource' => ['type' => 'string', 'example' => 'system.status']]
                        )
                    ),
                ],
            ],
            [OpenApiController::class, 'document'] => [
                'operationId' => 'openApiDocument',
                'summary' => 'Read the OpenAPI document',
                'description' => 'Publishes the generated OpenAPI 3.0.3 document for exploration, SDK generation, and machine integrations.',
                'tags' => ['Documentation'],
                'responses' => [
                    '200' => $this->jsonResponse(
                        'OpenAPI document.',
                        [
                            'type' => 'object',
                            'required' => ['openapi', 'info', 'paths', 'components'],
                            'properties' => [
                                'openapi' => ['type' => 'string', 'example' => '3.0.3'],
                                'info' => ['type' => 'object', 'additionalProperties' => true],
                                'servers' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                                'tags' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                                'paths' => ['type' => 'object', 'additionalProperties' => true],
                                'components' => ['type' => 'object', 'additionalProperties' => true],
                            ],
                        ]
                    ),
                ],
            ],
            [AiController::class, 'schema'] => [
                'operationId' => 'aiSchemaDocument',
                'summary' => 'Read the AI-friendly schema document',
                'description' => 'Publishes a machine-oriented schema view that summarizes entities, fields, writable rules, filters, pagination, operations, and OpenAPI compatibility.',
                'tags' => ['Documentation'],
                'responses' => [
                    '200' => $this->jsonResponse(
                        'AI-friendly schema document.',
                        [
                            'type' => 'object',
                            'required' => ['schema_version', 'generated_at', 'navigation', 'openapi_compatibility', 'entities'],
                            'properties' => [
                                'schema_version' => ['type' => 'string', 'example' => '1.0'],
                                'generated_at' => ['type' => 'string', 'format' => 'date-time'],
                                'generator' => ['type' => 'object', 'additionalProperties' => true],
                                'navigation' => ['type' => 'object', 'additionalProperties' => true],
                                'openapi_compatibility' => ['type' => 'object', 'additionalProperties' => true],
                                'entities' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                            ],
                            'additionalProperties' => true,
                        ]
                    ),
                ],
            ],
            [AiController::class, 'entities'] => [
                'operationId' => 'aiEntitiesDocument',
                'summary' => 'List AI-friendly entity summaries',
                'description' => 'Returns the exposed entities with primary paths, field counts, and the operations available to frontend or agent tooling.',
                'tags' => ['Documentation'],
                'responses' => [
                    '200' => $this->jsonResponse(
                        'AI-friendly entity summaries.',
                        [
                            'type' => 'object',
                            'required' => ['count', 'items'],
                            'properties' => [
                                'count' => ['type' => 'integer'],
                                'items' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                            ],
                            'additionalProperties' => true,
                        ]
                    ),
                ],
            ],
            [AiController::class, 'entity'] => [
                'operationId' => 'aiEntityDocument',
                'summary' => 'Read one AI-friendly entity description',
                'description' => 'Returns the full machine-readable contract for one exposed CRUD entity, including fields, filters, pagination, operations, and OpenAPI references.',
                'tags' => ['Documentation'],
                'parameters' => [
                    [
                        'name' => 'name',
                        'in' => 'path',
                        'required' => true,
                        'description' => 'Exposed CRUD entity slug.',
                        'schema' => ['type' => 'string'],
                    ],
                ],
                'responses' => [
                    '200' => $this->jsonResponse(
                        'AI-friendly entity document.',
                        [
                            'type' => 'object',
                            'required' => ['name', 'fields', 'operations', 'pagination', 'filters', 'openapi'],
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'entity_name' => ['type' => 'string'],
                                'table' => ['type' => 'string'],
                                'database_schema' => ['type' => 'string'],
                                'primary_field' => ['type' => 'string', 'nullable' => true],
                                'paths' => ['type' => 'object', 'additionalProperties' => true],
                                'pagination' => ['type' => 'object', 'additionalProperties' => true],
                                'filters' => ['type' => 'object', 'additionalProperties' => true],
                                'operations' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                                'fields' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                                'openapi' => ['type' => 'object', 'additionalProperties' => true],
                            ],
                            'additionalProperties' => true,
                        ]
                    ),
                    '404' => $this->responseRef('#/components/responses/NotFoundError'),
                ],
            ],
            [AuthController::class, 'login'] => [
                'operationId' => 'authLogin',
                'summary' => 'Authenticate with email and password',
                'description' => 'Issues a JWT access token and a refresh token for an active user account.',
                'tags' => ['Authentication'],
                'requestBody' => $this->requiredJsonRequestBody('#/components/schemas/AuthLoginRequest'),
                'responses' => [
                    '200' => $this->jsonResponse(
                        'Authenticated token pair.',
                        $this->successEnvelopeSchema(
                            ['$ref' => '#/components/schemas/AuthLoginResult'],
                            ['resource' => ['type' => 'string', 'example' => 'auth.login']]
                        )
                    ),
                    '401' => $this->responseRef('#/components/responses/AuthenticationError'),
                    '422' => $this->responseRef('#/components/responses/ValidationError'),
                ],
            ],
            [AuthController::class, 'refresh'] => [
                'operationId' => 'authRefresh',
                'summary' => 'Refresh an access token',
                'description' => 'Rotates the refresh token session and returns a new JWT access token plus refresh token.',
                'tags' => ['Authentication'],
                'requestBody' => $this->requiredJsonRequestBody('#/components/schemas/AuthRefreshRequest'),
                'responses' => [
                    '200' => $this->jsonResponse(
                        'Refreshed token pair.',
                        $this->successEnvelopeSchema(
                            ['$ref' => '#/components/schemas/AuthLoginResult'],
                            ['resource' => ['type' => 'string', 'example' => 'auth.refresh']]
                        )
                    ),
                    '401' => $this->responseRef('#/components/responses/AuthenticationError'),
                    '422' => $this->responseRef('#/components/responses/ValidationError'),
                ],
            ],
            [AuthController::class, 'revoke'] => [
                'operationId' => 'authRevoke',
                'summary' => 'Revoke credentials',
                'description' => 'Revokes either the provided refresh token or the currently authenticated bearer credential. Authentication is optional when a refresh_token is supplied.',
                'tags' => ['Authentication'],
                'security' => [
                    ['bearerAuth' => []],
                    new stdClass(),
                ],
                'requestBody' => $this->optionalJsonRequestBody('#/components/schemas/AuthRevokeRequest'),
                'responses' => [
                    '200' => $this->jsonResponse(
                        'Revocation result.',
                        $this->successEnvelopeSchema(
                            ['$ref' => '#/components/schemas/AuthRevocationResult'],
                            ['resource' => ['type' => 'string', 'example' => 'auth.revoke']]
                        )
                    ),
                    '401' => $this->responseRef('#/components/responses/AuthenticationError'),
                    '422' => $this->responseRef('#/components/responses/ValidationError'),
                ],
            ],
            [AuthController::class, 'me'] => [
                'operationId' => 'authMe',
                'summary' => 'Inspect the current principal',
                'description' => 'Returns the authenticated principal resolved from the bearer token.',
                'tags' => ['Authentication'],
                'security' => [['bearerAuth' => []]],
                'responses' => [
                    '200' => $this->jsonResponse(
                        'Authenticated principal information.',
                        $this->successEnvelopeSchema(
                            ['$ref' => '#/components/schemas/AuthMeResult'],
                            ['resource' => ['type' => 'string', 'example' => 'auth.me']]
                        )
                    ),
                    '401' => $this->responseRef('#/components/responses/AuthenticationError'),
                ],
            ],
            [AuthController::class, 'issueApiToken'] => [
                'operationId' => 'authIssueApiToken',
                'summary' => 'Issue an API token',
                'description' => 'Creates a long-lived API token for the authenticated principal. Required scopes: auth:tokens:create or auth:manage.',
                'tags' => ['Authentication'],
                'security' => [['bearerAuth' => []]],
                'requestBody' => $this->requiredJsonRequestBody('#/components/schemas/AuthIssueApiTokenRequest'),
                'responses' => [
                    '201' => $this->jsonResponse(
                        'Created API token.',
                        $this->successEnvelopeSchema(
                            ['$ref' => '#/components/schemas/AuthIssueApiTokenResult'],
                            ['resource' => ['type' => 'string', 'example' => 'auth.tokens.store']],
                            201
                        )
                    ),
                    '401' => $this->responseRef('#/components/responses/AuthenticationError'),
                    '403' => $this->responseRef('#/components/responses/AuthorizationError'),
                    '422' => $this->responseRef('#/components/responses/ValidationError'),
                ],
                'x-required-scopes' => ['auth:tokens:create', 'auth:manage'],
            ],
            [AuthController::class, 'revokeApiToken'] => [
                'operationId' => 'authRevokeApiToken',
                'summary' => 'Revoke an API token',
                'description' => 'Revokes an existing API token by numeric identifier. Required scopes: auth:tokens:revoke or auth:manage.',
                'tags' => ['Authentication'],
                'security' => [['bearerAuth' => []]],
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'description' => 'API token identifier.',
                        'schema' => ['type' => 'integer'],
                    ],
                ],
                'responses' => [
                    '200' => $this->jsonResponse(
                        'Revoked API token.',
                        $this->successEnvelopeSchema(
                            ['$ref' => '#/components/schemas/AuthRevocationResult'],
                            ['resource' => ['type' => 'string', 'example' => 'auth.tokens.destroy']]
                        )
                    ),
                    '401' => $this->responseRef('#/components/responses/AuthenticationError'),
                    '403' => $this->responseRef('#/components/responses/AuthorizationError'),
                ],
                'x-required-scopes' => ['auth:tokens:revoke', 'auth:manage'],
            ],
            default => null,
        };
    }

    /**
     * @return array<int, CrudEntity>
     */
    private function resources(): array
    {
        return array_values(
            array_filter(
                ($this->registry ?? new CrudEntityRegistry())->all(),
                static fn(CrudEntity $resource): bool => $resource->isExposed()
            )
        );
    }

    private function metadata(CrudEntity $resource): EntityDefinition
    {
        if ($this->introspector instanceof EntityIntrospector) {
            return $this->introspector->inspectTable($resource->table);
        }

        $connection = Connection::getInstance();
        $adapter = AdapterFactory::make($connection, new PdoQueryExecutor($connection->getPDO()));

        return (new EntityIntrospector(new SchemaInspector($adapter)))->inspectTable($resource->table);
    }

    /**
     * @return array<string, mixed>
     */
    private function crudIndexOperation(CrudEntity $resource, EntityDefinition $entity, string $tagName): array
    {
        return [
            'operationId' => 'list' . $this->studly($resource->slug),
            'summary' => sprintf('List %s', $this->humanPlural($resource->slug)),
            'description' => $this->crudIndexDescription($resource),
            'tags' => [$tagName],
            'security' => [['bearerAuth' => []]],
            'parameters' => $this->crudIndexParameters($resource, $entity),
            'responses' => [
                '200' => $this->jsonResponse(
                    sprintf('Paginated list of %s.', $resource->slug),
                    $this->successEnvelopeSchema(
                        [
                            'type' => 'array',
                            'items' => ['$ref' => '#/components/schemas/' . $this->crudItemSchemaName($resource)],
                        ],
                        [
                            'resource' => ['type' => 'string', 'example' => 'crud.index'],
                            'entity' => ['type' => 'string', 'example' => $resource->slug],
                            'pagination' => ['$ref' => '#/components/schemas/PaginationMeta'],
                        ]
                    )
                ),
                '401' => $this->responseRef('#/components/responses/AuthenticationError'),
                '403' => $this->responseRef('#/components/responses/AuthorizationError'),
                '404' => $this->responseRef('#/components/responses/NotFoundError'),
                '422' => $this->responseRef('#/components/responses/ValidationError'),
            ],
            'x-required-scopes' => ['crud:read', sprintf('entity:%s:read', $resource->slug)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function crudShowOperation(CrudEntity $resource, EntityDefinition $entity, string $tagName): array
    {
        return [
            'operationId' => 'show' . $this->studly($resource->slug),
            'summary' => sprintf('Read one %s', $this->humanSingular($resource->slug)),
            'description' => sprintf('Returns a single "%s" record by primary identifier.', $resource->slug),
            'tags' => [$tagName],
            'security' => [['bearerAuth' => []]],
            'parameters' => [$this->crudIdParameter($entity)],
            'responses' => [
                '200' => $this->jsonResponse(
                    sprintf('Single %s record.', $resource->slug),
                    $this->successEnvelopeSchema(
                        ['$ref' => '#/components/schemas/' . $this->crudItemSchemaName($resource)],
                        [
                            'resource' => ['type' => 'string', 'example' => 'crud.show'],
                            'entity' => ['type' => 'string', 'example' => $resource->slug],
                        ]
                    )
                ),
                '401' => $this->responseRef('#/components/responses/AuthenticationError'),
                '403' => $this->responseRef('#/components/responses/AuthorizationError'),
                '404' => $this->responseRef('#/components/responses/NotFoundError'),
            ],
            'x-required-scopes' => ['crud:read', sprintf('entity:%s:read', $resource->slug)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function crudStoreOperation(CrudEntity $resource, EntityDefinition $entity, string $tagName): array
    {
        return [
            'operationId' => 'create' . $this->studly($resource->slug),
            'summary' => sprintf('Create %s', $this->humanSingular($resource->slug)),
            'description' => sprintf('Creates a new "%s" record from the writable entity fields.', $resource->slug),
            'tags' => [$tagName],
            'security' => [['bearerAuth' => []]],
            'requestBody' => $this->requiredJsonRequestBody('#/components/schemas/' . $this->crudCreateSchemaName($resource)),
            'responses' => [
                '201' => $this->jsonResponse(
                    sprintf('Created %s record.', $resource->slug),
                    $this->successEnvelopeSchema(
                        ['$ref' => '#/components/schemas/' . $this->crudItemSchemaName($resource)],
                        [
                            'resource' => ['type' => 'string', 'example' => 'crud.store'],
                            'entity' => ['type' => 'string', 'example' => $resource->slug],
                        ],
                        201
                    )
                ),
                '401' => $this->responseRef('#/components/responses/AuthenticationError'),
                '403' => $this->responseRef('#/components/responses/AuthorizationError'),
                '404' => $this->responseRef('#/components/responses/NotFoundError'),
                '409' => $this->responseRef('#/components/responses/ConflictError'),
                '422' => $this->responseRef('#/components/responses/ValidationError'),
            ],
            'x-required-scopes' => ['crud:create', sprintf('entity:%s:create', $resource->slug)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function crudReplaceOperation(CrudEntity $resource, EntityDefinition $entity, string $tagName): array
    {
        return [
            'operationId' => 'replace' . $this->studly($resource->slug),
            'summary' => sprintf('Replace %s', $this->humanSingular($resource->slug)),
            'description' => sprintf('Replaces the writable state of a "%s" record.', $resource->slug),
            'tags' => [$tagName],
            'security' => [['bearerAuth' => []]],
            'parameters' => [$this->crudIdParameter($entity)],
            'requestBody' => $this->requiredJsonRequestBody('#/components/schemas/' . $this->crudReplaceSchemaName($resource)),
            'responses' => [
                '200' => $this->jsonResponse(
                    sprintf('Replaced %s record.', $resource->slug),
                    $this->successEnvelopeSchema(
                        ['$ref' => '#/components/schemas/' . $this->crudItemSchemaName($resource)],
                        [
                            'resource' => ['type' => 'string', 'example' => 'crud.replace'],
                            'entity' => ['type' => 'string', 'example' => $resource->slug],
                        ]
                    )
                ),
                '401' => $this->responseRef('#/components/responses/AuthenticationError'),
                '403' => $this->responseRef('#/components/responses/AuthorizationError'),
                '404' => $this->responseRef('#/components/responses/NotFoundError'),
                '409' => $this->responseRef('#/components/responses/ConflictError'),
                '422' => $this->responseRef('#/components/responses/ValidationError'),
            ],
            'x-required-scopes' => ['crud:update', sprintf('entity:%s:update', $resource->slug)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function crudPatchOperation(CrudEntity $resource, EntityDefinition $entity, string $tagName): array
    {
        return [
            'operationId' => 'update' . $this->studly($resource->slug),
            'summary' => sprintf('Patch %s', $this->humanSingular($resource->slug)),
            'description' => sprintf('Updates one or more writable fields on a "%s" record.', $resource->slug),
            'tags' => [$tagName],
            'security' => [['bearerAuth' => []]],
            'parameters' => [$this->crudIdParameter($entity)],
            'requestBody' => $this->requiredJsonRequestBody('#/components/schemas/' . $this->crudPatchSchemaName($resource)),
            'responses' => [
                '200' => $this->jsonResponse(
                    sprintf('Updated %s record.', $resource->slug),
                    $this->successEnvelopeSchema(
                        ['$ref' => '#/components/schemas/' . $this->crudItemSchemaName($resource)],
                        [
                            'resource' => ['type' => 'string', 'example' => 'crud.update'],
                            'entity' => ['type' => 'string', 'example' => $resource->slug],
                        ]
                    )
                ),
                '401' => $this->responseRef('#/components/responses/AuthenticationError'),
                '403' => $this->responseRef('#/components/responses/AuthorizationError'),
                '404' => $this->responseRef('#/components/responses/NotFoundError'),
                '409' => $this->responseRef('#/components/responses/ConflictError'),
                '422' => $this->responseRef('#/components/responses/ValidationError'),
            ],
            'x-required-scopes' => ['crud:update', sprintf('entity:%s:update', $resource->slug)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function crudDeleteOperation(CrudEntity $resource, EntityDefinition $entity, string $tagName): array
    {
        $responses = [
            '401' => $this->responseRef('#/components/responses/AuthenticationError'),
            '403' => $this->responseRef('#/components/responses/AuthorizationError'),
            '404' => $this->responseRef('#/components/responses/NotFoundError'),
        ];

        if ($resource->allowsDelete()) {
            $responses['200'] = $this->jsonResponse(
                sprintf('Deleted %s record.', $resource->slug),
                $this->successEnvelopeSchema(
                    ['$ref' => '#/components/schemas/' . $this->crudDeleteSchemaName($resource)],
                    [
                        'resource' => ['type' => 'string', 'example' => 'crud.destroy'],
                        'entity' => ['type' => 'string', 'example' => $resource->slug],
                    ]
                )
            );
        } else {
            $responses['405'] = $this->responseRef('#/components/responses/MethodNotAllowedError');
        }

        ksort($responses);

        return [
            'operationId' => 'delete' . $this->studly($resource->slug),
            'summary' => sprintf('Delete %s', $this->humanSingular($resource->slug)),
            'description' => $resource->allowsDelete()
                ? sprintf('Deletes a "%s" record and returns the removed representation.', $resource->slug)
                : sprintf('Delete requests for "%s" are registered but intentionally disabled and return HTTP 405.', $resource->slug),
            'tags' => [$tagName],
            'security' => [['bearerAuth' => []]],
            'parameters' => [$this->crudIdParameter($entity)],
            'responses' => $responses,
            'x-required-scopes' => ['crud:delete', sprintf('entity:%s:delete', $resource->slug)],
        ];
    }

    private function registerCrudSchemas(CrudEntity $resource, EntityDefinition $entity): void
    {
        $this->components['schemas'][$this->crudItemSchemaName($resource)] = $this->crudItemSchema($resource, $entity);
        $this->components['schemas'][$this->crudCreateSchemaName($resource)] = $this->crudWriteSchema($resource, $entity, 'create');
        $this->components['schemas'][$this->crudReplaceSchemaName($resource)] = $this->crudWriteSchema($resource, $entity, 'replace');
        $this->components['schemas'][$this->crudPatchSchemaName($resource)] = $this->crudWriteSchema($resource, $entity, 'patch');
        $this->components['schemas'][$this->crudDeleteSchemaName($resource)] = [
            'type' => 'object',
            'required' => ['deleted', 'item'],
            'properties' => [
                'deleted' => ['type' => 'boolean', 'example' => true],
                'item' => ['$ref' => '#/components/schemas/' . $this->crudItemSchemaName($resource)],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function crudItemSchema(CrudEntity $resource, EntityDefinition $entity): array
    {
        $properties = [];
        $required = [];

        foreach ($entity->fields as $field) {
            if ($resource->isHidden($field->name)) {
                continue;
            }

            $required[] = $field->name;
            $properties[$field->name] = $this->fieldSchema($field, $resource->rulesFor($field->name), false);

            if ($resource->isReadOnly($field->name, $field->readOnly)) {
                $properties[$field->name]['readOnly'] = true;
            }
        }

        return [
            'type' => 'object',
            'required' => $required,
            'properties' => $properties,
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function crudWriteSchema(CrudEntity $resource, EntityDefinition $entity, string $operation): array
    {
        $properties = [];
        $required = [];

        foreach ($entity->fields as $field) {
            if (!$resource->allowsWriteTo($field->name, $field->readOnly)) {
                continue;
            }

            $properties[$field->name] = $this->fieldSchema($field, $resource->rulesFor($field->name), true);

            if ($operation !== 'patch' && $this->isRequiredForOperation($resource, $field, $operation)) {
                $required[] = $field->name;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => false,
            'minProperties' => 1,
        ];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $rules
     * @return array<string, mixed>
     */
    private function fieldSchema(FieldDefinition $field, array $rules, bool $forRequest): array
    {
        $schema = match ($field->type) {
            'boolean' => ['type' => 'boolean'],
            'integer' => ['type' => 'integer', 'format' => 'int32'],
            'bigint' => ['type' => 'integer', 'format' => 'int64'],
            'decimal', 'float' => ['type' => 'number', 'format' => 'float'],
            'uuid' => ['type' => 'string', 'format' => 'uuid'],
            'date' => ['type' => 'string', 'format' => 'date'],
            'time' => ['type' => 'string', 'format' => 'time'],
            'datetime' => ['type' => 'string', 'format' => 'date-time'],
            'json' => $forRequest
                ? [
                    'type' => 'string',
                    'description' => 'JSON-encoded string.',
                ]
                : [
                    'description' => 'Decoded JSON value returned by the API.',
                    'oneOf' => [
                        ['type' => 'object', 'additionalProperties' => true],
                        ['type' => 'array', 'items' => new stdClass()],
                        ['type' => 'string'],
                        ['type' => 'number'],
                        ['type' => 'boolean'],
                    ],
                ],
            default => ['type' => 'string'],
        };

        if (!$forRequest && $field->readOnly) {
            $schema['readOnly'] = true;
        }

        if ($field->nullable) {
            $schema['nullable'] = true;
        }

        if (isset($rules['enum']) && is_array($rules['enum']) && $rules['enum'] !== []) {
            $schema['enum'] = array_values($rules['enum']);
        }

        if (in_array($field->type, ['string', 'text', 'enum', 'binary', 'uuid'], true)) {
            $maxLength = $this->numericRule($rules['max'] ?? null) ?? $field->length;
            $minLength = $this->numericRule($rules['min'] ?? null);

            if ($minLength !== null) {
                $schema['minLength'] = $minLength;
            }

            if ($maxLength !== null) {
                $schema['maxLength'] = $maxLength;
            }
        }

        if (in_array($field->type, ['integer', 'bigint', 'decimal', 'float'], true)) {
            $minimum = $this->numericRule($rules['min'] ?? null);
            $maximum = $this->numericRule($rules['max'] ?? null);

            if ($minimum !== null) {
                $schema['minimum'] = $minimum;
            }

            if ($maximum !== null) {
                $schema['maximum'] = $maximum;
            }
        }

        if (($rules['email'] ?? false) === true) {
            $schema['format'] = 'email';
        }

        if (($rules['url'] ?? false) === true) {
            $schema['format'] = 'uri';
        }

        if (($rules['uuid'] ?? false) === true) {
            $schema['format'] = 'uuid';
        }

        if ((is_scalar($field->defaultValue) || $field->defaultValue === null) && $field->defaultValue !== null) {
            $schema['default'] = $field->defaultValue;
        }

        return $schema;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function crudIndexParameters(CrudEntity $resource, EntityDefinition $entity): array
    {
        $parameters = [
            [
                'name' => 'page',
                'in' => 'query',
                'required' => false,
                'description' => '1-based page number.',
                'schema' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'default' => 1,
                ],
            ],
            [
                'name' => 'per_page',
                'in' => 'query',
                'required' => false,
                'description' => sprintf('Number of records per page. Maximum allowed value: %d.', $resource->effectiveMaxPerPage()),
                'schema' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => $resource->effectiveMaxPerPage(),
                    'default' => 15,
                ],
            ],
        ];

        if ($resource->searchableFields !== []) {
            $parameters[] = [
                'name' => 'search',
                'in' => 'query',
                'required' => false,
                'description' => 'Case-insensitive text search over: ' . implode(', ', $resource->searchableFields) . '.',
                'schema' => ['type' => 'string'],
            ];
        }

        if ($resource->sortableFields !== []) {
            $parameters[] = [
                'name' => 'sort',
                'in' => 'query',
                'required' => false,
                'description' => 'Comma-separated sortable fields. Prefix a field with "-" for descending order. Allowed values: ' . implode(', ', $resource->sortableFields) . '.',
                'schema' => ['type' => 'string'],
            ];
        }

        if ($resource->filterableFields !== []) {
            $filterProperties = [];

            foreach ($resource->filterableFields as $fieldName) {
                $field = $entity->field($fieldName);

                if ($field instanceof FieldDefinition) {
                    $filterProperties[$fieldName] = $this->fieldSchema($field, $resource->rulesFor($fieldName), true);
                }
            }

            $parameters[] = [
                'name' => 'filter',
                'in' => 'query',
                'required' => false,
                'description' => 'Field/value filters encoded as query parameters like filter[field]=value.',
                'style' => 'deepObject',
                'explode' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => $filterProperties,
                    'additionalProperties' => false,
                ],
            ];
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    private function crudIdParameter(EntityDefinition $entity): array
    {
        $field = $entity->field((string) $entity->primaryField);

        if (!$field instanceof FieldDefinition) {
            throw new RuntimeException(sprintf('Entity "%s" is missing a primary field definition.', $entity->table));
        }

        return [
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'description' => sprintf('Primary identifier for the "%s" record.', $entity->table),
            'schema' => $this->fieldSchema($field, [], true),
        ];
    }

    private function crudIndexDescription(CrudEntity $resource): string
    {
        $lines = [
            sprintf('Lists records from the "%s" entity.', $resource->slug),
            sprintf('Pagination supports page >= 1 and per_page <= %d.', $resource->effectiveMaxPerPage()),
            sprintf('Required scopes: crud:read or entity:%s:read.', $resource->slug),
        ];

        if ($resource->searchableFields !== []) {
            $lines[] = 'Searchable fields: ' . implode(', ', $resource->searchableFields) . '.';
        }

        if ($resource->filterableFields !== []) {
            $lines[] = 'Filterable fields: ' . implode(', ', $resource->filterableFields) . '.';
        }

        if ($resource->sortableFields !== []) {
            $lines[] = 'Sortable fields: ' . implode(', ', $resource->sortableFields) . '.';
        }

        return implode("\n\n", $lines);
    }

    private function isRequiredForOperation(CrudEntity $resource, FieldDefinition $field, string $operation): bool
    {
        $rules = $resource->rulesFor($field->name);

        if ($operation === 'create' && array_key_exists('required_on_create', $rules)) {
            return (bool) $rules['required_on_create'];
        }

        if ($operation === 'replace' && array_key_exists('required_on_replace', $rules)) {
            return (bool) $rules['required_on_replace'];
        }

        if (array_key_exists('required', $rules)) {
            return (bool) $rules['required'];
        }

        return $field->required;
    }

    /**
     * @return array<string, mixed>
     */
    private function requiredJsonRequestBody(string $schemaRef): array
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => $schemaRef],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function optionalJsonRequestBody(string $schemaRef): array
    {
        return [
            'required' => false,
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => $schemaRef],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function jsonResponse(string $description, array $schema): array
    {
        return [
            'description' => $description,
            'content' => [
                'application/json' => [
                    'schema' => $schema,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseRef(string $ref): array
    {
        return ['$ref' => $ref];
    }

    /**
     * @param array<string, mixed> $dataSchema
     * @param array<string, mixed> $metaProperties
     * @return array<string, mixed>
     */
    private function successEnvelopeSchema(array $dataSchema, array $metaProperties = [], int $statusCode = 200): array
    {
        return [
            'type' => 'object',
            'required' => ['success', 'data', 'meta', 'error'],
            'properties' => [
                'success' => ['type' => 'boolean', 'example' => true],
                'data' => $dataSchema,
                'meta' => $this->metaSchema($metaProperties, $statusCode),
                'error' => [
                    'nullable' => true,
                    'allOf' => [
                        ['$ref' => '#/components/schemas/ErrorObject'],
                    ],
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param array<string, mixed> $extraProperties
     * @return array<string, mixed>
     */
    private function metaSchema(array $extraProperties = [], int $statusCode = 200): array
    {
        $required = ['contract_version', 'request_id', 'timestamp', 'path', 'method'];
        $properties = [
            'contract_version' => ['type' => 'string', 'example' => '1.0'],
            'request_id' => ['type' => 'string'],
            'timestamp' => ['type' => 'string', 'format' => 'date-time'],
            'path' => ['type' => 'string'],
            'method' => ['type' => 'string'],
            'status_code' => ['type' => 'integer', 'example' => $statusCode],
        ];

        foreach ($extraProperties as $name => $propertySchema) {
            $properties[$name] = $propertySchema;
            $required[] = $name;
        }

        return [
            'type' => 'object',
            'required' => array_values(array_unique($required)),
            'properties' => $properties,
            'additionalProperties' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseComponents(): array
    {
        return [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                    'description' => 'Accepts PachyBase JWT access tokens and API tokens in the Authorization: Bearer header.',
                ],
            ],
            'schemas' => [
                'PaginationMeta' => [
                    'type' => 'object',
                    'required' => ['total', 'per_page', 'current_page', 'last_page', 'from', 'to'],
                    'properties' => [
                        'total' => ['type' => 'integer'],
                        'per_page' => ['type' => 'integer'],
                        'current_page' => ['type' => 'integer'],
                        'last_page' => ['type' => 'integer'],
                        'from' => ['type' => 'integer', 'nullable' => true],
                        'to' => ['type' => 'integer', 'nullable' => true],
                    ],
                ],
                'ErrorDetail' => [
                    'type' => 'object',
                    'required' => ['field', 'code', 'message'],
                    'properties' => [
                        'field' => ['type' => 'string'],
                        'code' => ['type' => 'string'],
                        'message' => ['type' => 'string'],
                    ],
                    'additionalProperties' => true,
                ],
                'ErrorObject' => [
                    'type' => 'object',
                    'required' => ['code', 'type', 'message', 'details'],
                    'properties' => [
                        'code' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'message' => ['type' => 'string'],
                        'details' => [
                            'type' => 'array',
                            'items' => ['$ref' => '#/components/schemas/ErrorDetail'],
                        ],
                    ],
                    'additionalProperties' => false,
                ],
                'ErrorMeta' => [
                    'type' => 'object',
                    'required' => ['contract_version', 'request_id', 'timestamp', 'path', 'method'],
                    'properties' => [
                        'contract_version' => ['type' => 'string', 'example' => '1.0'],
                        'request_id' => ['type' => 'string'],
                        'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                        'path' => ['type' => 'string'],
                        'method' => ['type' => 'string'],
                    ],
                    'additionalProperties' => true,
                ],
                'ErrorEnvelope' => [
                    'type' => 'object',
                    'required' => ['success', 'data', 'meta', 'error'],
                    'properties' => [
                        'success' => ['type' => 'boolean', 'example' => false],
                        'data' => ['nullable' => true, 'type' => 'object', 'additionalProperties' => true],
                        'meta' => ['$ref' => '#/components/schemas/ErrorMeta'],
                        'error' => ['$ref' => '#/components/schemas/ErrorObject'],
                    ],
                    'additionalProperties' => false,
                ],
                'SystemStatus' => [
                    'type' => 'object',
                    'required' => ['name', 'status', 'version'],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'example' => 'running'],
                        'version' => ['type' => 'string', 'example' => '1.0.0'],
                        'environment' => ['type' => 'string', 'nullable' => true],
                        'database' => [
                            'type' => 'object',
                            'nullable' => true,
                            'properties' => [
                                'driver' => ['type' => 'string', 'nullable' => true],
                                'connected' => ['type' => 'boolean'],
                                'adapter' => ['type' => 'string', 'nullable' => true],
                            ],
                            'additionalProperties' => false,
                        ],
                        'request' => [
                            'type' => 'object',
                            'nullable' => true,
                            'properties' => [
                                'method' => ['type' => 'string'],
                                'path' => ['type' => 'string'],
                            ],
                            'additionalProperties' => false,
                        ],
                    ],
                    'additionalProperties' => false,
                ],
                'AuthenticatedUser' => [
                    'type' => 'object',
                    'required' => ['id', 'name', 'email', 'role', 'scopes'],
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'role' => ['type' => 'string'],
                        'scopes' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                    'additionalProperties' => false,
                ],
                'AuthPrincipal' => [
                    'type' => 'object',
                    'required' => ['provider', 'subject_type', 'subject_id', 'user_id', 'session_id', 'token_id', 'email', 'name', 'role', 'scopes'],
                    'properties' => [
                        'provider' => ['type' => 'string'],
                        'subject_type' => ['type' => 'string'],
                        'subject_id' => [
                            'oneOf' => [
                                ['type' => 'integer'],
                                ['type' => 'string'],
                            ],
                        ],
                        'user_id' => ['type' => 'integer', 'nullable' => true],
                        'session_id' => ['type' => 'integer', 'nullable' => true],
                        'token_id' => ['type' => 'integer', 'nullable' => true],
                        'email' => ['type' => 'string', 'format' => 'email', 'nullable' => true],
                        'name' => ['type' => 'string', 'nullable' => true],
                        'role' => ['type' => 'string', 'nullable' => true],
                        'scopes' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                    'additionalProperties' => false,
                ],
                'AuthLoginRequest' => [
                    'type' => 'object',
                    'required' => ['email', 'password'],
                    'properties' => [
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'password' => ['type' => 'string'],
                    ],
                    'additionalProperties' => false,
                ],
                'AuthRefreshRequest' => [
                    'type' => 'object',
                    'required' => ['refresh_token'],
                    'properties' => [
                        'refresh_token' => ['type' => 'string'],
                    ],
                    'additionalProperties' => false,
                ],
                'AuthRevokeRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'refresh_token' => ['type' => 'string'],
                    ],
                    'additionalProperties' => false,
                ],
                'AuthLoginResult' => [
                    'type' => 'object',
                    'required' => ['token_type', 'access_token', 'refresh_token', 'expires_in', 'refresh_expires_in', 'user'],
                    'properties' => [
                        'token_type' => ['type' => 'string', 'example' => 'Bearer'],
                        'access_token' => ['type' => 'string'],
                        'refresh_token' => ['type' => 'string'],
                        'expires_in' => ['type' => 'integer'],
                        'refresh_expires_in' => ['type' => 'integer'],
                        'user' => ['$ref' => '#/components/schemas/AuthenticatedUser'],
                    ],
                    'additionalProperties' => false,
                ],
                'AuthMeResult' => [
                    'type' => 'object',
                    'required' => ['authenticated', 'principal'],
                    'properties' => [
                        'authenticated' => ['type' => 'boolean', 'example' => true],
                        'principal' => ['$ref' => '#/components/schemas/AuthPrincipal'],
                    ],
                    'additionalProperties' => false,
                ],
                'AuthIssueApiTokenRequest' => [
                    'type' => 'object',
                    'required' => ['name'],
                    'properties' => [
                        'name' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 120],
                        'scopes' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                        'expires_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'AuthIssueApiTokenResult' => [
                    'type' => 'object',
                    'required' => ['token_id', 'name', 'token', 'token_prefix', 'expires_at', 'scopes'],
                    'properties' => [
                        'token_id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'token' => ['type' => 'string'],
                        'token_prefix' => ['type' => 'string'],
                        'expires_at' => ['type' => 'string', 'nullable' => true],
                        'scopes' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                    'additionalProperties' => false,
                ],
                'AuthRevocationResult' => [
                    'type' => 'object',
                    'required' => ['revoked', 'target'],
                    'properties' => [
                        'revoked' => ['type' => 'boolean', 'example' => true],
                        'target' => ['type' => 'string'],
                        'id' => ['type' => 'integer', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'responses' => [
                'ValidationError' => [
                    'description' => 'Validation error response.',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorEnvelope'],
                        ],
                    ],
                ],
                'AuthenticationError' => [
                    'description' => 'Authentication error response.',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorEnvelope'],
                        ],
                    ],
                ],
                'AuthorizationError' => [
                    'description' => 'Authorization error response.',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorEnvelope'],
                        ],
                    ],
                ],
                'NotFoundError' => [
                    'description' => 'Not found error response.',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorEnvelope'],
                        ],
                    ],
                ],
                'MethodNotAllowedError' => [
                    'description' => 'Method not allowed response.',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorEnvelope'],
                        ],
                    ],
                ],
                'ConflictError' => [
                    'description' => 'Conflict error response.',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ErrorEnvelope'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function resolveServerUrl(?Request $request): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? null;

        if (!is_string($host) || trim($host) === '') {
            return '/';
        }

        $https = $_SERVER['HTTPS'] ?? '';
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        $scheme = strtolower((string) $forwardedProto) === 'https' || strtolower((string) $https) === 'on'
            ? 'https'
            : 'http';

        return sprintf('%s://%s', $scheme, $host);
    }

    private function resourceTag(CrudEntity $resource): string
    {
        return $this->humanPlural($resource->slug);
    }

    private function humanPlural(string $slug): string
    {
        $segments = array_map(
            function (string $segment): string {
                $lower = strtolower($segment);

                return match ($lower) {
                    'api' => 'API',
                    default => ucfirst($lower),
                };
            },
            array_filter(explode('-', $slug), static fn(string $segment): bool => $segment !== '')
        );

        return implode(' ', $segments);
    }

    private function humanSingular(string $slug): string
    {
        $plural = $this->humanPlural($slug);

        if (str_ends_with($plural, 'ies')) {
            return substr($plural, 0, -3) . 'y';
        }

        if (str_ends_with($plural, 's')) {
            return substr($plural, 0, -1);
        }

        return $plural;
    }

    private function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    private function crudItemSchemaName(CrudEntity $resource): string
    {
        return 'Crud' . $this->studly($resource->slug) . 'Item';
    }

    private function crudCreateSchemaName(CrudEntity $resource): string
    {
        return 'Crud' . $this->studly($resource->slug) . 'CreateRequest';
    }

    private function crudReplaceSchemaName(CrudEntity $resource): string
    {
        return 'Crud' . $this->studly($resource->slug) . 'ReplaceRequest';
    }

    private function crudPatchSchemaName(CrudEntity $resource): string
    {
        return 'Crud' . $this->studly($resource->slug) . 'PatchRequest';
    }

    private function crudDeleteSchemaName(CrudEntity $resource): string
    {
        return 'Crud' . $this->studly($resource->slug) . 'DeleteResult';
    }

    private function numericRule(mixed $value): int|float|null
    {
        return is_int($value) || is_float($value) ? $value : null;
    }
}
