<?php

declare(strict_types=1);

namespace PachyBase\Database;

use PachyBase\Config;
use PDO;
use PDOException;
use RuntimeException;

/**
 * PDO Connection singleton.
 *
 * Usage:
 *   $pdo = Connection::getInstance()->getPDO();
 *
 * Supported drivers: mysql, pgsql
 */
final class Connection
{
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $driver   = Config::get('DB_DRIVER', 'mysql');
        $host     = Config::get('DB_HOST', '127.0.0.1');
        $port     = Config::get('DB_PORT', '3306');
        $database = Config::get('DB_DATABASE', 'pachybase');
        $username = Config::get('DB_USERNAME', 'root');
        $password = Config::get('DB_PASSWORD', '');

        $dsn = match ($driver) {
            'mysql' => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database),
            'pgsql' => sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $database),
            default => throw new RuntimeException(
                sprintf('Unsupported database driver: "%s". Use mysql or pgsql.', $driver)
            ),
        };

        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed.',
                500,
                $e
            );
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

    /**
     * Reset the singleton (useful in tests).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    // Prevent cloning and unserialization
    private function __clone() {}
}
