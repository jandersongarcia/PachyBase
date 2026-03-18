<?php

declare(strict_types=1);

namespace Tests\Database\Adapters;

use PachyBase\Database\Adapters\MySqlAdapter;
use PachyBase\Database\Schema\TypeNormalizer;
use PHPUnit\Framework\TestCase;
use Tests\Database\Fakes\InMemoryQueryExecutor;

class MySqlAdapterTest extends TestCase
{
    public function testReadsSchemaMetadataFromMysqlRows(): void
    {
        $executor = new InMemoryQueryExecutor([
            [['table_name' => 'users', 'table_type' => 'BASE TABLE']],
            [[
                'column_name' => 'id',
                'data_type' => 'int',
                'column_type' => 'int(11)',
                'is_nullable' => 'NO',
                'column_default' => null,
                'extra' => 'auto_increment',
                'character_maximum_length' => null,
                'numeric_precision' => 10,
                'numeric_scale' => 0,
            ], [
                'column_name' => 'email',
                'data_type' => 'varchar',
                'column_type' => 'varchar(255)',
                'is_nullable' => 'YES',
                'column_default' => null,
                'extra' => '',
                'character_maximum_length' => 255,
                'numeric_precision' => null,
                'numeric_scale' => null,
            ]],
            [[
                'constraint_name' => 'PRIMARY',
                'column_name' => 'id',
            ]],
            [[
                'index_name' => 'PRIMARY',
                'column_name' => 'id',
                'non_unique' => 0,
            ], [
                'index_name' => 'users_email_unique',
                'column_name' => 'email',
                'non_unique' => 0,
            ]],
            [[
                'constraint_name' => 'users_role_fk',
                'column_name' => 'role_id',
                'referenced_table_name' => 'roles',
                'referenced_column_name' => 'id',
                'update_rule' => 'CASCADE',
                'delete_rule' => 'RESTRICT',
            ]],
        ]);

        $adapter = new MySqlAdapter($executor, 'pachybase', 'pachybase', new TypeNormalizer());
        $schema = $adapter->inspectTable('users');

        $this->assertSame('mysql', $adapter->driver());
        $this->assertSame('users', $schema->table->name);
        $this->assertSame('integer', $schema->column('id')?->normalizedType);
        $this->assertTrue($schema->column('id')?->autoIncrement ?? false);
        $this->assertSame(['id'], $schema->primaryKey?->columns);
        $this->assertCount(2, $schema->indexes);
        $this->assertSame(['role_id'], $schema->relations[0]->localColumns);
        $this->assertSame('roles', $schema->relations[0]->referencedTable);
    }
}
