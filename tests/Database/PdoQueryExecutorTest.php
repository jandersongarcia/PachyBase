<?php

declare(strict_types=1);

namespace Tests\Database;

use PDO;
use PDOException;
use PachyBase\Database\Query\PdoQueryExecutor;
use PHPUnit\Framework\TestCase;

class PdoQueryExecutorTest extends TestCase
{
    public function testTransactionSkipsCommitWhenDriverAutoCommitsDuringCallback(): void
    {
        $pdo = new class('sqlite::memory:') extends PDO {
            private bool $transactionOpen = false;

            public function beginTransaction(): bool
            {
                $this->transactionOpen = true;

                return true;
            }

            public function commit(): bool
            {
                if (!$this->transactionOpen) {
                    throw new PDOException('There is no active transaction');
                }

                $this->transactionOpen = false;

                return true;
            }

            public function rollBack(): bool
            {
                $this->transactionOpen = false;

                return true;
            }

            public function inTransaction(): bool
            {
                return $this->transactionOpen;
            }

            public function prepare($query, $options = []): \PDOStatement|false
            {
                $this->transactionOpen = false;

                return parent::prepare('SELECT 1', $options);
            }
        };

        $executor = new PdoQueryExecutor($pdo);

        $result = $executor->transaction(static function (PdoQueryExecutor $queryExecutor): string {
            $queryExecutor->select('SELECT 1');

            return 'ok';
        });

        $this->assertSame('ok', $result);
    }
}
