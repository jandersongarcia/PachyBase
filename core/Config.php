<?php

namespace PachyBase;

use Dotenv\Dotenv;

class Config
{
    private static $config = [];

    public static function load()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();

        self::$config = $_ENV;
    }

    public static function get($key, $default = null)
    {
        return self::$config[$key] ?? $default;
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

        $value = self::get('APP_DEBUG', false);

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
