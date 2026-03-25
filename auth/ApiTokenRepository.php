<?php

declare(strict_types=1);

namespace PachyBase\Auth;

use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Query\QueryExecutorInterface;
use PachyBase\Utils\Json;

final class ApiTokenRepository
{
    private readonly QueryExecutorInterface $queryExecutor;
    private readonly string $table;

    public function __construct(?QueryExecutorInterface $queryExecutor = null)
    {
        $connection = Connection::getInstance();
        $adapter = AdapterFactory::make($connection);

        $this->queryExecutor = $queryExecutor ?? new PdoQueryExecutor($connection->getPDO());
        $this->table = $adapter->quoteIdentifier('pb_api_tokens');
    }

    /**
     * @param array<int, string> $scopes
     * @return array<string, mixed>
     */
    public function create(
        string $name,
        string $tokenHash,
        string $tokenPrefix,
        array $scopes,
        ?int $userId,
        int $tenantId,
        ?int $createdByUserId,
        ?string $expiresAt
    ): array {
        $this->queryExecutor->execute(
            sprintf(
                'INSERT INTO %s (tenant_id, name, token_hash, token_prefix, scopes, user_id, created_by_user_id, is_active, expires_at) VALUES (:tenant_id, :name, :token_hash, :token_prefix, :scopes, :user_id, :created_by_user_id, :is_active, :expires_at)',
                $this->table
            ),
            [
                'tenant_id' => $tenantId,
                'name' => $name,
                'token_hash' => $tokenHash,
                'token_prefix' => $tokenPrefix,
                'scopes' => Json::encode(array_values($scopes)),
                'user_id' => $userId,
                'created_by_user_id' => $createdByUserId,
                'is_active' => true,
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
                'SELECT * FROM %s WHERE token_hash = :token_hash AND is_active = :is_active AND (expires_at IS NULL OR expires_at > :now) LIMIT 1',
                $this->table
            ),
            [
                'token_hash' => $hash,
                'is_active' => true,
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

    public function revokeById(int $id, ?int $revokedByUserId = null, ?string $reason = null): bool
    {
        $affectedRows = $this->queryExecutor->execute(
            sprintf(
                'UPDATE %s SET is_active = :is_active, revoked_at = :revoked_at, revoked_by_user_id = :revoked_by_user_id, revoked_reason = :revoked_reason WHERE id = :id AND is_active = :currently_active',
                $this->table
            ),
            [
                'id' => $id,
                'is_active' => false,
                'currently_active' => true,
                'revoked_at' => gmdate('Y-m-d H:i:s'),
                'revoked_by_user_id' => $revokedByUserId,
                'revoked_reason' => $reason,
            ]
        );

        return $affectedRows > 0;
    }
}
