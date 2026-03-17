<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Release/ProjectMetadata.php';
require_once __DIR__ . '/../utils/BooleanParser.php';

use PachyBase\Release\ProjectMetadata;
use PachyBase\Utils\BooleanParser;

$basePath = dirname(__DIR__);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(doctorMain($argv, $basePath));
}

function doctorMain(array $argv, string $basePath): int
{
    $arguments = array_slice($argv, 1);
    $report = doctorBuildReport(doctorLoadEnvConfig($basePath), $basePath);

    if (in_array('--json', $arguments, true)) {
        fwrite(
            STDOUT,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );

        return $report['status'] === 'fail' ? 1 : 0;
    }

    doctorWriteHumanReport($report);

    return $report['status'] === 'fail' ? 1 : 0;
}

/**
 * @return array<string, string>
 */
function doctorLoadEnvConfig(string $basePath): array
{
    $envPath = $basePath . DIRECTORY_SEPARATOR . '.env';

    if (!is_file($envPath)) {
        return [];
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES);
    $config = [];

    if ($lines === false) {
        return [];
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $delimiter = strpos($trimmed, '=');
        if ($delimiter === false) {
            continue;
        }

        $key = trim(substr($trimmed, 0, $delimiter));
        $value = trim(substr($trimmed, $delimiter + 1));

        if ($key === '') {
            continue;
        }

        $config[$key] = trim($value, "\"'");
    }

    return $config;
}

/**
 * @param array<string, string> $config
 * @return array{
 *     status: string,
 *     version: string,
 *     checks: array<int, array{status: string, code: string, message: string, hint: string|null}>,
 *     summary: array{passed: int, warnings: int, errors: int}
 * }
 */
