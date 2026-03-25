<?php

declare(strict_types=1);

use PachyBase\Auth\ApiTokenRepository;
use PachyBase\Auth\UserRepository;
use PachyBase\Config;
use PachyBase\Services\Tenancy\TenantRepository;

require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(authTokenCreateMain($argv, $basePath));
}

function authTokenCreateMain(array $argv, string $basePath): int
{
    Config::load($basePath);
    $options = authTokenCreateParseArguments(array_slice($argv, 1));
    $payload = authTokenCreateIssueToken($options);

    if ($options['json']) {
        fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        return 0;
    }

    authTokenCreateWriteHumanReport($payload);

    return 0;
}

/**
 * @param array<int, string> $arguments
 * @return array{
 *     name: string,
 *     scopes: array<int, string>,
 *     expires_at: string|null,
 *     user_email: string|null,
 *     json: bool
 * }
 */
function authTokenCreateParseArguments(array $arguments): array
{
    $name = null;
    $scopes = [];
    $allScopes = false;
    $expiresAt = null;
    $userEmail = null;
    $json = false;

    foreach ($arguments as $argument) {
        if ($argument === '--json') {
            $json = true;
            continue;
        }

        if ($argument === '--all-scopes') {
            $allScopes = true;
            continue;
        }

        if (str_starts_with($argument, '--scope=')) {
            $value = trim(substr($argument, 8));
            if ($value !== '') {
                $scopes[] = $value;
            }
            continue;
        }

        if (str_starts_with($argument, '--scopes=')) {
            foreach (explode(',', substr($argument, 9)) as $scope) {
                $scope = trim($scope);
                if ($scope !== '') {
                    $scopes[] = $scope;
                }
            }
            continue;
        }

        if (str_starts_with($argument, '--expires-at=')) {
            $expiresAt = trim(substr($argument, 13)) ?: null;
            continue;
        }

        if (str_starts_with($argument, '--user-email=')) {
            $userEmail = strtolower(trim(substr($argument, 13))) ?: null;
            continue;
        }

        if (str_starts_with($argument, '--')) {
            throw new RuntimeException(sprintf('Unknown option "%s".', $argument));
        }

        if ($name === null) {
            $name = trim($argument);
            continue;
        }

        throw new RuntimeException(sprintf('Unexpected argument "%s".', $argument));
    }

    if ($name === null || $name === '') {
        throw new RuntimeException('The auth:token:create command requires a token name.');
    }

    $scopes = authTokenCreateNormalizeScopes($allScopes ? ['*'] : $scopes);

    if ($scopes === []) {
        throw new RuntimeException('Provide at least one scope with --scope=... or use --all-scopes.');
    }

    return [
        'name' => $name,
        'scopes' => $scopes,
        'expires_at' => authTokenCreateNormalizeOptionalDateTime($expiresAt),
        'user_email' => $userEmail,
        'json' => $json,
    ];
}

/**
 * @param array{
 *     name: string,
 *     scopes: array<int, string>,
 *     expires_at: string|null,
 *     user_email: string|null,
 *     json: bool
 * } $options
 * @return array<string, mixed>
 */
function authTokenCreateIssueToken(array $options): array
{
    $user = null;
    $tenantId = null;

    if ($options['user_email'] !== null) {
        $user = (new UserRepository())->findActiveByEmail($options['user_email']);

        if ($user === null) {
            throw new RuntimeException(sprintf('Active user not found for email "%s".', $options['user_email']));
        }

        $tenantId = isset($user['tenant_id']) ? (int) $user['tenant_id'] : null;
    }

    $tenantId ??= (int) (new TenantRepository())->defaultTenant()['id'];

    $plainToken = 'pbt_' . bin2hex(random_bytes(24));
    $record = (new ApiTokenRepository())->create(
        $options['name'],
        hash('sha256', $plainToken),
        substr($plainToken, 0, 12),
        $options['scopes'],
        isset($user['id']) ? (int) $user['id'] : null,
        $tenantId,
        isset($user['id']) ? (int) $user['id'] : null,
        $options['expires_at']
    );

    return [
        'token_id' => (int) $record['id'],
        'name' => (string) $record['name'],
        'token' => $plainToken,
        'token_prefix' => (string) ($record['token_prefix'] ?? substr($plainToken, 0, 12)),
        'expires_at' => $record['expires_at'] ?? null,
        'scopes' => $options['scopes'],
        'subject' => [
            'type' => $user === null ? 'integration' : 'user',
            'user_id' => isset($user['id']) ? (int) $user['id'] : null,
            'email' => $user['email'] ?? null,
            'name' => $user['name'] ?? null,
            'role' => $user['role'] ?? null,
        ],
    ];
}

/**
 * @param array<int, string> $scopes
 * @return array<int, string>
 */
function authTokenCreateNormalizeScopes(array $scopes): array
{
    $normalized = [];

    foreach ($scopes as $scope) {
        $scope = trim($scope);

        if ($scope === '' || in_array($scope, $normalized, true)) {
            continue;
        }

        if ($scope === '*') {
            return ['*'];
        }

        $normalized[] = $scope;
    }

    return $normalized;
}

function authTokenCreateNormalizeOptionalDateTime(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($value))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    } catch (Throwable) {
        throw new RuntimeException(sprintf('The --expires-at value "%s" is not a valid datetime.', $value));
    }
}

/**
 * @param array<string, mixed> $payload
 * @param resource|null $stream
 */
function authTokenCreateWriteHumanReport(array $payload, $stream = null): void
{
    $stream ??= STDOUT;

    fwrite($stream, "Integration token created successfully.\n");
    fwrite($stream, sprintf("- token_id: %d\n", (int) $payload['token_id']));
    fwrite($stream, sprintf("- name: %s\n", (string) $payload['name']));
    fwrite($stream, sprintf("- subject: %s\n", (string) ($payload['subject']['type'] ?? 'integration')));

    if (($payload['subject']['email'] ?? null) !== null) {
        fwrite($stream, sprintf("- user_email: %s\n", (string) $payload['subject']['email']));
    }

    fwrite($stream, sprintf("- scopes: %s\n", implode(', ', $payload['scopes'])));
    fwrite($stream, sprintf("- expires_at: %s\n", $payload['expires_at'] === null ? 'never' : (string) $payload['expires_at']));
    fwrite($stream, sprintf("- token_prefix: %s\n", (string) $payload['token_prefix']));
    fwrite($stream, sprintf("- token: %s\n", (string) $payload['token']));
    fwrite($stream, "Store this token now. It will not be shown again.\n");
}
