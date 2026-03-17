<?php

declare(strict_types=1);

namespace PachyBase\Database\Query;

interface QueryExecutorInterface
{
    /**
     * @param array<int|string, mixed> $bindings
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array;

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array;

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function scalar(string $sql, array $bindings = []): mixed;

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function execute(string $sql, array $bindings = []): int;

    public function transaction(callable $callback): mixed;
}
