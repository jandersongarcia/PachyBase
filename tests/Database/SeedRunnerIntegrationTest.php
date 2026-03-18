<?php

declare(strict_types=1);

namespace Tests\Database;

use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Migrations\FilesystemMigrationLoader;
use PachyBase\Database\Migrations\MigrationRunner;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Seeds\FilesystemSeedLoader;
use PachyBase\Database\Seeds\SeedRunner;
use PHPUnit\Framework\TestCase;
use Throwable;

class SeedRunnerIntegrationTest extends TestCase
{
    private ?PdoQueryExecutor $queryExecutor = null;
    private ?string $migrationDirectory = null;
    private ?string $seedDirectory = null;
    private ?string $tableName = null;

    protected function setUp(): void
    {
        Config::load(dirname(__DIR__, 2));

        $this->queryExecutor = new PdoQueryExecutor(Connection::getInstance()->getPDO());
        $suffix = bin2hex(random_bytes(4));
        $this->tableName = 'pb_phase3_seed_' . $suffix;
        $this->migrationDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pb_seed_migrations_' . $suffix;
        $this->seedDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pb_seed_files_' . $suffix;

        mkdir($this->migrationDirectory, 0777, true);
        mkdir($this->seedDirectory, 0777, true);

        $this->writeMigrationFile();
        $this->writeSeedFile();
    }

    protected function tearDown(): void
    {
        if ($this->queryExecutor !== null && $this->tableName !== null) {
            $adapter = AdapterFactory::make();

            foreach ([
                sprintf('DROP TABLE IF EXISTS %s', $adapter->quoteIdentifier($this->tableName)),
                sprintf(
                    'DELETE FROM %s WHERE name = :name',
                    $adapter->quoteIdentifier('pachybase_seeders')
                ),
                sprintf(
                    'DELETE FROM %s WHERE version = :version',
                    $adapter->quoteIdentifier('pachybase_migrations')
                ),
            ] as $index => $statement) {
                try {
                    $bindings = match ($index) {
                        1 => ['name' => '20260317_seed_rows'],
                        2 => ['version' => '20260317_seed_table'],
                        default => [],
                    };

                    $this->queryExecutor->execute($statement, $bindings);
                } catch (Throwable) {
                }
            }
        }

        foreach ([$this->migrationDirectory, $this->seedDirectory] as $directory) {
            if ($directory === null || !is_dir($directory)) {
                continue;
            }

            foreach (glob($directory . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
                unlink($file);
            }

            rmdir($directory);
        }

        Connection::reset();
        Config::reset();
    }

    public function testRunsSeedersAgainstTheActiveDatabaseDriver(): void
    {
        $adapter = AdapterFactory::make();
        $migrationRunner = new MigrationRunner($this->queryExecutor, $adapter);
        $seedRunner = new SeedRunner($this->queryExecutor, $adapter);
        $migrationLoader = new FilesystemMigrationLoader();
        $seedLoader = new FilesystemSeedLoader();
        $migrations = $migrationLoader->load((string) $this->migrationDirectory);
        $seeders = $seedLoader->load((string) $this->seedDirectory);

        $migrationRunner->migrate($migrations);

        $statusBefore = $seedRunner->status($seeders);
        $runResult = $seedRunner->run($seeders);
        $statusAfter = $seedRunner->status($seeders);
        $rows = $this->queryExecutor->select(
            sprintf('SELECT label FROM %s ORDER BY id ASC', $adapter->quoteIdentifier((string) $this->tableName))
        );

        $this->assertFalse($statusBefore[0]['executed']);
        $this->assertSame(1, $runResult['executed_count']);
        $this->assertSame(['alpha', 'beta'], array_column($rows, 'label'));
        $this->assertTrue($statusAfter[0]['executed']);
    }

    private function writeMigrationFile(): void
    {
        $table = var_export($this->tableName, true);
        $file = $this->migrationDirectory . DIRECTORY_SEPARATOR . '20260317_seed_table.php';

        file_put_contents(
            $file,
            <<<PHP
<?php

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Migrations\AbstractSqlMigration;

return new class({$table}) extends AbstractSqlMigration {
    public function __construct(
        private readonly string \$table
    ) {
    }

    public function version(): string
    {
        return '20260317_seed_table';
    }

    public function description(): string
    {
        return 'Create temporary seed table';
    }

    protected function upStatements(DatabaseAdapterInterface \$adapter): array
    {
        \$table = \$adapter->quoteIdentifier(\$this->table);

        return match (\$adapter->driver()) {
            'mysql' => [
                "CREATE TABLE {\$table} (`id` BIGINT AUTO_INCREMENT PRIMARY KEY, `label` VARCHAR(80) NOT NULL)"
            ],
            default => [
                "CREATE TABLE {\$table} (\"id\" BIGSERIAL PRIMARY KEY, \"label\" VARCHAR(80) NOT NULL)"
            ],
        };
    }

    protected function downStatements(DatabaseAdapterInterface \$adapter): array
    {
        return ['DROP TABLE IF EXISTS ' . \$adapter->quoteIdentifier(\$this->table)];
    }
};
PHP
        );
    }

    private function writeSeedFile(): void
    {
        $table = var_export($this->tableName, true);
        $file = $this->seedDirectory . DIRECTORY_SEPARATOR . '20260317_seed_rows.php';

        file_put_contents(
            $file,
            <<<PHP
<?php

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Seeds\AbstractSqlSeeder;

return new class({$table}) extends AbstractSqlSeeder {
    public function __construct(
        private readonly string \$table
    ) {
    }

    public function name(): string
    {
        return '20260317_seed_rows';
    }

    public function description(): string
    {
        return 'Seed temporary rows';
    }

    protected function statements(DatabaseAdapterInterface \$adapter): array
    {
        \$table = \$adapter->quoteIdentifier(\$this->table);

        return [
            "DELETE FROM {\$table}",
            [
                'sql' => "INSERT INTO {\$table} (label) VALUES (:label)",
                'bindings' => ['label' => 'alpha'],
            ],
            [
                'sql' => "INSERT INTO {\$table} (label) VALUES (:label)",
                'bindings' => ['label' => 'beta'],
            ],
        ];
    }
};
PHP
        );
    }
}
