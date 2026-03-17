<?php

declare(strict_types=1);

namespace Tests\Scripts;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../scripts/docker-install.php';

class DockerInstallTest extends TestCase
{
    public function testBuildDockerComposeDoesNotPublishDatabasePort(): void
    {
        $compose = buildDockerCompose([
            'DB_DRIVER' => 'mysql',
            'DB_IMAGE' => 'mysql:8',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'pachybase',
            'DB_USERNAME' => 'pachybase',
            'DB_PASSWORD' => 'change_this_password',
            'DB_VOLUME_PATH' => '/var/lib/mysql',
        ]);

        $databaseSection = explode("  db:\n", str_replace("\r\n", "\n", $compose), 2)[1] ?? '';

        $this->assertStringNotContainsString('"3306:3306"', $compose);
        $this->assertStringNotContainsString("\n    ports:\n", $databaseSection);
    }

    public function testBuildDatabaseServiceCreatesDedicatedMysqlUserWhenNotRoot(): void
    {
        $service = buildDatabaseService([
            'DB_DRIVER' => 'mysql',
            'DB_DATABASE' => 'pachybase',
            'DB_USERNAME' => 'pachybase',
            'DB_PASSWORD' => 'change_this_password',
        ]);

        $this->assertStringContainsString('MYSQL_USER: "pachybase"', $service['environment']);
        $this->assertStringContainsString('MYSQL_PASSWORD: "change_this_password"', $service['environment']);
    }
}
