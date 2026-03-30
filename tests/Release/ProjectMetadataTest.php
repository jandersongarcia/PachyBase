<?php

declare(strict_types=1);

namespace Tests\Release;

use PachyBase\Release\ProjectMetadata;
use PHPUnit\Framework\TestCase;

class ProjectMetadataTest extends TestCase
{
    protected function tearDown(): void
    {
        ProjectMetadata::reset();
    }

    public function testReadsVersionFromVersionFile(): void
    {
        $projectPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-version-' . bin2hex(random_bytes(6));
        mkdir($projectPath, 0777, true);
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'VERSION', "2.4.0-rc.3\n");

        $this->assertSame('2.4.0-rc.3', ProjectMetadata::version($projectPath));
    }

    public function testFallsBackWhenVersionFileIsMissingOrInvalid(): void
    {
        $missingPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-version-missing-' . bin2hex(random_bytes(6));
        mkdir($missingPath, 0777, true);

        $invalidPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-version-invalid-' . bin2hex(random_bytes(6));
        mkdir($invalidPath, 0777, true);
        file_put_contents($invalidPath . DIRECTORY_SEPARATOR . 'VERSION', "main\n");

        $this->assertSame('0.0.0-dev', ProjectMetadata::version($missingPath));

        ProjectMetadata::reset();

        $this->assertSame('0.0.0-dev', ProjectMetadata::version($invalidPath));
    }
}
