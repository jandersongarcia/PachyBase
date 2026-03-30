<?php

declare(strict_types=1);

namespace PachyBase\Database\Query;

use RuntimeException;
use Throwable;

final class QueryException extends RuntimeException
{
    /**
     * @param array<int|string, mixed> $bindings
     */
    public function __construct(
        string $message,
        private readonly string $sql,
        private readonly array $bindings = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function sql(): string
    {
        return $this->sql;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function bindings(): array
    {
        return $this->bindings;
    }
}
