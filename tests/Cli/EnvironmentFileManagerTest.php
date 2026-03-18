<?php

declare(strict_types=1);

namespace Tests\Cli;

use PachyBase\Cli\EnvironmentFileManager;
use PHPUnit\Framework\TestCase;

class EnvironmentFileManagerTest extends TestCase
{
    public function testSyncFromTemplateCreatesEnvAndPreservesExistingValues(): void
    {
        $projectPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-env-' . bin2hex(random_bytes(6));
        mkdir($projectPath, 0777, true);
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . '.env.example', implode(PHP_EOL, [
            'APP_NAME=PachyBase',
            'APP_ENV=development',
            'DB_DRIVER=mysql',
        ]) . PHP_EOL);
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . '.env', "APP_NAME=Changed\n");

        $manager = new EnvironmentFileManager($projectPath);
        $payload = $manager->syncFromTemplate();

        $this->assertSame('updated', $payload['status']);
        $contents = (string) file_get_contents($projectPath . DIRECTORY_SEPARATOR . '.env');
        $this->assertStringContainsString('APP_NAME=Changed', $contents);
        $this->assertStringContainsString('APP_ENV=development', $contents);
        $this->assertStringContainsString('DB_DRIVER=mysql', $contents);
    }

    public function testValidateReportsMissingFieldsAndWarnings(): void
    {
        $projectPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-env-' . bin2hex(random_bytes(6));
        mkdir($projectPath, 0777, true);
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . '.env', implode(PHP_EOL, [
            'APP_NAME=PachyBase',
            'APP_ENV=development',
            'APP_DEBUG=true',
            'APP_RUNTIME=docker',
        ]) . PHP_EOL);

        $report = (new EnvironmentFileManager($projectPath))->validate();

        $this->assertNotEmpty($report['errors']);
        $this->assertContains('APP_KEY is not configured.', $report['warnings']);
    }
}
