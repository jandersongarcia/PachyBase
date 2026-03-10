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
}