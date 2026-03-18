<?php

declare(strict_types=1);

namespace Tests\Database;

use PachyBase\Config;
use PachyBase\Database\Connection;
use PachyBase\Database\Query\PdoQueryExecutor;
use PHPUnit\Framework\TestCase;

class QueryExecutorIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load(dirname(__DIR__, 2));
    }

    protected function tearDown(): void
    {
        Connection::reset();
        Config::reset();
    }

    public function testExecutesParameterizedQueryAgainstActiveDatabase(): void
    {
        $executor = new PdoQueryExecutor(Connection::getInstance()->getPDO());
        $value = 'safe-binding-check';

        $result = $executor->scalar('SELECT :value AS probe', ['value' => $value]);

        $this->assertSame($value, $result);
    }
}
