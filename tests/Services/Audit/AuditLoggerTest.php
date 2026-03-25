<?php

declare(strict_types=1);

namespace Tests\Services\Audit;

use PachyBase\Auth\AuthPrincipal;
use PachyBase\Config;
use PachyBase\Http\Request;
use PachyBase\Services\Audit\AuditLogger;
use PachyBase\Services\Observability\RequestMetrics;
use PHPUnit\Framework\TestCase;

class AuditLoggerTest extends TestCase
{
    private string $logPath = '';

    protected function tearDown(): void
    {
        Config::reset();
        RequestMetrics::reset();
        $_SERVER = [];

        if ($this->logPath !== '' && is_file($this->logPath)) {
            unlink($this->logPath);
        }

        $directory = $this->logPath !== '' ? dirname($this->logPath) : '';
        if ($directory !== '' && is_dir($directory)) {
            @rmdir($directory);
        }
    }

    public function testLoggerAppendsStructuredAuditEntries(): void
    {
        $this->logPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-audit-' . bin2hex(random_bytes(4)) . '.jsonl';
        Config::override([
            'APP_AUDIT_LOG_ENABLED' => 'true',
            'APP_AUDIT_LOG_PATH' => $this->logPath,
        ]);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.8';
        $_SERVER['HTTP_X_REQUEST_ID'] = 'req-audit-1';

        $request = new Request('POST', '/api/system-settings', [], ['User-Agent' => 'PHPUnit']);
        $request->setAttribute('auth.principal', new AuthPrincipal(
            provider: 'api_token',
            subjectType: 'token',
            subjectId: 15,
            userId: 7,
            scopes: ['crud:create'],
            tokenId: 15,
            email: 'agent@example.com',
            name: 'Agent',
            role: 'admin'
        ));
        RequestMetrics::start();
        RequestMetrics::recordQuery(6.10);

        (new AuditLogger())->log('crud.record.created', $request, [
            'entity' => 'system-settings',
            'record_id' => 42,
        ], 201);

        $lines = file($this->logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $this->assertIsArray($lines);
        $this->assertCount(1, $lines);

        $entry = json_decode((string) $lines[0], true);

        $this->assertSame('crud', $entry['category']);
        $this->assertSame('crud.record.created', $entry['event']);
        $this->assertSame('info', $entry['level']);
        $this->assertSame('success', $entry['outcome']);
        $this->assertSame('req-audit-1', $entry['request_id']);
        $this->assertSame('/api/system-settings', $entry['path']);
        $this->assertSame(201, $entry['status_code']);
        $this->assertSame('10.0.0.8', $entry['ip']);
        $this->assertSame('system-settings', $entry['context']['entity']);
        $this->assertSame(42, $entry['context']['record_id']);
        $this->assertSame('agent@example.com', $entry['principal']['email']);
        $this->assertSame(6.1, $entry['metrics']['query_time_ms']);
    }

    public function testLoggerDoesNothingWhenAuditIsDisabled(): void
    {
        $this->logPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-audit-' . bin2hex(random_bytes(4)) . '.jsonl';
        Config::override([
            'APP_AUDIT_LOG_ENABLED' => 'false',
            'APP_AUDIT_LOG_PATH' => $this->logPath,
        ]);

        (new AuditLogger())->log('crud.record.created', new Request('POST', '/api/system-settings'));

        $this->assertFileDoesNotExist($this->logPath);
    }
}