function doctorBuildReport(array $config, string $basePath): array
{
    $checks = [];
    $envPath = $basePath . DIRECTORY_SEPARATOR . '.env';
    $composePath = $basePath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'docker-compose.yml';
    $dockerfilePath = $basePath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'Dockerfile';
    $appEnv = strtolower(trim((string) ($config['APP_ENV'] ?? 'development')));
    $driver = strtolower(trim((string) ($config['DB_DRIVER'] ?? '')));

    $checks[] = is_file($envPath)
        ? doctorCheck('pass', 'ENV_FILE_PRESENT', '.env is present.', null)
        : doctorCheck('error', 'ENV_FILE_MISSING', '.env is missing.', 'Run "./pachybase env:init" and review the generated values.');

    if (in_array($appEnv, ['development', 'production'], true)) {
        $checks[] = doctorCheck('pass', 'APP_ENV_VALID', sprintf('APP_ENV is set to "%s".', $appEnv), null);
    } else {
        $checks[] = doctorCheck(
            'error',
            'APP_ENV_INVALID',
            sprintf('APP_ENV "%s" is not supported.', $appEnv === '' ? '(empty)' : $appEnv),
            'Use "development" or "production".'
        );
    }

    $debugEnabled = BooleanParser::fromMixed($config['APP_DEBUG'] ?? false);
    if ($appEnv === 'production' && $debugEnabled) {
        $checks[] = doctorCheck(
            'error',
            'APP_DEBUG_ENABLED_IN_PRODUCTION',
            'APP_DEBUG must be disabled in production.',
            'Set APP_DEBUG=false before publishing.'
        );
    } else {
        $checks[] = doctorCheck(
            'pass',
            'APP_DEBUG_REVIEWED',
            sprintf('APP_DEBUG resolves to "%s".', $debugEnabled ? 'true' : 'false'),
            null
        );
    }

    if (in_array($driver, ['mysql', 'pgsql'], true)) {
        $checks[] = doctorCheck('pass', 'DB_DRIVER_SUPPORTED', sprintf('DB_DRIVER "%s" is supported.', $driver), null);
    } else {
        $checks[] = doctorCheck(
            'error',
            'DB_DRIVER_UNSUPPORTED',
            sprintf('DB_DRIVER "%s" is not supported.', $driver === '' ? '(empty)' : $driver),
            'Use "mysql" or "pgsql".'
        );
    }

    foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $field) {
        $value = trim((string) ($config[$field] ?? ''));
        $checks[] = $value !== ''
            ? doctorCheck('pass', $field . '_PRESENT', sprintf('%s is configured.', $field), null)
            : doctorCheck('error', $field . '_MISSING', sprintf('%s is missing.', $field), 'Review .env before publishing.');
    }

    $port = trim((string) ($config['DB_PORT'] ?? ''));
    if ($port !== '' && (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535)) {
        $checks[] = doctorCheck(
            'error',
            'DB_PORT_INVALID',
            sprintf('DB_PORT "%s" is invalid.', $port),
            'Use a numeric TCP port between 1 and 65535.'
        );
    }

    if ($driver === 'pgsql') {
        $schema = trim((string) ($config['DB_SCHEMA'] ?? 'public'));
        $checks[] = doctorCheck(
            'pass',
            'DB_SCHEMA_REVIEWED',
            sprintf('PostgreSQL schema resolves to "%s".', $schema === '' ? 'public' : $schema),
            null
        );
    }

    if ($driver === 'mysql' && trim((string) ($config['DB_SCHEMA'] ?? '')) !== '') {
        $checks[] = doctorCheck(
            'warning',
            'DB_SCHEMA_IGNORED_FOR_MYSQL',
            'DB_SCHEMA is set but ignored when DB_DRIVER=mysql.',
            'Remove DB_SCHEMA to avoid confusion.'
        );
    }

    $jwtSecret = trim((string) ($config['AUTH_JWT_SECRET'] ?? ''));
    if ($appEnv === 'production' && $jwtSecret === '') {
        $checks[] = doctorCheck(
            'error',
            'AUTH_JWT_SECRET_MISSING',
            'AUTH_JWT_SECRET is required in production.',
            'Configure a strong JWT secret before publishing.'
        );
    } elseif ($jwtSecret === '') {
        $checks[] = doctorCheck(
            'warning',
            'AUTH_JWT_SECRET_FALLBACK',
            'AUTH_JWT_SECRET is not set, so development fallback behavior applies.',
            'Set AUTH_JWT_SECRET to make local and production behavior closer.'
        );
    } else {
        $checks[] = doctorCheck('pass', 'AUTH_JWT_SECRET_PRESENT', 'AUTH_JWT_SECRET is configured.', null);
    }

    $defaultAdminEmail = 'admin@pachybase.local';
    $defaultAdminPassword = 'pachybase123';
    $bootstrapEmail = strtolower(trim((string) ($config['AUTH_BOOTSTRAP_ADMIN_EMAIL'] ?? $defaultAdminEmail)));
    $bootstrapPassword = (string) ($config['AUTH_BOOTSTRAP_ADMIN_PASSWORD'] ?? $defaultAdminPassword);

    if ($appEnv === 'production' && $bootstrapEmail === $defaultAdminEmail && $bootstrapPassword === $defaultAdminPassword) {
        $checks[] = doctorCheck(
            'warning',
            'BOOTSTRAP_ADMIN_DEFAULTS',
            'Bootstrap admin credentials still match the development defaults.',
            'Rotate AUTH_BOOTSTRAP_ADMIN_EMAIL and AUTH_BOOTSTRAP_ADMIN_PASSWORD before publishing.'
        );
    } else {
        $checks[] = doctorCheck('pass', 'BOOTSTRAP_ADMIN_REVIEWED', 'Bootstrap admin credentials were reviewed.', null);
    }

    $checks[] = doctorInspectDockerCompose($composePath, $driver);
    $checks[] = doctorInspectDockerfile($dockerfilePath);

    $summary = [
        'passed' => count(array_filter($checks, static fn(array $check): bool => $check['status'] === 'pass')),
        'warnings' => count(array_filter($checks, static fn(array $check): bool => $check['status'] === 'warning')),
        'errors' => count(array_filter($checks, static fn(array $check): bool => $check['status'] === 'error')),
    ];

    return [
        'status' => $summary['errors'] > 0 ? 'fail' : 'pass',
        'version' => ProjectMetadata::version($basePath),
        'checks' => $checks,
        'summary' => $summary,
    ];
}

