<?php

declare(strict_types=1);

namespace Tests\Services\Platform;

use PachyBase\Services\Platform\OperationsOverviewService;
use Tests\Support\DatabaseIntegrationTestCase;

class OperationsOverviewServiceTest extends DatabaseIntegrationTestCase
{
    public function testOverviewAggregatesPlatformCountersAndRecentBackups(): void
    {
        $active = $this->createTenant(name: 'Active Tenant');
        $inactive = $this->createTenant(name: 'Inactive Tenant', isActive: false);

        $this->executor?->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, label, status, file_path, backup_json, size_bytes, created_at) VALUES (:tenant_id, :label, :status, :file_path, :backup_json, :size_bytes, :created_at)',
                $this->table('pb_project_backups')
            ),
            [
                'tenant_id' => $active['id'],
                'label' => 'older-backup',
                'status' => 'ready',
                'file_path' => 'build/backups/' . $active['slug'] . '/older.json',
                'backup_json' => '{}',
                'size_bytes' => 12,
                'created_at' => gmdate('Y-m-d H:i:s', time() - 172800),
            ]
        );
        $this->executor?->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, label, status, file_path, backup_json, size_bytes, created_at) VALUES (:tenant_id, :label, :status, :file_path, :backup_json, :size_bytes, :created_at)',
                $this->table('pb_project_backups')
            ),
            [
                'tenant_id' => $inactive['id'],
                'label' => 'recent-backup',
                'status' => 'ready',
                'file_path' => 'build/backups/' . $inactive['slug'] . '/recent.json',
                'backup_json' => '{}',
                'size_bytes' => 28,
                'created_at' => gmdate('Y-m-d H:i:s', time() - 3600),
            ]
        );
        $this->executor?->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, type, status, payload_json, available_at) VALUES (:tenant_id, :type, :status, :payload_json, :available_at)',
                $this->table('pb_async_jobs')
            ),
            [
                'tenant_id' => $active['id'],
                'type' => 'noop',
                'status' => 'pending',
                'payload_json' => '{}',
                'available_at' => gmdate('Y-m-d H:i:s'),
            ]
        );
        $this->executor?->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, type, status, payload_json, available_at) VALUES (:tenant_id, :type, :status, :payload_json, :available_at)',
                $this->table('pb_async_jobs')
            ),
            [
                'tenant_id' => $inactive['id'],
                'type' => 'noop',
                'status' => 'failed',
                'payload_json' => '{}',
                'available_at' => gmdate('Y-m-d H:i:s'),
            ]
        );
        $this->executor?->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, disk, object_key, original_name, content_type, size_bytes, checksum_sha256, relative_path) VALUES (:tenant_id, :disk, :object_key, :original_name, :content_type, :size_bytes, :checksum_sha256, :relative_path)',
                $this->table('pb_file_objects')
            ),
            [
                'tenant_id' => $active['id'],
                'disk' => 'local',
                'object_key' => 'overview.txt',
                'original_name' => 'overview.txt',
                'content_type' => 'text/plain',
                'size_bytes' => 64,
                'checksum_sha256' => hash('sha256', 'overview'),
                'relative_path' => 'build/storage/projects/' . $active['slug'] . '/overview.txt',
            ]
        );
        $this->executor?->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, category, event, level, outcome, occurred_at) VALUES (:tenant_id, :category, :event, :level, :outcome, :occurred_at)',
                $this->table('pb_audit_logs')
            ),
            [
                'tenant_id' => $active['id'],
                'category' => 'system',
                'event' => 'system.ok',
                'level' => 'info',
                'outcome' => 'success',
                'occurred_at' => gmdate('Y-m-d H:i:s', time() - 1800),
            ]
        );
        $this->executor?->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, category, event, level, outcome, occurred_at) VALUES (:tenant_id, :category, :event, :level, :outcome, :occurred_at)',
                $this->table('pb_audit_logs')
            ),
            [
                'tenant_id' => $inactive['id'],
                'category' => 'system',
                'event' => 'system.error',
                'level' => 'error',
                'outcome' => 'failure',
                'occurred_at' => gmdate('Y-m-d H:i:s', time() - 1200),
            ]
        );

        $overview = (new OperationsOverviewService($this->executor))->overview();

        $this->assertSame(2, $overview['projects']['total']);
        $this->assertSame(1, $overview['projects']['active']);
        $this->assertSame(2, $overview['backups']['total']);
        $this->assertSame(1, $overview['backups']['last_24h']);
        $this->assertSame(1, $overview['jobs']['pending']);
        $this->assertSame(1, $overview['jobs']['failed']);
        $this->assertSame(64, $overview['storage']['total_bytes']);
        $this->assertSame(2, $overview['audit']['last_24h']);
        $this->assertSame(1, $overview['audit']['errors_last_24h']);
        $this->assertSame(['recent-backup', 'older-backup'], array_column($overview['recent_backups'], 'label'));
    }
}
