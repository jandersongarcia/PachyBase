<?php

declare(strict_types=1);

namespace PachyBase\Auth;

use DateTimeImmutable;
use DateTimeZone;
use PachyBase\Config\AuthConfig;
use PachyBase\Http\AuthenticationException;
use PachyBase\Http\AuthorizationException;
use PachyBase\Http\ValidationException;

final class AuthService
{
    public function __construct(
        private readonly ?UserRepository $users = null,
        private readonly ?ApiTokenRepository $apiTokens = null,
        private readonly ?RefreshTokenRepository $refreshTokens = null,
        private readonly ?JwtCodec $jwtCodec = null,
        private readonly ?AuthorizationService $authorization = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function login(array $payload): array
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $errors = [];

        if ($email === '') {
            $errors[] = [
                'field' => 'email',
                'code' => 'required',
                'message' => 'The email field is required.',
            ];
        }

        if ($password === '') {
            $errors[] = [
                'field' => 'password',
                'code' => 'required',
                'message' => 'The password field is required.',
            ];
        }

        if ($errors !== []) {
            throw new ValidationException(details: $errors);
        }

        $user = $this->userRepository()->findActiveByEmail($email);

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            throw new AuthenticationException('The provided credentials are invalid.', 'INVALID_CREDENTIALS');
        }

        $this->userRepository()->touchLastLogin((int) $user['id']);

        return $this->issueUserTokens($user, $this->decodeScopes($user['scopes'] ?? '[]'));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function refresh(array $payload): array
    {
        $refreshToken = trim((string) ($payload['refresh_token'] ?? ''));

        if ($refreshToken === '') {
            throw new ValidationException(details: [[
                'field' => 'refresh_token',
                'code' => 'required',
                'message' => 'The refresh_token field is required.',
            ]]);
        }

        $refreshTokenHash = hash('sha256', $refreshToken);
        $session = $this->refreshTokenRepository()->findActiveByHash($refreshTokenHash);

        if ($session === null) {
            throw new AuthenticationException('The refresh token is invalid or expired.', 'INVALID_REFRESH_TOKEN');
        }

        $user = $this->userRepository()->findActiveById((int) $session['user_id']);

        if ($user === null) {
            throw new AuthenticationException('The authenticated user is no longer available.', 'INVALID_REFRESH_TOKEN');
        }

        $this->refreshTokenRepository()->touchLastUsed((int) $session['id']);
        $this->refreshTokenRepository()->revokeById((int) $session['id']);

        return $this->issueUserTokens($user, $this->decodeScopes($session['scopes'] ?? '[]'));
    }

