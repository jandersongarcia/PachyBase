<?php

declare(strict_types=1);

namespace PachyBase\Auth;

use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Query\QueryExecutorInterface;

final class UserRepository
{
    private readonly QueryExecutorInterface $queryExecutor;
    private readonly string $table;

    public function __construct(?QueryExecutorInterface $queryExecutor = null)
    {
        $connection = Connection::getInstance();
        $adapter = AdapterFactory::make($connection);

        $this->queryExecutor = $queryExecutor ?? new PdoQueryExecutor($connection->getPDO());
        $this->table = $adapter->quoteIdentifier('pb_users');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByEmail(string $email): ?array
    {
        return $this->queryExecutor->selectOne(
            sprintf('SELECT * FROM %s WHERE email = :email AND is_active = :is_active LIMIT 1', $this->table),
            [
                'email' => strtolower(trim($email)),
                'is_active' => true,
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveById(int $id): ?array
    {
        return $this->queryExecutor->selectOne(
            sprintf('SELECT * FROM %s WHERE id = :id AND is_active = :is_active LIMIT 1', $this->table),
            [
                'id' => $id,
                'is_active' => true,
            ]
        );
    }

    public function touchLastLogin(int $id): void
    {
        $this->queryExecutor->execute(
            sprintf('UPDATE %s SET last_login_at = :last_login_at WHERE id = :id', $this->table),
            [
                'id' => $id,
                'last_login_at' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }
}
