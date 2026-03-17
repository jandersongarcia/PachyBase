<?php

declare(strict_types=1);

namespace PachyBase\Database\Seeds;

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Query\QueryExecutorInterface;

interface SeederInterface
{
    public function name(): string;

    public function description(): string;

    public function run(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void;
}
