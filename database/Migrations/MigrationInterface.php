<?php

declare(strict_types=1);

namespace PachyBase\Database\Migrations;

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Query\QueryExecutorInterface;

interface MigrationInterface
{
    public function version(): string;

    public function description(): string;

    public function up(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void;

    public function down(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void;
}
