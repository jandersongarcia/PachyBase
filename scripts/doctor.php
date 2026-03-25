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
    $appRuntime = strtolower(trim((string) ($config['APP_RUNTIME'] ?? 'docker')));
    $driver = strtolower(trim((string) ($config['DB_DRIVER'] ?? '')));
    $rateLimitEnabled = BooleanParser::fromMixed($config['APP_RATE_LIMIT_ENABLED'] ?? false);
    $auditLogEnabled = BooleanParser::fromMixed($config['APP_AUDIT_LOG_ENABLED'] ?? false);

    $checks[] = is_file($envPath)
        ? doctorCheck('pass', 'ENV_FILE_PRESENT', '.env is present.', null)
        : doctorCheck('error', 'ENV_FILE_MISSING', '.env is missing.', 'Run "./pachybase env:sync" and review the generated values.');

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

    if (in_array($appRuntime, ['docker', 'local'], true)) {
        $checks[] = doctorCheck('pass', 'APP_RUNTIME_VALID', sprintf('APP_RUNTIME is set to "%s".', $appRuntime), null);
    } else {
        $checks[] = doctorCheck(
            'error',
            'APP_RUNTIME_INVALID',
            sprintf('APP_RUNTIME "%s" is not supported.', $appRuntime === '' ? '(empty)' : $appRuntime),
            'Use "docker" or "local".'
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
    $appKey = trim((string) ($config['APP_KEY'] ?? ''));

    if ($appKey === '') {
        $checks[] = doctorCheck(
            $appEnv === 'production' ? 'error' : 'warning',
            'APP_KEY_MISSING',
            'APP_KEY is not configured.',
            'Run "./pachybase app:key" to generate the application key.'
        );
    } else {
        $checks[] = doctorCheck('pass', 'APP_KEY_PRESENT', 'APP_KEY is configured.', null);
    }

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

    $appUrl = trim((string) ($config['APP_URL'] ?? ''));
    if ($appUrl === '') {
        $checks[] = doctorCheck(
            'warning',
            'APP_URL_MISSING',
            'APP_URL is not configured.',
            'Set APP_URL to make CLI access messages explicit.'
        );
    } else {
        $checks[] = doctorCheck('pass', 'APP_URL_PRESENT', sprintf('APP_URL is set to "%s".', $appUrl), null);
    }

    if ($rateLimitEnabled) {
        $checks[] = doctorCheck('pass', 'RATE_LIMIT_ENABLED', 'APP_RATE_LIMIT_ENABLED is active.', null);
    } else {
        $checks[] = doctorCheck(
            $appEnv === 'production' ? 'warning' : 'pass',
            'RATE_LIMIT_DISABLED',
            'APP_RATE_LIMIT_ENABLED is disabled.',
            $appEnv === 'production'
                ? 'Enable request throttling before exposing the API publicly.'
                : null
        );
    }

    if ($rateLimitEnabled) {
        $maxRequests = trim((string) ($config['APP_RATE_LIMIT_MAX_REQUESTS'] ?? '120'));
        $windowSeconds = trim((string) ($config['APP_RATE_LIMIT_WINDOW_SECONDS'] ?? '60'));
        $storagePath = doctorResolveProjectPath($basePath, (string) ($config['APP_RATE_LIMIT_STORAGE_PATH'] ?? 'build/runtime/rate-limit.json'));

        $checks[] = doctorValidatePositiveInteger(
            $maxRequests,
            'APP_RATE_LIMIT_MAX_REQUESTS',
            'Set APP_RATE_LIMIT_MAX_REQUESTS to a positive integer.'
        );
        $checks[] = doctorValidatePositiveInteger(
            $windowSeconds,
            'APP_RATE_LIMIT_WINDOW_SECONDS',
            'Set APP_RATE_LIMIT_WINDOW_SECONDS to a positive integer.'
        );
        $checks[] = doctorCheck(
            'pass',
            'RATE_LIMIT_STORAGE_PATH_REVIEWED',
            sprintf('Rate limit storage resolves to "%s".', $storagePath),
            null
        );
    }

    if ($auditLogEnabled) {
        $checks[] = doctorCheck('pass', 'AUDIT_LOG_ENABLED', 'APP_AUDIT_LOG_ENABLED is active.', null);
    } else {
        $checks[] = doctorCheck(
            $appEnv === 'production' ? 'warning' : 'pass',
            'AUDIT_LOG_DISABLED',
            'APP_AUDIT_LOG_ENABLED is disabled.',
            $appEnv === 'production'
                ? 'Enable audit logging for sensitive auth and write operations before publishing.'
                : null
        );
    }

    if ($auditLogEnabled) {
        $auditPath = doctorResolveProjectPath($basePath, (string) ($config['APP_AUDIT_LOG_PATH'] ?? 'build/logs/audit.jsonl'));
        $checks[] = doctorCheck(
            'pass',
            'AUDIT_LOG_PATH_REVIEWED',
            sprintf('Audit log path resolves to "%s".', $auditPath),
            null
        );
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

    $checks[] = doctorInspectDockerCompose($composePath, $driver, $appRuntime);
    $checks[] = doctorInspectDockerfile($dockerfilePath, $appRuntime);

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
function doctorInspectDockerCompose(string $composePath, string $driver, string $appRuntime): array
{
    if (!is_file($composePath)) {
        return doctorCheck(
            $appRuntime === 'docker' ? 'error' : 'warning',
            'DOCKER_COMPOSE_MISSING',
            'docker/docker-compose.yml is not present yet.',
            'Run "./pachybase docker:sync" to generate the local stack.'
        );
    }

    $contents = (string) file_get_contents($composePath);
    $expectedPort = match ($driver) {
        'pgsql' => '5432',
        default => '3306',
    };

    if (str_contains($contents, sprintf('"%1$s:%1$s"', $expectedPort))) {
        return doctorCheck(
            'pass',
            'DOCKER_DATABASE_PORT_PUBLISHED',
            sprintf('docker/docker-compose.yml publishes the database port %s to the host.', $expectedPort),
            null
        );
    }

    if ($driver === 'mysql' && !str_contains($contents, 'image: mysql:')) {
        return doctorCheck(
            'warning',
            'DOCKER_COMPOSE_DRIVER_MISMATCH',
            'docker/docker-compose.yml does not look aligned with DB_DRIVER=mysql.',
            'Regenerate the Compose file with "./pachybase docker:sync".'
        );
    }

    if ($driver === 'pgsql' && !str_contains($contents, 'image: postgres:')) {
        return doctorCheck(
            'warning',
            'DOCKER_COMPOSE_DRIVER_MISMATCH',
            'docker/docker-compose.yml does not look aligned with DB_DRIVER=pgsql.',
            'Regenerate the Compose file with "./pachybase docker:sync".'
        );
    }

    return doctorCheck(
        'warning',
        'DOCKER_DATABASE_PORT_NOT_PUBLISHED',
        'docker/docker-compose.yml does not publish the database port to the host.',
        'Regenerate the Compose file with "./pachybase docker:sync" to enable external database access.'
    );
}

/**
 * @return array{status: string, code: string, message: string, hint: string|null}
 */
function doctorInspectDockerfile(string $dockerfilePath, string $appRuntime): array
{
    if (!is_file($dockerfilePath)) {
        return doctorCheck(
            $appRuntime === 'docker' ? 'error' : 'warning',
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
 * @return array{status: string, code: string, message: string, hint: string|null}
 */
function doctorValidatePositiveInteger(string $value, string $field, string $hint): array
{
    if ($value !== '' && ctype_digit($value) && (int) $value > 0) {
        return doctorCheck('pass', $field . '_VALID', sprintf('%s is set to "%s".', $field, $value), null);
    }

    return doctorCheck(
        'error',
        $field . '_INVALID',
        sprintf('%s "%s" is invalid.', $field, $value === '' ? '(empty)' : $value),
        $hint
    );
}

function doctorResolveProjectPath(string $basePath, string $path): string
{
    $trimmed = trim($path);

    if ($trimmed === '') {
        return $basePath;
    }

    if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $trimmed) === 1 || str_starts_with($trimmed, '/') || str_starts_with($trimmed, '\\')) {
        return $trimmed;
    }

    return rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $trimmed);
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
