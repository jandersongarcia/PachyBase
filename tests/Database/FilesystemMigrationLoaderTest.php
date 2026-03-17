<?php

declare(strict_types=1);

namespace Tests\Database;

use PachyBase\Database\Migrations\FilesystemMigrationLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FilesystemMigrationLoaderTest extends TestCase
{
    private ?string $directory = null;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pb_loader_' . bin2hex(random_bytes(4));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        if ($this->directory === null || !is_dir($this->directory)) {
            return;
        }

        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    public function testLoadsAndSortsMigrationFilesByVersion(): void
    {
        file_put_contents(
            $this->directory . DIRECTORY_SEPARATOR . '202603170002_second.php',
            <<<'PHP'
<?php

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Migrations\MigrationInterface;
use PachyBase\Database\Query\QueryExecutorInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603170002';
    }

    public function description(): string
    {
        return 'Second';
    }

    public function up(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
    }

    public function down(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
    }
};
PHP
        );

        file_put_contents(
            $this->directory . DIRECTORY_SEPARATOR . '202603170001_first.php',
            <<<'PHP'
<?php

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Migrations\MigrationInterface;
use PachyBase\Database\Query\QueryExecutorInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603170001';
    }

    public function description(): string
    {
        return 'First';
    }

    public function up(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
    }

    public function down(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
    }
};
PHP
        );

        $loader = new FilesystemMigrationLoader();
        $migrations = $loader->load((string) $this->directory);

        $this->assertCount(2, $migrations);
        $this->assertSame('202603170001', $migrations[0]->version());
        $this->assertSame('202603170002', $migrations[1]->version());
    }

    public function testRejectsDuplicateMigrationVersions(): void
    {
        file_put_contents(
            $this->directory . DIRECTORY_SEPARATOR . '202603170001_first.php',
            <<<'PHP'
<?php

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Migrations\MigrationInterface;
use PachyBase\Database\Query\QueryExecutorInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603170001';
    }

    public function description(): string
    {
        return 'First';
    }

    public function up(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
    }

    public function down(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
    }
};
PHP
        );

        file_put_contents(
            $this->directory . DIRECTORY_SEPARATOR . '202603170001_duplicate.php',
            <<<'PHP'
<?php

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Migrations\MigrationInterface;
use PachyBase\Database\Query\QueryExecutorInterface;

return new class implements MigrationInterface {
    public function version(): string
    {
        return '202603170001';
    }

    public function description(): string
    {
        return 'Duplicate';
    }

    public function up(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
    }

    public function down(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
    }
};
PHP
        );

        $loader = new FilesystemMigrationLoader();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate migration versions were found');

        $loader->load((string) $this->directory);
    }
}
