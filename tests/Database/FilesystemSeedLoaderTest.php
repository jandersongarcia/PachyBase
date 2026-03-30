<?php

declare(strict_types=1);

namespace Tests\Database;

use PachyBase\Database\Seeds\FilesystemSeedLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FilesystemSeedLoaderTest extends TestCase
{
    private ?string $directory = null;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pb_seed_loader_' . bin2hex(random_bytes(4));
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

    public function testLoadsAndSortsSeedFilesByName(): void
    {
        file_put_contents(
            $this->directory . DIRECTORY_SEPARATOR . '202603170002_second.php',
            <<<'PHP'
<?php

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Query\QueryExecutorInterface;
use PachyBase\Database\Seeds\SeederInterface;

return new class implements SeederInterface {
    public function name(): string
    {
        return '202603170002_second';
    }

    public function description(): string
    {
        return 'Second';
    }

    public function run(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
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
use PachyBase\Database\Query\QueryExecutorInterface;
use PachyBase\Database\Seeds\SeederInterface;

return new class implements SeederInterface {
    public function name(): string
    {
        return '202603170001_first';
    }

    public function description(): string
    {
        return 'First';
    }

    public function run(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
    }
};
PHP
        );

        $loader = new FilesystemSeedLoader();
        $seeders = $loader->load((string) $this->directory);

        $this->assertCount(2, $seeders);
        $this->assertSame('202603170001_first', $seeders[0]->name());
        $this->assertSame('202603170002_second', $seeders[1]->name());
    }

    public function testRejectsDuplicateSeederNames(): void
    {
        file_put_contents(
            $this->directory . DIRECTORY_SEPARATOR . '202603170001_first.php',
            <<<'PHP'
<?php

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Query\QueryExecutorInterface;
use PachyBase\Database\Seeds\SeederInterface;

return new class implements SeederInterface {
    public function name(): string
    {
        return '202603170001_seed';
    }

    public function description(): string
    {
        return 'First';
    }

    public function run(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
    }
};
PHP
        );

        file_put_contents(
            $this->directory . DIRECTORY_SEPARATOR . '202603170002_duplicate.php',
            <<<'PHP'
<?php

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Query\QueryExecutorInterface;
use PachyBase\Database\Seeds\SeederInterface;

return new class implements SeederInterface {
    public function name(): string
    {
        return '202603170001_seed';
    }

    public function description(): string
    {
        return 'Duplicate';
    }

    public function run(QueryExecutorInterface $queryExecutor, DatabaseAdapterInterface $adapter): void
    {
    }
};
PHP
        );

        $loader = new FilesystemSeedLoader();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate seeder names were found');

        $loader->load((string) $this->directory);
    }
}
