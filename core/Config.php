<?php

declare(strict_types=1);

namespace PachyBase;

use PachyBase\Config\AppConfig;

final class Config
{
    public static function load(?string $basePath = null): void
    {
        AppConfig::load($basePath ?? dirname(__DIR__));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return AppConfig::get($key, $default);
    }

    public static function override(array $config): void
    {
        AppConfig::override($config);
    }

    public static function reset(): void
    {
        AppConfig::reset();
    }

    public static function environment(): string
    {
        return AppConfig::environment();
    }

    public static function isProduction(): bool
    {
        return AppConfig::isProduction();
    }

    public static function debugEnabled(): bool
    {
        return AppConfig::debugEnabled();
    }
}
