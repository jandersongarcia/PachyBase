<?php

declare(strict_types=1);

namespace Tests\Database\Adapters;

use PachyBase\Database\Adapters\PostgresAdapter;
use PachyBase\Database\Schema\TypeNormalizer;
use PHPUnit\Framework\TestCase;
use Tests\Database\Fakes\InMemoryQueryExecutor;

class PostgresAdapterTest extends TestCase
{
    public function testReadsSchemaMetadataFromPostgresRows(): void
    {
        $executor = new InMemoryQueryExecutor([
            [['table_name' => 'accounts', 'table_type' => 'BASE TABLE']],
            [[
                'column_name' => 'id',
                'data_type' => 'bigint',
                'udt_name' => 'int8',
                'is_nullable' => 'NO',
                'column_default' => "nextval('accounts_id_seq'::regclass)",
                'character_maximum_length' => null,
                'numeric_precision' => 64,
                'numeric_scale' => 0,
                'is_identity' => 'NO',
            ], [
                'column_name' => 'payload',
                'data_type' => 'jsonb',
                'udt_name' => 'jsonb',
                'is_nullable' => 'YES',
                'column_default' => null,
                'character_maximum_length' => null,
                'numeric_precision' => null,
                'numeric_scale' => null,
                'is_identity' => 'NO',
            ]],
            [[
                'constraint_name' => 'accounts_pkey',
                'column_name' => 'id',
            ]],
            [[
                'index_name' => 'accounts_pkey',
                'is_unique' => 't',
                'is_primary' => 't',
                'column_name' => 'id',
            ], [
                'index_name' => 'accounts_payload_idx',
                'is_unique' => 'f',
                'is_primary' => 'f',
                'column_name' => 'payload',
            ]],
            [[
                'constraint_name' => 'accounts_user_id_fkey',
                'column_name' => 'user_id',
                'referenced_table_name' => 'users',
                'referenced_column_name' => 'id',
                'update_rule' => 'CASCADE',
                'delete_rule' => 'CASCADE',
            ]],
        ]);

        $adapter = new PostgresAdapter($executor, 'pachybase', 'public', new TypeNormalizer());
        $schema = $adapter->inspectTable('accounts');

        $this->assertSame('pgsql', $adapter->driver());
        $this->assertSame('bigint', $schema->column('id')?->normalizedType);
        $this->assertTrue($schema->column('id')?->autoIncrement ?? false);
        $this->assertSame('json', $schema->column('payload')?->normalizedType);
        $this->assertSame(['id'], $schema->primaryKey?->columns);
        $this->assertCount(2, $schema->indexes);
        $this->assertSame('users', $schema->relations[0]->referencedTable);
    }
}
