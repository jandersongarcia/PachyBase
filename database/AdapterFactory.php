<?php

declare(strict_types=1);

namespace PachyBase\Database;

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Adapters\MySqlAdapter;
use PachyBase\Database\Adapters\PostgresAdapter;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Query\QueryExecutorInterface;
use PachyBase\Database\Schema\TypeNormalizer;
use RuntimeException;

final class AdapterFactory
{
    public static function make(
        ?Connection $connection = null,
        ?QueryExecutorInterface $queryExecutor = null,
        ?TypeNormalizer $typeNormalizer = null
    ): DatabaseAdapterInterface {
        $connection ??= Connection::getInstance();
        $queryExecutor ??= new PdoQueryExecutor($connection->getPDO());
        $typeNormalizer ??= new TypeNormalizer();

        return match ($connection->driver()) {
            'mysql' => new MySqlAdapter(
                $queryExecutor,
                $connection->database(),
                $connection->schema(),
                $typeNormalizer
            ),
            'pgsql' => new PostgresAdapter(
                $queryExecutor,
                $connection->database(),
                $connection->schema(),
                $typeNormalizer
            ),
            default => throw new RuntimeException(
                sprintf('Unsupported database driver for adapter resolution: "%s".', $connection->driver())
            ),
        };
    }
}
