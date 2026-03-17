<?php

declare(strict_types=1);

namespace PachyBase\Config;

use PachyBase\Config;
use RuntimeException;

final class AuthConfig
{
    public static function jwtSecret(): string
    {
        $secret = trim((string) Config::get('AUTH_JWT_SECRET', Config::get('APP_KEY', '')));

        if ($secret !== '') {
            return $secret;
        }

        if (Config::isProduction()) {
            throw new RuntimeException('AUTH_JWT_SECRET must be configured in production environments.');
        }

        return 'pachybase-local-development-secret';
    }

    public static function issuer(): string
    {
        $issuer = trim((string) Config::get('AUTH_JWT_ISSUER', Config::get('APP_NAME', 'PachyBase')));

        return $issuer !== '' ? $issuer : 'PachyBase';
    }

    public static function accessTokenTtlMinutes(): int
    {
        return max(1, (int) Config::get('AUTH_ACCESS_TTL_MINUTES', 15));
    }

    public static function refreshTokenTtlDays(): int
    {
        return max(1, (int) Config::get('AUTH_REFRESH_TTL_DAYS', 30));
    }

    public static function bootstrapAdminEmail(): string
    {
        return strtolower(trim((string) Config::get('AUTH_BOOTSTRAP_ADMIN_EMAIL', 'admin@pachybase.local')));
    }

    public static function bootstrapAdminPassword(): string
    {
        return (string) Config::get('AUTH_BOOTSTRAP_ADMIN_PASSWORD', 'pachybase123');
    }

    public static function bootstrapAdminName(): string
    {
        return trim((string) Config::get('AUTH_BOOTSTRAP_ADMIN_NAME', 'PachyBase Admin'));
    }
}