    public function authenticateBearerToken(string $token): AuthPrincipal
    {
        return substr_count($token, '.') === 2
            ? $this->authenticateJwt($token)
            : $this->authenticateApiToken($token);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function issueApiToken(AuthPrincipal $principal, array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $requestedScopes = $this->normalizeScopes($payload['scopes'] ?? []);
        $expiresAt = $this->normalizeOptionalDateTime($payload['expires_at'] ?? null, 'expires_at');

        if ($name === '') {
            throw new ValidationException(details: [[
                'field' => 'name',
                'code' => 'required',
                'message' => 'The name field is required.',
            ]]);
        }

        if (strlen($name) < 3 || strlen($name) > 120) {
            throw new ValidationException(details: [[
                'field' => 'name',
                'code' => 'length',
                'message' => 'The name must contain between 3 and 120 characters.',
            ]]);
        }

        $scopes = $requestedScopes !== [] ? $requestedScopes : $principal->scopes;
        $this->authorization()->ensureGrantable($principal, $scopes);

        $plainToken = 'pbt_' . bin2hex(random_bytes(24));
        $record = $this->apiTokenRepository()->create(
            $name,
            hash('sha256', $plainToken),
            substr($plainToken, 0, 12),
            $scopes,
            $principal->userId,
            $expiresAt
        );

        return [
            'token_id' => (int) $record['id'],
            'name' => (string) $record['name'],
            'token' => $plainToken,
            'token_prefix' => (string) $record['token_prefix'],
            'expires_at' => $record['expires_at'],
            'scopes' => $scopes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function revokeCurrent(AuthPrincipal $principal): array
    {
        if ($principal->provider === 'jwt' && $principal->sessionId !== null) {
            $this->refreshTokenRepository()->revokeById($principal->sessionId);

            return [
                'revoked' => true,
                'target' => 'session',
                'id' => $principal->sessionId,
            ];
        }

        if ($principal->provider === 'api_token' && $principal->tokenId !== null) {
            $this->apiTokenRepository()->revokeById($principal->tokenId);

            return [
                'revoked' => true,
                'target' => 'api_token',
                'id' => $principal->tokenId,
            ];
        }

        throw new AuthenticationException('The authenticated credential cannot be revoked.', 'REVOCATION_NOT_SUPPORTED');
    }

    /**
     * @return array<string, mixed>
     */
    public function revokeRefreshToken(string $refreshToken): array
    {
        $revoked = $this->refreshTokenRepository()->revokeByHash(hash('sha256', $refreshToken));

        if (!$revoked) {
            throw new AuthenticationException('The refresh token is invalid or expired.', 'INVALID_REFRESH_TOKEN');
        }

        return [
            'revoked' => true,
            'target' => 'refresh_token',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function revokeApiToken(AuthPrincipal $principal, int $tokenId): array
    {
        $record = $this->apiTokenRepository()->findById($tokenId);

        if ($record === null) {
            throw new AuthenticationException('The API token could not be found.', 'TOKEN_NOT_FOUND');
        }

        if (
            $principal->userId !== null
            && isset($record['user_id'])
            && $record['user_id'] !== null
            && (int) $record['user_id'] !== $principal->userId
            && !$this->authorization()->hasAnyScope($principal, ['auth:manage'])
        ) {
            throw new AuthorizationException(
                'You do not have permission to revoke this API token.',
                'INSUFFICIENT_PERMISSIONS'
            );
        }

        $this->apiTokenRepository()->revokeById($tokenId);

        return [
            'revoked' => true,
            'target' => 'api_token',
            'id' => $tokenId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function issueUserTokens(array $user, array $scopes): array
    {
        $now = time();
        $refreshToken = 'pbr_' . bin2hex(random_bytes(32));
        $refreshExpiresAt = gmdate(
            'Y-m-d H:i:s',
            $now + (AuthConfig::refreshTokenTtlDays() * 86400)
        );
        $session = $this->refreshTokenRepository()->create(
            (int) $user['id'],
            hash('sha256', $refreshToken),
            $scopes,
            $refreshExpiresAt
        );
        $accessExpiresAt = $now + (AuthConfig::accessTokenTtlMinutes() * 60);
        $accessToken = $this->jwtCodec()->encode([
            'iss' => AuthConfig::issuer(),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $accessExpiresAt,
            'typ' => 'access',
            'sub' => (string) $user['id'],
            'uid' => (int) $user['id'],
            'sid' => (int) $session['id'],
            'provider' => 'jwt',
            'email' => (string) $user['email'],
            'name' => (string) $user['name'],
            'role' => (string) ($user['role'] ?? 'user'),
            'scopes' => $scopes,
        ]);

        return [
            'token_type' => 'Bearer',
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => AuthConfig::accessTokenTtlMinutes() * 60,
            'refresh_expires_in' => AuthConfig::refreshTokenTtlDays() * 86400,
            'user' => $this->publicUser($user, $scopes),
        ];
    }

    private function authenticateJwt(string $token): AuthPrincipal
    {
        $claims = $this->jwtCodec()->decode($token);

        if (($claims['typ'] ?? null) !== 'access' || !isset($claims['uid'])) {
            throw new AuthenticationException('The JWT token payload is invalid.', 'INVALID_TOKEN');
        }

        $user = $this->userRepository()->findActiveById((int) $claims['uid']);

        if ($user === null) {
            throw new AuthenticationException('The authenticated user is no longer available.', 'INVALID_TOKEN');
        }

        return new AuthPrincipal(
            'jwt',
            'user',
            (int) $claims['uid'],
            (int) $claims['uid'],
            $this->normalizeScopes($claims['scopes'] ?? []),
            isset($claims['sid']) ? (int) $claims['sid'] : null,
            null,
            (string) $user['email'],
            (string) $user['name'],
            (string) ($user['role'] ?? 'user')
        );
    }

    private function authenticateApiToken(string $token): AuthPrincipal
    {
        $record = $this->apiTokenRepository()->findActiveByHash(hash('sha256', $token));

        if ($record === null) {
            throw new AuthenticationException('The API token is invalid or expired.', 'INVALID_TOKEN');
        }

        $this->apiTokenRepository()->touchLastUsed((int) $record['id']);
        $userId = isset($record['user_id']) && $record['user_id'] !== null ? (int) $record['user_id'] : null;
        $user = $userId !== null ? $this->userRepository()->findActiveById($userId) : null;

        return new AuthPrincipal(
            'api_token',
            'api_token',
            (int) $record['id'],
            $userId,
            $this->decodeScopes($record['scopes'] ?? '[]'),
            null,
            (int) $record['id'],
            $user['email'] ?? null,
            $user['name'] ?? null,
            $user['role'] ?? null
        );
    }

    /**
     * @return array<int, string>
     */
    private function normalizeScopes(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            return $this->decodeScopes($value);
        }

        if (!is_array($value)) {
            throw new ValidationException(details: [[
                'field' => 'scopes',
                'code' => 'invalid_type',
                'message' => 'The scopes field must be an array of strings.',
            ]]);
        }

        $scopes = [];

        foreach ($value as $scope) {
            $scope = trim((string) $scope);

            if ($scope === '') {
                continue;
            }

            $scopes[] = $scope;
        }

        return array_values(array_unique($scopes));
    }

    /**
     * @return array<int, string>
     */
    private function decodeScopes(mixed $rawScopes): array
    {
        if (is_array($rawScopes)) {
            return $this->normalizeScopes($rawScopes);
        }

        $decoded = json_decode((string) $rawScopes, true);

        return is_array($decoded) ? $this->normalizeScopes($decoded) : [];
    }

    private function normalizeOptionalDateTime(mixed $value, string $field): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable((string) $value))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            throw new ValidationException(details: [[
                'field' => $field,
                'code' => 'datetime',
                'message' => sprintf('The %s field must be a valid datetime string.', $field),
            ]]);
        }
    }

    /**
     * @param array<string, mixed> $user
     * @param array<int, string> $scopes
     * @return array<string, mixed>
     */
    private function publicUser(array $user, array $scopes): array
    {
        return [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => (string) ($user['role'] ?? 'user'),
            'scopes' => $scopes,
        ];
    }

    private function userRepository(): UserRepository
    {
        return $this->users ?? new UserRepository();
    }

    private function apiTokenRepository(): ApiTokenRepository
    {
        return $this->apiTokens ?? new ApiTokenRepository();
    }

    private function refreshTokenRepository(): RefreshTokenRepository
    {
        return $this->refreshTokens ?? new RefreshTokenRepository();
    }

    private function jwtCodec(): JwtCodec
    {
        return $this->jwtCodec ?? new JwtCodec();
    }

    private function authorization(): AuthorizationService
    {
        return $this->authorization ?? new AuthorizationService();
    }
}
