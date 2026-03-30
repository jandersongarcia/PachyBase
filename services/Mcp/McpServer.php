<?php

declare(strict_types=1);

namespace PachyBase\Services\Mcp;

use PachyBase\Release\ProjectMetadata;
use RuntimeException;
use Throwable;

final class McpServer
{
    /**
     * @var array<int, string>
     */
    private const SUPPORTED_PROTOCOL_VERSIONS = [
        '2025-11-25',
        '2025-06-18',
        '2024-11-05',
    ];

    private bool $initialized = false;

    public function __construct(
        private readonly PachyBaseMcpBackendInterface $backend,
        private readonly string $serverName = 'PachyBase MCP',
        private readonly ?string $serverVersion = null
    ) {
    }

    /**
     * @return array<string, mixed>|array<int, array<string, mixed>>|null
     */
    public function handleMessage(array $message): array|null
    {
        if ($this->isBatch($message)) {
            $responses = [];

            foreach ($message as $entry) {
                if (!is_array($entry)) {
                    $responses[] = $this->errorResponse(null, -32600, 'Invalid Request');
                    continue;
                }

                $response = $this->handleSingleMessage($entry);

                if ($response !== null) {
                    $responses[] = $response;
                }
            }

            return $responses === [] ? null : $responses;
        }

        return $this->handleSingleMessage($message);
    }

