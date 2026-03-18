<?php

declare(strict_types=1);

namespace Tests\Database;

use PachyBase\Config;
use PachyBase\Database\AdapterFactory;
use PachyBase\Database\Connection;
use PachyBase\Database\Migrations\FilesystemMigrationLoader;
use PachyBase\Database\Migrations\MigrationRepository;
use PachyBase\Database\Migrations\MigrationRunner;
use PachyBase\Database\Query\PdoQueryExecutor;
use PachyBase\Database\Schema\SchemaInspector;
use PHPUnit\Framework\TestCase;

class MigrationRunnerIntegrationTest extends TestCase
{
    private ?PdoQueryExecutor $queryExecutor = null;
    private ?string $migrationDirectory = null;
    private ?string $tableName = null;
    private ?string $version = null;

    protected function setUp(): void
    {
        Config::load(dirname(__DIR__, 2));

        $this->queryExecutor = new PdoQueryExecutor(Connection::getInstance()->getPDO());
        $suffix = bin2hex(random_bytes(4));
        $this->tableName = 'pb_phase2_migration_' . $suffix;
        $this->version = '20260317' . substr($suffix, 0, 6);
        $this->migrationDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pb_migrations_' . $suffix;

        mkdir($this->migrationDirectory, 0777, true);
        $this->writeMigrationFile();
    }

    protected function tearDown(): void
    {
        if ($this->queryExecutor !== null && $this->tableName !== null && $this->version !== null) {
            $adapter = AdapterFactory::make();
            $repository = new MigrationRepository($this->queryExecutor, $adapter);
            $repository->ensureTable();

            $this->queryExecutor->execute(
                sprintf('DROP TABLE IF EXISTS %s', $adapter->quoteIdentifier($this->tableName))
            );
            $repository->forget($this->version);
        }

        if ($this->migrationDirectory !== null && is_dir($this->migrationDirectory)) {
            foreach (glob($this->migrationDirectory . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
                unlink($file);
            }

            rmdir($this->migrationDirectory);
        }

        Connection::reset();
        Config::reset();
    }

    public function testAppliesAndRollsBackMigrationsThroughTheCentralRunner(): void
    {
        $adapter = AdapterFactory::make();
        $loader = new FilesystemMigrationLoader();
        $runner = new MigrationRunner($this->queryExecutor, $adapter);
        $inspector = new SchemaInspector($adapter);
        $migrations = $loader->load((string) $this->migrationDirectory);

        $statusBefore = $runner->status($migrations);
        $migrateResult = $runner->migrate($migrations);
        $databaseSchema = $inspector->inspectDatabase();
        $statusAfter = $runner->status($migrations);
        $rollbackResult = $runner->rollback($migrations);
        $schemaAfterRollback = $inspector->inspectDatabase();

        $this->assertFalse($statusBefore[0]['applied']);
        $this->assertSame(1, $migrateResult['applied_count']);
        $this->assertSame([(string) $this->version], $migrateResult['applied_versions']);
        $this->assertNotNull($databaseSchema->table((string) $this->tableName));
        $this->assertTrue($statusAfter[0]['applied']);
        $this->assertSame(1, $rollbackResult['rolled_back_count']);
        $this->assertSame([(string) $this->version], $rollbackResult['rolled_back_versions']);
        $this->assertNull($schemaAfterRollback->table((string) $this->tableName));
    }

    private function writeMigrationFile(): void
    {
        $file = $this->migrationDirectory . DIRECTORY_SEPARATOR . $this->version . '_create_test_table.php';
        $table = var_export($this->tableName, true);
        $version = var_export($this->version, true);

        file_put_contents(
            $file,
            <<<PHP
<?php

use PachyBase\Database\Adapters\DatabaseAdapterInterface;
use PachyBase\Database\Migrations\AbstractSqlMigration;

return new class({$table}, {$version}) extends AbstractSqlMigration {
    public function __construct(
        private readonly string \$table,
        private readonly string \$version
    ) {
    }

    public function version(): string
    {
        return \$this->version;
    }

    public function description(): string
    {
        return 'Create a migration-managed test table';
    }

    protected function upStatements(DatabaseAdapterInterface \$adapter): array
    {
        \$table = \$adapter->quoteIdentifier(\$this->table);

        return match (\$adapter->driver()) {
            'mysql' => [
                "CREATE TABLE {\$table} (`id` BIGINT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(120) NOT NULL)"
            ],
            default => [
                "CREATE TABLE {\$table} (\"id\" BIGSERIAL PRIMARY KEY, \"name\" VARCHAR(120) NOT NULL)"
            ],
        };
    }

    protected function downStatements(DatabaseAdapterInterface \$adapter): array
    {
        return [
            'DROP TABLE IF EXISTS ' . \$adapter->quoteIdentifier(\$this->table),
        ];
    }
};
PHP
        );
    }
}
