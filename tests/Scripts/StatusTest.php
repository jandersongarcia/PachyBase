<?php

declare(strict_types=1);

namespace Tests\Scripts;

use PachyBase\Config;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../scripts/status.php';

class StatusTest extends TestCase
{
    private string $basePath = '';

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-status-' . bin2hex(random_bytes(6));
        mkdir($this->basePath . DIRECTORY_SEPARATOR . 'build', 0777, true);
        file_put_contents($this->basePath . DIRECTORY_SEPARATOR . '.env', implode(PHP_EOL, [
            'APP_ENV=development',
            'APP_RUNTIME=local',
            'APP_HOST=127.0.0.1',
            'APP_PORT=8080',
            'APP_URL=http://localhost:8080',
            'APP_KEY=base64:test',
            'AUTH_JWT_SECRET=base64:test',
            'DB_DRIVER=mysql',
            'DB_HOST=127.0.0.1',
            'DB_PORT=3306',
            'DB_DATABASE=pachybase',
            'DB_USERNAME=user',
            'DB_PASSWORD=secret',
        ]) . PHP_EOL);
    }

    protected function tearDown(): void
    {
        Config::reset();

        if (!is_dir($this->basePath)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->basePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
                continue;
            }

            @unlink($file->getPathname());
        }

        @rmdir($this->basePath);
    }

    public function testStatusReportRequiresValidGeneratedDocuments(): void
    {
        Config::override([
            'APP_ENV' => 'development',
            'APP_PREFER_BUILD_DOCS' => 'true',
            'APP_KEY' => 'base64:test',
            'AUTH_JWT_SECRET' => 'base64:test',
            'DB_DRIVER' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'pachybase',
            'DB_USERNAME' => 'user',
            'DB_PASSWORD' => 'secret',
        ]);

        file_put_contents($this->basePath . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'openapi.json', '{"invalid":true}');
        file_put_contents(
            $this->basePath . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'ai-schema.json',
            json_encode(['schema_version' => '1.0', 'entities' => []], JSON_UNESCAPED_SLASHES)
        );

        $report = statusBuildReport($this->basePath);

        $this->assertFalse($report['docs']['openapi']);
        $this->assertTrue($report['docs']['ai']);
    }
}
