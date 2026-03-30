<?php

declare(strict_types=1);

namespace PachyBase\Database\Query;

use PDO;
use PDOException;
use PDOStatement;
use PachyBase\Services\Observability\RequestMetrics;
use Throwable;

final class PdoQueryExecutor implements QueryExecutorInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll() ?: [];
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $result = $this->run($sql, $bindings)->fetch();

        return is_array($result) ? $result : null;
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        $result = $this->selectOne($sql, $bindings);

        if ($result === null) {
            return null;
        }

        return reset($result);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function execute(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);

            // Some drivers, notably MySQL around DDL, may auto-commit and end the transaction.
            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    private function run(string $sql, array $bindings): PDOStatement
    {
        $startedAt = hrtime(true);

        try {
            $statement = $this->pdo->prepare($sql);

            if (!$statement instanceof PDOStatement) {
                throw new QueryException('Failed to prepare database statement.', $sql, $bindings);
            }

            foreach ($bindings as $key => $value) {
                $statement->bindValue(
                    is_int($key) ? $key + 1 : $this->normalizeParameterName((string) $key),
                    $value,
                    $this->parameterType($value)
                );
            }

            $statement->execute();

            return $statement;
        } catch (PDOException $exception) {
            throw new QueryException(
                'Database query failed.',
                $sql,
                $bindings,
                (int) $exception->getCode(),
                $exception
            );
        } finally {
            RequestMetrics::recordQuery((hrtime(true) - $startedAt) / 1_000_000);
        }
    }

    private function normalizeParameterName(string $name): string
    {
        return str_starts_with($name, ':') ? $name : ':' . $name;
    }

    private function parameterType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }
}
