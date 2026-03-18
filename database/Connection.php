<?php

declare(strict_types=1);

namespace PachyBase\Database;

use PachyBase\Config;
use PDO;
use PDOException;
use RuntimeException;

final class Connection
{
    private static ?self $instance = null;
    private PDO $pdo;
    private string $driver;
    private string $database;
    private string $schema;

    private function __construct()
    {
        $driver = strtolower((string) Config::get('DB_DRIVER', 'mysql'));
        $host = Config::get('DB_HOST', '127.0.0.1');
        $port = Config::get('DB_PORT', '3306');
        $database = Config::get('DB_DATABASE', 'pachybase');
        $username = Config::get('DB_USERNAME', 'root');
        $password = Config::get('DB_PASSWORD', '');

        $this->driver = $driver;
        $this->database = (string) $database;
        $this->schema = $driver === 'pgsql'
            ? (string) Config::get('DB_SCHEMA', 'public')
            : $this->database;

        $dsn = match ($driver) {
            'mysql' => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database),
            'pgsql' => sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $database),
            default => throw new RuntimeException(
                sprintf('Unsupported database driver: "%s". Use mysql or pgsql.', $driver)
            ),
        };

        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection failed.', 500, $exception);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getPDO(): PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function database(): string
    {
        return $this->database;
    }

    public function schema(): string
    {
        return $this->schema;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private function __clone()
    {
    }
}
