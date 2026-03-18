<?php

declare(strict_types=1);

namespace PachyBase\Database\Schema;

use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Adapters\DatabaseAdapterInterface;

final class SchemaInspector
{
    public function __construct(
        private readonly ?DatabaseAdapterInterface $adapter = null
    ) {
    }

    public function inspectDatabase(): DatabaseSchema
    {
        return $this->adapter()->inspectDatabase();
    }

    public function inspectTable(string $table): TableSchema
    {
        return $this->adapter()->inspectTable($table);
    }

    public function adapter(): DatabaseAdapterInterface
    {
        return $this->adapter ?? AdapterFactory::make();
    }
}
