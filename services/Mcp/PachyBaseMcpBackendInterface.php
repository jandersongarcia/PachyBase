<?php

declare(strict_types=1);

namespace PachyBase\Services\Mcp;

interface PachyBaseMcpBackendInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array;

    /**
     * @return array<string, mixed>
     */
    public function listEntities(): array;

    /**
     * @return array<string, mixed>
     */
    public function describeEntity(string $entity): array;

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function listRecords(string $entity, array $query): array;

    /**
     * @return array<string, mixed>
     */
    public function getRecord(string $entity, string $id): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createRecord(string $entity, array $payload): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function replaceRecord(string $entity, string $id, array $payload): array;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateRecord(string $entity, string $id, array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function deleteRecord(string $entity, string $id): array;
}
