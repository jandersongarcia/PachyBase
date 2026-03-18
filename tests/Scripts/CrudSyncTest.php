<?php

declare(strict_types=1);

namespace Tests\Scripts;

use PachyBase\Database\Metadata\EntityDefinition;
use PachyBase\Database\Metadata\FieldDefinition;
use PachyBase\Modules\Crud\CrudEntity;
use PachyBase\Modules\Crud\CrudEntityRegistry;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../scripts/crud-sync.php';

class CrudSyncTest extends TestCase
{
    public function testBuildCrudConfigEntriesPreservesConfiguredEntityAndScaffoldsNewOne(): void
    {
        $definitions = [
            new EntityDefinition(
                'system_setting',
                'pb_system_settings',
                'public',
                'id',
                [
                    new FieldDefinition('id', 'id', 'integer', 'bigint', true, false, true, false, null, true),
                    new FieldDefinition('setting_key', 'setting_key', 'string', 'varchar', false, true, false, false, null, false, 120),
                    new FieldDefinition('setting_value', 'setting_value', 'text', 'text', false, false, false, true),
                    new FieldDefinition('created_at', 'created_at', 'datetime', 'timestamp', false, false, true, false),
                ]
            ),
            new EntityDefinition(
                'audit_log',
                'pb_audit_logs',
                'public',
                'id',
                [
                    new FieldDefinition('id', 'id', 'integer', 'bigint', true, false, true, false, null, true),
                    new FieldDefinition('message', 'message', 'string', 'varchar', false, true, false, false, null, false, 255),
                    new FieldDefinition('token_hash', 'token_hash', 'string', 'varchar', false, false, false, false, null, false, 255),
                ]
            ),
        ];

        $registry = new CrudEntityRegistry([
            new CrudEntity(
                slug: 'system-settings',
                table: 'pb_system_settings',
                searchableFields: ['setting_key'],
                filterableFields: ['id', 'setting_key'],
                sortableFields: ['id', 'setting_key'],
                hiddenFields: [],
                defaultSort: ['setting_key'],
                validationRules: ['setting_key' => ['min' => 3]],
                exposed: true,
                allowDelete: false,
                allowedFields: ['setting_key', 'setting_value'],
                maxPerPage: 50,
                readOnlyFields: ['created_at']
            ),
        ]);

        $entries = crudSyncBuildEntries($definitions, $registry);

        $this->assertCount(2, $entries);
        $this->assertSame('system-settings', $entries[1]['slug']);
        $this->assertSame(['setting_key'], $entries[1]['searchable_fields']);
        $this->assertFalse($entries[1]['allow_delete']);

        $this->assertSame('audit-logs', $entries[0]['slug']);
        $this->assertFalse($entries[0]['exposed']);
        $this->assertSame(['token_hash'], $entries[0]['hidden_fields']);
        $this->assertSame(['message', 'token_hash'], $entries[0]['allowed_fields']);
        $this->assertSame(['-id'], $entries[0]['default_sort']);
        $this->assertSame(['message' => ['max' => 255]], $entries[0]['validation_rules']);
    }

    public function testGenerateCrudConfigPhpProducesStableArrayConfig(): void
    {
        $php = crudSyncGeneratePhp([
            [
                'slug' => 'audit-logs',
                'table' => 'pb_audit_logs',
                'exposed' => false,
                'allow_delete' => true,
                'searchable_fields' => ['message'],
                'filterable_fields' => ['id', 'message'],
                'sortable_fields' => ['id', 'message'],
                'allowed_fields' => ['message'],
                'hidden_fields' => [],
                'readonly_fields' => ['id'],
                'default_sort' => ['-id'],
                'max_per_page' => 100,
                'validation_rules' => ['message' => ['max' => 255]],
            ],
        ]);

        $this->assertStringContainsString("'slug' => 'audit-logs'", $php);
        $this->assertStringContainsString("'validation_rules' => [", $php);
        $this->assertStringContainsString("'max' => 255", $php);
    }
}
