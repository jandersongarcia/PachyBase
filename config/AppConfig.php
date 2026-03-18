<?php

declare(strict_types=1);

namespace PachyBase\Config;

use Dotenv\Dotenv;
use PachyBase\Utils\BooleanParser;

final class AppConfig
{
    private static array $config = [];

    public static function load(string $basePath): void
    {
        $dotenv = Dotenv::createImmutable($basePath);
        $dotenv->load();

        self::$config = $_ENV;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$config[$key] ?? $default;
    }

    public static function override(array $config): void
    {
        self::$config = $config;
    }

    public static function reset(): void
    {
        self::$config = [];
    }

    public static function environment(): string
    {
        $environment = strtolower(trim((string) self::get('APP_ENV', 'production')));

        return $environment === 'development' ? 'development' : 'production';
    }

    public static function isProduction(): bool
    {
        return self::environment() === 'production';
    }

    public static function debugEnabled(): bool
    {
        if (self::isProduction()) {
            return false;
        }

        return BooleanParser::fromMixed(self::get('APP_DEBUG', false));
    }
}
