<?php

declare(strict_types=1);

namespace Tests\Services\Documentation;

use PachyBase\Config;
use PachyBase\Services\Documentation\BuildDocumentRepository;
use PHPUnit\Framework\TestCase;

class BuildDocumentRepositoryTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-build-docs-' . bin2hex(random_bytes(6));
        mkdir($this->basePath . DIRECTORY_SEPARATOR . 'build', 0777, true);
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

    public function testLoadsStaticOpenApiDocumentOnlyInProduction(): void
    {
        file_put_contents(
            $this->basePath . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'openapi.json',
            json_encode(['openapi' => '3.0.3', 'paths' => ['/health' => ['get' => []]]], JSON_PRETTY_PRINT)
        );

        Config::override(['APP_ENV' => 'development']);
        $repository = new BuildDocumentRepository($this->basePath);

        $this->assertNull($repository->loadOpenApi());

        Config::override(['APP_ENV' => 'production']);

        $this->assertSame('3.0.3', $repository->loadOpenApi()['openapi'] ?? null);
    }

    public function testIgnoresInvalidStaticAiSchemaDocument(): void
    {
        Config::override(['APP_ENV' => 'production']);

        file_put_contents(
            $this->basePath . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'ai-schema.json',
            '{"invalid":true}'
        );

        $repository = new BuildDocumentRepository($this->basePath);

        $this->assertNull($repository->loadAiSchema());
    }
}
