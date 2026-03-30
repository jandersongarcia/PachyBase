<?php

declare(strict_types=1);

namespace Tests\Scripts;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../scripts/ai-build.php';

class AiBuildTest extends TestCase
{
    public function testResolveAiBuildOutputPathSupportsRelativeAndAbsolutePaths(): void
    {
        $basePath = 'C:\\app\\PachyBase';
        $resolved = aiBuildResolveOutputPath(
            ['--output=build/custom-ai-schema.json'],
            $basePath . '\\build\\ai-schema.json',
            $basePath
        );

        $this->assertSame(
            $basePath . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'custom-ai-schema.json',
            $resolved
        );
    }

    public function testWriteAiDocumentFileCreatesTargetFileAndReturnsSummary(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-ai-' . bin2hex(random_bytes(6));
        $outputPath = $directory . DIRECTORY_SEPARATOR . 'ai-schema.json';

        $payload = aiBuildWriteDocumentFile([
            'schema_version' => '1.0',
            'generated_at' => '2026-03-17T00:00:00Z',
            'entities' => [
                ['name' => 'system-settings'],
                ['name' => 'api-tokens'],
            ],
        ], $outputPath);

        $this->assertSame($outputPath, $payload['output']);
        $this->assertSame(2, $payload['entities']);
        $this->assertSame('2026-03-17T00:00:00Z', $payload['generated_at']);
        $this->assertFileExists($outputPath);
        $this->assertStringContainsString('"schema_version": "1.0"', (string) file_get_contents($outputPath));
    }
}
