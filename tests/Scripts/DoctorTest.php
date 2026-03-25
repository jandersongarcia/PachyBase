<?php

declare(strict_types=1);

namespace Tests\Scripts;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../scripts/doctor.php';

class DoctorTest extends TestCase
{
    public function testBuildReportFailsForMissingProductionSecret(): void
    {
        $projectPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-doctor-' . bin2hex(random_bytes(6));
        mkdir($projectPath . DIRECTORY_SEPARATOR . 'docker', 0777, true);
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . '.env', implode(PHP_EOL, [
            'APP_ENV=production',
            'APP_DEBUG=false',
            'DB_DRIVER=mysql',
            'DB_HOST=db',
            'DB_PORT=3306',
            'DB_DATABASE=pachybase',
            'DB_USERNAME=pachybase',
            'DB_PASSWORD=change_this_password',
        ]));
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'docker-compose.yml', "services:\n");
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'Dockerfile', "FROM php:8.2-fpm-bookworm\n");

        $report = doctorBuildReport(doctorLoadEnvConfig($projectPath), $projectPath);

        $this->assertSame('fail', $report['status']);
        $this->assertContains(
            'AUTH_JWT_SECRET_MISSING',
            array_map(static fn(array $check): string => $check['code'], $report['checks'])
        );
    }

    public function testBuildReportPassesWhenDatabasePortIsPublished(): void
    {
        $projectPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-doctor-' . bin2hex(random_bytes(6));
        mkdir($projectPath . DIRECTORY_SEPARATOR . 'docker', 0777, true);
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . '.env', implode(PHP_EOL, [
            'APP_ENV=development',
            'APP_DEBUG=true',
            'DB_DRIVER=pgsql',
            'DB_HOST=db',
            'DB_PORT=5432',
            'DB_DATABASE=pachybase',
            'DB_USERNAME=pachybase',
            'DB_PASSWORD=change_this_password',
            'AUTH_JWT_SECRET=test-secret',
        ]));
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'docker-compose.yml', implode(PHP_EOL, [
            'services:',
            '  db:',
            '    ports:',
            '      - "5432:5432"',
        ]));
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'Dockerfile', "FROM php:8.2-fpm-bookworm\n");

        $report = doctorBuildReport(doctorLoadEnvConfig($projectPath), $projectPath);
        $checks = array_column($report['checks'], 'status', 'code');

        $this->assertSame('pass', $checks['DOCKER_DATABASE_PORT_PUBLISHED'] ?? null);
        $this->assertSame('pass', $checks['DB_SCHEMA_REVIEWED'] ?? null);
    }

    public function testBuildReportWarnsWhenDatabasePortIsNotPublished(): void
    {
        $projectPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-doctor-' . bin2hex(random_bytes(6));
        mkdir($projectPath . DIRECTORY_SEPARATOR . 'docker', 0777, true);
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . '.env', implode(PHP_EOL, [
            'APP_ENV=development',
            'APP_DEBUG=true',
            'DB_DRIVER=mysql',
            'DB_HOST=db',
            'DB_PORT=3306',
            'DB_DATABASE=pachybase',
            'DB_USERNAME=pachybase',
            'DB_PASSWORD=change_this_password',
            'AUTH_JWT_SECRET=test-secret',
        ]));
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'docker-compose.yml', implode(PHP_EOL, [
            'services:',
            '  db:',
            '    image: mysql:8',
        ]));
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'Dockerfile', "FROM php:8.2-fpm-bookworm\n");

        $report = doctorBuildReport(doctorLoadEnvConfig($projectPath), $projectPath);
        $checks = array_column($report['checks'], 'status', 'code');

        $this->assertSame('warning', $checks['DOCKER_DATABASE_PORT_NOT_PUBLISHED'] ?? null);
    }

    public function testBuildReportWarnsWhenComposeDriverDoesNotMatchEnvDriver(): void
    {
        $projectPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-doctor-' . bin2hex(random_bytes(6));
        mkdir($projectPath . DIRECTORY_SEPARATOR . 'docker', 0777, true);
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . '.env', implode(PHP_EOL, [
            'APP_ENV=development',
            'APP_DEBUG=true',
            'DB_DRIVER=pgsql',
            'DB_HOST=db',
            'DB_PORT=5432',
            'DB_DATABASE=pachybase',
            'DB_USERNAME=pachybase',
            'DB_PASSWORD=change_this_password',
            'AUTH_JWT_SECRET=test-secret',
        ]));
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'docker-compose.yml', implode(PHP_EOL, [
            'services:',
            '  db:',
            '    image: mysql:8',
        ]));
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'Dockerfile', "FROM php:8.2-fpm-bookworm\n");

        $report = doctorBuildReport(doctorLoadEnvConfig($projectPath), $projectPath);
        $checks = array_column($report['checks'], 'status', 'code');

        $this->assertSame('warning', $checks['DOCKER_COMPOSE_DRIVER_MISMATCH'] ?? null);
    }

    public function testBuildReportReviewsRateLimitAndAuditConfiguration(): void
    {
        $projectPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-doctor-' . bin2hex(random_bytes(6));
        mkdir($projectPath . DIRECTORY_SEPARATOR . 'docker', 0777, true);
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . '.env', implode(PHP_EOL, [
            'APP_ENV=production',
            'APP_DEBUG=false',
            'DB_DRIVER=mysql',
            'DB_HOST=db',
            'DB_PORT=3306',
            'DB_DATABASE=pachybase',
            'DB_USERNAME=pachybase',
            'DB_PASSWORD=change_this_password',
            'AUTH_JWT_SECRET=test-secret',
            'APP_RATE_LIMIT_ENABLED=true',
            'APP_RATE_LIMIT_MAX_REQUESTS=200',
            'APP_RATE_LIMIT_WINDOW_SECONDS=60',
            'APP_AUDIT_LOG_ENABLED=true',
        ]));
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'docker-compose.yml', "services:\n");
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'Dockerfile', "FROM php:8.2-fpm-bookworm\n");

        $report = doctorBuildReport(doctorLoadEnvConfig($projectPath), $projectPath);
        $checks = array_column($report['checks'], 'status', 'code');

        $this->assertSame('pass', $checks['RATE_LIMIT_ENABLED'] ?? null);
        $this->assertSame('pass', $checks['APP_RATE_LIMIT_MAX_REQUESTS_VALID'] ?? null);
        $this->assertSame('pass', $checks['APP_RATE_LIMIT_WINDOW_SECONDS_VALID'] ?? null);
        $this->assertSame('pass', $checks['AUDIT_LOG_ENABLED'] ?? null);
        $this->assertSame('pass', $checks['AUDIT_LOG_PATH_REVIEWED'] ?? null);
    }

    public function testBuildReportFlagsInvalidRateLimitConfiguration(): void
    {
        $projectPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-doctor-' . bin2hex(random_bytes(6));
        mkdir($projectPath . DIRECTORY_SEPARATOR . 'docker', 0777, true);
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . '.env', implode(PHP_EOL, [
            'APP_ENV=production',
            'APP_DEBUG=false',
            'DB_DRIVER=mysql',
            'DB_HOST=db',
            'DB_PORT=3306',
            'DB_DATABASE=pachybase',
            'DB_USERNAME=pachybase',
            'DB_PASSWORD=change_this_password',
            'AUTH_JWT_SECRET=test-secret',
            'APP_RATE_LIMIT_ENABLED=true',
            'APP_RATE_LIMIT_MAX_REQUESTS=0',
            'APP_RATE_LIMIT_WINDOW_SECONDS=abc',
        ]));
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'docker-compose.yml', "services:\n");
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'Dockerfile', "FROM php:8.2-fpm-bookworm\n");

        $report = doctorBuildReport(doctorLoadEnvConfig($projectPath), $projectPath);
        $checks = array_column($report['checks'], 'status', 'code');

        $this->assertSame('error', $checks['APP_RATE_LIMIT_MAX_REQUESTS_INVALID'] ?? null);
        $this->assertSame('error', $checks['APP_RATE_LIMIT_WINDOW_SECONDS_INVALID'] ?? null);
    }
}
