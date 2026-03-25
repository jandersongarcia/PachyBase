<?php

declare(strict_types=1);

namespace Tests\Scripts;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../scripts/docker-install.php';

class DockerInstallTest extends TestCase
{
    public function testBuildDockerComposePublishesDatabasePort(): void
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

        $this->assertStringContainsString('"3306:3306"', $compose);
        $this->assertStringContainsString("context: ..", $compose);
        $this->assertStringContainsString("dockerfile: docker/Dockerfile", $compose);
        $this->assertStringContainsString('image: nginx:1.27-alpine', $compose);
        $this->assertStringContainsString('db_mysql_data:/var/lib/mysql', $compose);
        $this->assertStringContainsString("volumes:\n  db_mysql_data:\n", str_replace("\r\n", "\n", $compose));
        $this->assertStringContainsString("\n    ports:\n", $databaseSection);
    }

    public function testBuildDockerComposeIsDeterministicAndUsesUnixLineEndings(): void
    {
        $config = [
            'DB_DRIVER' => 'pgsql',
            'DB_IMAGE' => 'postgres:15',
            'DB_PORT' => '5432',
            'DB_DATABASE' => 'pachybase',
            'DB_USERNAME' => 'pachybase',
            'DB_PASSWORD' => 'change_this_password',
            'DB_VOLUME_PATH' => '/var/lib/postgresql/data',
        ];

        $first = buildDockerCompose($config);
        $second = buildDockerCompose($config);

        $this->assertSame($first, $second);
        $this->assertStringNotContainsString("\r", $first);
        $this->assertStringEndsWith("\n", $first);
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
        $this->assertStringContainsString('MYSQL_ROOT_PASSWORD: "change_this_password"', $service['environment']);
    }

    public function testBuildDatabaseServiceCreatesPostgresEnvironment(): void
    {
        $service = buildDatabaseService([
            'DB_DRIVER' => 'pgsql',
            'DB_DATABASE' => 'pachybase',
            'DB_USERNAME' => 'pachybase',
            'DB_PASSWORD' => 'change_this_password',
        ]);

        $this->assertStringContainsString('POSTGRES_DB: "pachybase"', $service['environment']);
        $this->assertStringContainsString('POSTGRES_USER: "pachybase"', $service['environment']);
        $this->assertStringContainsString('POSTGRES_PASSWORD: "change_this_password"', $service['environment']);
    }

    public function testBuildDockerComposeUsesDriverSpecificPostgresVolume(): void
    {
        $compose = buildDockerCompose([
            'DB_DRIVER' => 'pgsql',
            'DB_IMAGE' => 'postgres:15',
            'DB_PORT' => '5432',
            'DB_DATABASE' => 'pachybase',
            'DB_USERNAME' => 'pachybase',
            'DB_PASSWORD' => 'change_this_password',
            'DB_VOLUME_PATH' => '/var/lib/postgresql/data',
        ]);

        $this->assertStringContainsString('db_pgsql_data:/var/lib/postgresql/data', $compose);
        $this->assertStringContainsString("volumes:\n  db_pgsql_data:\n", str_replace("\r\n", "\n", $compose));
    }

    public function testWriteDockerComposeFileReportsGeneratedAndSynchronizedStates(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-compose-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);
        $composePath = $directory . DIRECTORY_SEPARATOR . 'docker-compose.yml';
        $compose = "services:\n  php:\n    image: demo\n";

        $first = writeDockerComposeFile($composePath, $compose);
        $second = writeDockerComposeFile($composePath, $compose);

        $this->assertSame('generated', $first['status']);
        $this->assertSame('already synchronized', $second['status']);
        $this->assertSame($compose, (string) file_get_contents($composePath));

        @unlink($composePath);
        @rmdir($directory);
    }
}
