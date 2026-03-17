<?php

declare(strict_types=1);

namespace PachyBase\Auth;

use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Query\QueryExecutorInterface;
use PachyBase\Utils\Json;

final class RefreshTokenRepository
{
    private readonly QueryExecutorInterface $queryExecutor;
    private readonly string $table;

    public function __construct(?QueryExecutorInterface $queryExecutor = null)
    {
        $connection = Connection::getInstance();
        $adapter = AdapterFactory::make($connection);

        $this->queryExecutor = $queryExecutor ?? new PdoQueryExecutor($connection->getPDO());
        $this->table = $adapter->quoteIdentifier('pb_auth_sessions');
    }

    /**
     * @param array<int, string> $scopes
     * @return array<string, mixed>
     */
    public function create(int $userId, string $refreshTokenHash, array $scopes, string $expiresAt): array
    {
        $this->queryExecutor->execute(
            sprintf(
                'INSERT INTO %s (user_id, refresh_token_hash, scopes, expires_at) VALUES (:user_id, :refresh_token_hash, :scopes, :expires_at)',
                $this->table
            ),
            [
                'user_id' => $userId,
                'refresh_token_hash' => $refreshTokenHash,
                'scopes' => Json::encode(array_values($scopes)),
                'expires_at' => $expiresAt,
            ]
        );

        $id = (int) Connection::getInstance()->getPDO()->lastInsertId();

        return (array) $this->findById($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByHash(string $hash): ?array
    {
        return $this->queryExecutor->selectOne(
            sprintf(
                'SELECT * FROM %s WHERE refresh_token_hash = :refresh_token_hash AND revoked_at IS NULL AND expires_at > :now LIMIT 1',
                $this->table
            ),
            [
                'refresh_token_hash' => $hash,
                'now' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->queryExecutor->selectOne(
            sprintf('SELECT * FROM %s WHERE id = :id LIMIT 1', $this->table),
            ['id' => $id]
        );
    }

    public function touchLastUsed(int $id): void
    {
        $this->queryExecutor->execute(
            sprintf('UPDATE %s SET last_used_at = :last_used_at WHERE id = :id', $this->table),
            [
                'id' => $id,
                'last_used_at' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    public function revokeById(int $id): bool
    {
        $affectedRows = $this->queryExecutor->execute(
            sprintf('UPDATE %s SET revoked_at = :revoked_at WHERE id = :id AND revoked_at IS NULL', $this->table),
            [
                'id' => $id,
                'revoked_at' => gmdate('Y-m-d H:i:s'),
            ]
        );

        return $affectedRows > 0;
    }

    public function revokeByHash(string $hash): bool
    {
        $affectedRows = $this->queryExecutor->execute(
            sprintf(
                'UPDATE %s SET revoked_at = :revoked_at WHERE refresh_token_hash = :refresh_token_hash AND revoked_at IS NULL',
                $this->table
            ),
            [
                'refresh_token_hash' => $hash,
                'revoked_at' => gmdate('Y-m-d H:i:s'),
            ]
        );

        return $affectedRows > 0;
    }
}
