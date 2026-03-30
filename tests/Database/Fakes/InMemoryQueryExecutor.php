<?php

declare(strict_types=1);

namespace Tests\Database\Fakes;

use PachyBase\Database\Query\QueryExecutorInterface;

final class InMemoryQueryExecutor implements QueryExecutorInterface
{
    /**
     * @var array<int, array<int, array<string, mixed>>>
     */
    private array $selectResponses;

    /**
     * @var array<int, array{sql: string, bindings: array<int|string, mixed>}>
     */
    private array $calls = [];

    /**
     * @param array<int, array<int, array<string, mixed>>> $selectResponses
     */
    public function __construct(array $selectResponses = [])
    {
        $this->selectResponses = $selectResponses;
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        $this->calls[] = ['sql' => $sql, 'bindings' => $bindings];

        return array_shift($this->selectResponses) ?? [];
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $rows = $this->select($sql, $bindings);

        return $rows[0] ?? null;
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        $row = $this->selectOne($sql, $bindings);

        return $row === null ? null : reset($row);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function execute(string $sql, array $bindings = []): int
    {
        $this->calls[] = ['sql' => $sql, 'bindings' => $bindings];

        return 1;
    }

    public function transaction(callable $callback): mixed
    {
        return $callback($this);
    }

    /**
     * @return array<int, array{sql: string, bindings: array<int|string, mixed>}>
     */
    public function calls(): array
    {
        return $this->calls;
    }
}