/**
 * @return array{status: string, code: string, message: string, hint: string|null}
 */
function doctorCheck(string $status, string $code, string $message, ?string $hint): array
{
    return [
        'status' => $status,
        'code' => $code,
        'message' => $message,
        'hint' => $hint,
    ];
}

/**
 * @return array{status: string, code: string, message: string, hint: string|null}
 */
function doctorInspectDockerCompose(string $composePath, string $driver): array
{
    if (!is_file($composePath)) {
        return doctorCheck(
            'warning',
            'DOCKER_COMPOSE_MISSING',
            'docker/docker-compose.yml is not present yet.',
            'Run "./pachybase docker:install" to generate the local stack.'
        );
    }

    $contents = (string) file_get_contents($composePath);
    if (preg_match('/"(?:3306|5432):(?:3306|5432)"/', $contents) === 1) {
        return doctorCheck(
            'warning',
            'DOCKER_DATABASE_PORT_PUBLISHED',
            'docker/docker-compose.yml publishes a database port to the host.',
            'Prefer leaving the database reachable only inside the Compose network.'
        );
    }

    if ($driver === 'mysql' && !str_contains($contents, 'image: mysql:')) {
        return doctorCheck(
            'warning',
            'DOCKER_COMPOSE_DRIVER_MISMATCH',
            'docker/docker-compose.yml does not look aligned with DB_DRIVER=mysql.',
            'Regenerate the Compose file with "./pachybase docker:install".'
        );
    }

    if ($driver === 'pgsql' && !str_contains($contents, 'image: postgres:')) {
        return doctorCheck(
            'warning',
            'DOCKER_COMPOSE_DRIVER_MISMATCH',
            'docker/docker-compose.yml does not look aligned with DB_DRIVER=pgsql.',
            'Regenerate the Compose file with "./pachybase docker:install".'
        );
    }

    return doctorCheck('pass', 'DOCKER_COMPOSE_REVIEWED', 'docker/docker-compose.yml does not expose the database port.', null);
}

/**
 * @return array{status: string, code: string, message: string, hint: string|null}
 */
function doctorInspectDockerfile(string $dockerfilePath): array
{
    if (!is_file($dockerfilePath)) {
        return doctorCheck(
            'error',
            'DOCKERFILE_MISSING',
            'docker/Dockerfile is missing.',
            'Restore the Dockerfile before publishing.'
        );
    }

    $contents = (string) file_get_contents($dockerfilePath);

    if (str_contains($contents, ':latest')) {
        return doctorCheck(
            'warning',
            'DOCKERFILE_USES_LATEST',
            'docker/Dockerfile still references a latest tag.',
            'Pin runtime images to reduce release drift.'
        );
    }

    return doctorCheck('pass', 'DOCKERFILE_REVIEWED', 'docker/Dockerfile uses pinned base images.', null);
}

/**
 * @param array{
 *     status: string,
 *     version: string,
 *     checks: array<int, array{status: string, code: string, message: string, hint: string|null}>,
 *     summary: array{passed: int, warnings: int, errors: int}
 * } $report
 */
function doctorWriteHumanReport(array $report): void
{
    fwrite(STDOUT, sprintf("PachyBase %s release doctor\n", $report['version']));

    foreach ($report['checks'] as $check) {
        $label = match ($check['status']) {
            'pass' => 'PASS',
            'warning' => 'WARN',
            default => 'FAIL',
        };

        fwrite(STDOUT, sprintf("[%s] %s\n", $label, $check['message']));

        if ($check['hint'] !== null) {
            fwrite(STDOUT, sprintf("       %s\n", $check['hint']));
        }
    }

    fwrite(
        STDOUT,
        sprintf(
            "\nSummary: %d passed, %d warning(s), %d error(s)\n",
            $report['summary']['passed'],
            $report['summary']['warnings'],
            $report['summary']['errors']
        )
    );
}
