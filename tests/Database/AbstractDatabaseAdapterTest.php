<?php

declare(strict_types=1);

namespace Tests\Database;

use PachyBase\Database\Adapters\AbstractDatabaseAdapter;
use PachyBase\Database\Query\QueryExecutorInterface;
use PachyBase\Database\Schema\ColumnDefinition;
use PachyBase\Database\Schema\IndexDefinition;
use PachyBase\Database\Schema\PrimaryKeyDefinition;
use PachyBase\Database\Schema\RelationDefinition;
use PachyBase\Database\Schema\TableDefinition;
use PachyBase\Database\Schema\TypeNormalizer;
use PHPUnit\Framework\TestCase;

class AbstractDatabaseAdapterTest extends TestCase
{
    public function testInspectDatabaseReadsTableListOnlyOnce(): void
    {
        $adapter = new class () extends AbstractDatabaseAdapter {
            public int $listTablesCalls = 0;

            public function __construct()
            {
                parent::__construct(
                    new class () implements QueryExecutorInterface {
                        public function select(string $sql, array $bindings = []): array
                        {
                            return [];
                        }

                        public function selectOne(string $sql, array $bindings = []): ?array
                        {
                            return null;
                        }

                        public function scalar(string $sql, array $bindings = []): mixed
                        {
                            return null;
                        }

                        public function execute(string $sql, array $bindings = []): int
                        {
                            return 0;
                        }

                        public function transaction(callable $callback): mixed
                        {
                            return $callback($this);
                        }
                    },
                    'pachybase',
                    'public',
                    new TypeNormalizer()
                );
            }

            public function driver(): string
            {
                return 'pgsql';
            }

            public function listTables(): array
            {
                $this->listTablesCalls++;

                return [
                    new TableDefinition('pb_alpha', 'public'),
                    new TableDefinition('pb_beta', 'public'),
                ];
            }

            public function listColumns(string $table): array
            {
                return [
                    new ColumnDefinition('id', 'int8', 'bigint', false, null, true),
                ];
            }

            public function listPrimaryKey(string $table): ?PrimaryKeyDefinition
            {
                return new PrimaryKeyDefinition($table . '_pkey', ['id']);
            }

            public function listIndexes(string $table): array
            {
                return [
                    new IndexDefinition($table . '_pkey', ['id'], true, true),
                ];
            }

            public function listRelations(string $table): array
            {
                return [];
            }

            protected function identifierQuote(): string
            {
                return '"';
            }
        };

        $database = $adapter->inspectDatabase();

        $this->assertCount(2, $database->tables);
        $this->assertSame(1, $adapter->listTablesCalls);
    }
}