    /**
     * @param resource $input
     * @param resource $output
     * @param resource|null $error
     */
    public function run($input = null, $output = null, $error = null): int
    {
        $input ??= STDIN;
        $output ??= STDOUT;
        $error ??= STDERR;

        while (($line = fgets($input)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $message = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                fwrite($error, "Invalid MCP JSON message received.\n");
                $this->write($output, $this->errorResponse(null, -32700, 'Parse error'));
                continue;
            }

            if (!is_array($message)) {
                $this->write($output, $this->errorResponse(null, -32600, 'Invalid Request'));
                continue;
            }

            $response = $this->handleMessage($message);

            if ($response === null) {
                continue;
            }

            $this->write($output, $response);
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>|null
     */
    private function handleSingleMessage(array $message): ?array
    {
        $id = $message['id'] ?? null;
        $method = $message['method'] ?? null;

        if (!is_string($method) || $method === '') {
            return $this->errorResponse($id, -32600, 'Invalid Request');
        }

        return match ($method) {
            'initialize' => $this->initialize($id, $message['params'] ?? []),
            'notifications/initialized' => $this->markInitialized(),
            'ping' => $this->successResponse($id, new \stdClass()),
            'tools/list' => $this->toolsList($id),
            'tools/call' => $this->toolsCall($id, $message['params'] ?? []),
            default => $id === null
                ? null
                : $this->errorResponse($id, -32601, sprintf('Method not found: %s', $method)),
        };
    }

    /**
     * @param array<string, mixed> $params
     */
    private function initialize(mixed $id, mixed $params): array
    {
        $protocolVersion = is_array($params)
            ? (string) ($params['protocolVersion'] ?? self::SUPPORTED_PROTOCOL_VERSIONS[0])
            : self::SUPPORTED_PROTOCOL_VERSIONS[0];

        $this->initialized = true;

        return $this->successResponse($id, [
            'protocolVersion' => $this->negotiatedProtocolVersion($protocolVersion),
            'capabilities' => [
                'tools' => [
                    'listChanged' => false,
                ],
            ],
            'serverInfo' => [
                'name' => $this->serverName,
                'version' => $this->serverVersion ?? ProjectMetadata::version(),
            ],
            'instructions' => 'PachyBase MCP wraps the live PachyBase HTTP API. Configure PACHYBASE_MCP_TOKEN for protected CRUD tools.',
        ]);
    }

    private function markInitialized(): null
    {
        $this->initialized = true;

        return null;
    }

    private function toolsList(mixed $id): array
    {
        return $this->successResponse($id, [
            'tools' => $this->toolDefinitions(),
        ]);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function toolsCall(mixed $id, mixed $params): array
    {
        if (!$this->initialized) {
            return $this->errorResponse($id, -32002, 'Server not initialized');
        }

        if (!is_array($params)) {
            return $this->errorResponse($id, -32602, 'Invalid params');
        }

        $toolName = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];

        if (!is_string($toolName) || $toolName === '') {
            return $this->errorResponse($id, -32602, 'Tool name is required');
        }

        if (!is_array($arguments)) {
            return $this->errorResponse($id, -32602, 'Tool arguments must be an object');
        }

        try {
            $result = $this->callTool($toolName, $arguments);

            return $this->successResponse($id, [
                'content' => [[
                    'type' => 'text',
                    'text' => $this->serializeTextContent($result),
                ]],
                'structuredContent' => $result,
                'isError' => false,
            ]);
        } catch (InvalidMcpArgumentsException $exception) {
            return $this->errorResponse($id, -32602, $exception->getMessage());
        } catch (Throwable $exception) {
            return $this->successResponse($id, [
                'content' => [[
                    'type' => 'text',
                    'text' => $exception->getMessage(),
                ]],
                'isError' => true,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function callTool(string $toolName, array $arguments): array
    {
        return match ($toolName) {
            'pachybase_get_schema' => $this->backend->getSchema(),
            'pachybase_list_entities' => $this->backend->listEntities(),
            'pachybase_describe_entity' => $this->backend->describeEntity($this->requiredString($arguments, 'entity')),
            'pachybase_list_records' => $this->backend->listRecords(
                $this->requiredString($arguments, 'entity'),
                $this->optionalObject($arguments, 'query')
            ),
            'pachybase_get_record' => $this->backend->getRecord(
                $this->requiredString($arguments, 'entity'),
                $this->requiredString($arguments, 'id')
            ),
            'pachybase_create_record' => $this->backend->createRecord(
                $this->requiredString($arguments, 'entity'),
                $this->requiredObject($arguments, 'payload')
            ),
            'pachybase_replace_record' => $this->backend->replaceRecord(
                $this->requiredString($arguments, 'entity'),
                $this->requiredString($arguments, 'id'),
                $this->requiredObject($arguments, 'payload')
            ),
            'pachybase_update_record' => $this->backend->updateRecord(
                $this->requiredString($arguments, 'entity'),
                $this->requiredString($arguments, 'id'),
                $this->requiredObject($arguments, 'payload')
            ),
            'pachybase_delete_record' => $this->backend->deleteRecord(
                $this->requiredString($arguments, 'entity'),
                $this->requiredString($arguments, 'id')
            ),
            default => throw new InvalidMcpArgumentsException(sprintf('Unknown tool: %s', $toolName)),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolDefinitions(): array
    {
        return [
            [
                'name' => 'pachybase_get_schema',
                'title' => 'Get PachyBase Schema',
                'description' => 'Read the AI-friendly PachyBase schema document.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'pachybase_list_entities',
                'title' => 'List PachyBase Entities',
                'description' => 'List the exposed PachyBase entities available to the API.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'pachybase_describe_entity',
                'title' => 'Describe One Entity',
                'description' => 'Read the machine-friendly contract for one exposed entity.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'entity' => [
                            'type' => 'string',
                            'description' => 'Entity slug, such as system-settings.',
                        ],
                    ],
                    'required' => ['entity'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'pachybase_list_records',
                'title' => 'List Entity Records',
                'description' => 'List records from one exposed PachyBase entity using the same query shape as the HTTP API.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'entity' => ['type' => 'string'],
                        'query' => [
                            'type' => 'object',
                            'description' => 'HTTP query parameters such as page, per_page, search, sort, and filter.',
                            'additionalProperties' => true,
                        ],
                    ],
                    'required' => ['entity'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'pachybase_get_record',
                'title' => 'Get One Record',
                'description' => 'Read one record from an exposed entity.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'entity' => ['type' => 'string'],
                        'id' => ['type' => 'string'],
                    ],
                    'required' => ['entity', 'id'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'pachybase_create_record',
                'title' => 'Create One Record',
                'description' => 'Create one record through the PachyBase CRUD API.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'entity' => ['type' => 'string'],
                        'payload' => ['type' => 'object', 'additionalProperties' => true],
                    ],
                    'required' => ['entity', 'payload'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'pachybase_replace_record',
                'title' => 'Replace One Record',
                'description' => 'Replace a record through the PachyBase CRUD API.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'entity' => ['type' => 'string'],
                        'id' => ['type' => 'string'],
                        'payload' => ['type' => 'object', 'additionalProperties' => true],
                    ],
                    'required' => ['entity', 'id', 'payload'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'pachybase_update_record',
                'title' => 'Patch One Record',
                'description' => 'Patch a record through the PachyBase CRUD API.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'entity' => ['type' => 'string'],
                        'id' => ['type' => 'string'],
                        'payload' => ['type' => 'object', 'additionalProperties' => true],
                    ],
                    'required' => ['entity', 'id', 'payload'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'pachybase_delete_record',
                'title' => 'Delete One Record',
                'description' => 'Delete a record through the PachyBase CRUD API.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'entity' => ['type' => 'string'],
                        'id' => ['type' => 'string'],
                    ],
                    'required' => ['entity', 'id'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    private function requiredString(array $arguments, string $field): string
    {
        $value = $arguments[$field] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw new InvalidMcpArgumentsException(sprintf('The "%s" argument is required and must be a string.', $field));
        }

        return trim($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function requiredObject(array $arguments, string $field): array
    {
        $value = $arguments[$field] ?? null;

        if (!is_array($value)) {
            throw new InvalidMcpArgumentsException(sprintf('The "%s" argument is required and must be an object.', $field));
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function optionalObject(array $arguments, string $field): array
    {
        $value = $arguments[$field] ?? [];

        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            throw new InvalidMcpArgumentsException(sprintf('The "%s" argument must be an object when provided.', $field));
        }

        return $value;
    }

    private function negotiatedProtocolVersion(string $requestedVersion): string
    {
        return in_array($requestedVersion, self::SUPPORTED_PROTOCOL_VERSIONS, true)
            ? $requestedVersion
            : self::SUPPORTED_PROTOCOL_VERSIONS[0];
    }

    /**
     * @param array<string, mixed>|array<int, array<string, mixed>> $payload
     */
    private function write(mixed $output, array $payload): void
    {
        if ($this->isBatch($payload)) {
            fwrite($output, json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n");

            return;
        }

        fwrite($output, json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function successResponse(mixed $id, mixed $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResponse(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    /**
     * @param array<mixed> $payload
     */
    private function isBatch(array $payload): bool
    {
        return array_is_list($payload);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function serializeTextContent(array $result): string
    {
        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Failed to encode the MCP tool result.');
        }

        return $json;
    }
}

final class InvalidMcpArgumentsException extends RuntimeException
{
}
