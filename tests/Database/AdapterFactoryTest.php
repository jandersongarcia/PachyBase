<?php

declare(strict_types=1);

namespace Tests\Database;

use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Adapters\MySqlAdapter;
use PachyBase\Database\Adapters\PostgresAdapter;
use PachyBase\Database\Connection;
use PHPUnit\Framework\TestCase;

class AdapterFactoryTest extends TestCase
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

    public function testResolvesAdapterForCurrentConfiguredDriver(): void
    {
        $adapter = AdapterFactory::make();

        $this->assertContains($adapter::class, [MySqlAdapter::class, PostgresAdapter::class]);
    }
}
