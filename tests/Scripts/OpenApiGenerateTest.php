<?php

declare(strict_types=1);

namespace Tests\Scripts;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../scripts/openapi-generate.php';

class OpenApiGenerateTest extends TestCase
{
    public function testResolveOpenApiOutputPathSupportsRelativeAndAbsolutePaths(): void
    {
        $basePath = 'C:\\app\\PachyBase';
        $resolved = openapiGenerateResolveOutputPath(
            ['--output=docs-site/static/openapi.json'],
            $basePath . '\\build\\openapi.json',
            $basePath
        );

        $this->assertSame(
            $basePath . DIRECTORY_SEPARATOR . 'docs-site' . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'openapi.json',
            $resolved
        );
    }

    public function testWriteOpenApiDocumentFileCreatesTargetFileAndReturnsSummary(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-openapi-' . bin2hex(random_bytes(6));
        $outputPath = $directory . DIRECTORY_SEPARATOR . 'openapi.json';

        $payload = openapiGenerateWriteDocumentFile([
            'openapi' => '3.0.3',
            'paths' => [
                '/status' => ['get' => ['summary' => 'Status']],
            ],
            'components' => [
                'schemas' => [
                    'SystemStatus' => ['type' => 'object'],
                ],
            ],
        ], $outputPath);

        $this->assertSame($outputPath, $payload['output']);
        $this->assertSame(1, $payload['paths']);
        $this->assertSame(1, $payload['schemas']);
        $this->assertFileExists($outputPath);
        $this->assertStringContainsString('"openapi": "3.0.3"', (string) file_get_contents($outputPath));
    }
}
