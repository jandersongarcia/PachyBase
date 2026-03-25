<?php

declare(strict_types=1);

namespace PachyBase\Cli;

use RuntimeException;

final class PachybaseCli
{
    private const COMPOSE_FILE = 'docker/docker-compose.yml';

    /**
     * @var array<string, string>
     */
    private const ALIASES = [
        'help' => 'help',
        '--help' => 'help',
        '-h' => 'help',
        'env:init' => 'env:sync',
        'docker:install' => 'docker:sync',
        'compose-sync' => 'docker:sync',
        'compose:sync' => 'docker:sync',
        'release:check' => 'doctor',
        'migrate' => 'db:migrate',
        'migrate:rollback' => 'db:rollback',
        'seed' => 'db:seed',
        'openapi:generate' => 'openapi:build',
    ];

    public function __construct(
        private readonly string $basePath,
        private readonly ?ProcessRunnerInterface $runner = null
    ) {
    }

    public function run(array $argv): int
    {
        $command = $this->normalizeCommand($argv[0] ?? 'help');
        $arguments = array_slice($argv, 1);

        try {
            return match ($command) {
                'help' => $this->help(),
                'version' => $this->version(),
                'install' => $this->install($arguments),
                'start' => $this->start(),
                'stop' => $this->stop(),
                'doctor' => $this->doctor($arguments),
                'acceptance:check' => $this->acceptanceCheck($arguments),
                'http:smoke' => $this->httpSmoke($arguments),
                'benchmark:local' => $this->benchmarkLocal($arguments),
                'status' => $this->status($arguments),
                'env:sync' => $this->envSync($arguments),
                'env:validate' => $this->envValidate($arguments),
                'app:key' => $this->appKey($arguments),
                'auth:token:create' => $this->authTokenCreate($arguments),
                'mcp:serve' => $this->mcpServe($arguments),
                'docker:sync' => $this->dockerSync(),
                'docker:up' => $this->dockerUp(),
                'docker:down' => $this->dockerDown(),
                'docker:logs' => $this->dockerLogs($arguments),
                'db:setup' => $this->dbSetup($arguments),
                'db:migrate' => $this->dbMigrate($arguments),
                'db:rollback' => $this->dbRollback($arguments),
                'db:seed' => $this->dbSeed($arguments),
                'db:fresh' => $this->dbFresh($arguments),
                'project:provision' => $this->projectProvision($arguments),
                'project:backup' => $this->projectBackup($arguments),
                'project:restore' => $this->projectRestore($arguments),
                'jobs:work' => $this->jobsWork($arguments),
                'make:module' => $this->makeModule($arguments),
                'make:entity' => $this->makeEntity($arguments),
                'make:migration' => $this->makeMigration($arguments),
                'make:seed' => $this->makeSeed($arguments),
                'make:controller' => $this->makeController($arguments),
                'make:service' => $this->makeService($arguments),
                'make:middleware' => $this->makeMiddleware($arguments),
                'make:test' => $this->makeTest($arguments),
                'entity:list' => $this->entityList($arguments),
                'crud:sync' => $this->crudSync($arguments),
                'crud:generate' => $this->crudGenerate($arguments),
                'auth:install' => $this->authInstall($arguments),
                'openapi:build' => $this->openapiBuild($arguments),
                'ai:build' => $this->aiBuild($arguments),
                'test' => $this->test($arguments),
                default => $this->unknownCommand($command),
            };
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return 1;
        }
    }

    private function help(): int
    {
        $this->write(<<<'TEXT'
PachyBase CLI

Usage:
  php scripts/pachybase.php <command> [options]

Lifecycle:
  install
  start
  stop
  doctor
  acceptance:check
  http:smoke
  benchmark:local
  status
  test

Environment:
  env:sync
  env:validate
  app:key

Docker:
  docker:sync
  docker:up
  docker:down
  docker:logs

Database:
  db:setup
  db:migrate
  db:rollback
  db:seed
  db:fresh

Platform:
  project:provision
  project:backup
  project:restore
  jobs:work

Scaffolding:
  make:module
  make:entity
  make:migration
  make:seed
  make:controller
  make:service
  make:middleware
  make:test
  crud:generate

Build:
  auth:install
  auth:token:create
  mcp:serve
  openapi:build
  ai:build
  entity:list
  version

Examples:
  php scripts/pachybase.php install
  php scripts/pachybase.php status --json
  php scripts/pachybase.php acceptance:check --json
  php scripts/pachybase.php http:smoke --json
  php scripts/pachybase.php benchmark:local --json
  php scripts/pachybase.php make:migration create_orders_table
  php scripts/pachybase.php crud:generate --expose-new
  php scripts/pachybase.php auth:token:create "Codex Agent" --scope=crud:read
  php scripts/pachybase.php project:provision --name="Acme" --slug=acme
  php scripts/pachybase.php project:backup --project=acme
  php scripts/pachybase.php jobs:work --project=acme --limit=25
  php scripts/pachybase.php mcp:serve
  php scripts/pachybase.php openapi:build --output=docs-site/static/openapi.json

TEXT);

        return 0;
    }

    private function version(): int
    {
        return $this->runCommand($this->localPhpCommand(['scripts/version.php']));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function install(array $arguments): int
    {
        $envResult = $this->envManager()->syncFromTemplate($this->hasFlag($arguments, '--force-env'));
        $this->write(sprintf(".env %s at %s\n", $envResult['status'], $envResult['path']));

        $validation = $this->envManager()->validate();
        if ($validation['errors'] !== []) {
            foreach ($validation['errors'] as $error) {
                $this->error($error);
            }

            return 1;
        }

        if ($this->envManager()->getValue('APP_KEY') === null || trim((string) $this->envManager()->getValue('APP_KEY')) === '') {
            $exitCode = $this->appKey([]);
            if ($exitCode !== 0) {
                return $exitCode;
            }
        }

        $exitCode = $this->authInstall(['--skip-db']);
        if ($exitCode !== 0) {
            return $exitCode;
        }

        if ($this->runtimeMode() === 'docker') {
            $exitCode = $this->prepareDockerRuntime();
            if ($exitCode !== 0) {
                return $exitCode;
            }
        } else {
            $exitCode = $this->ensureLocalDependencies();
            if ($exitCode !== 0) {
                return $exitCode;
            }
        }

        foreach ([
            fn(): int => $this->dbSetup($arguments),
            fn(): int => $this->dbSeed($arguments),
            fn(): int => $this->openapiBuild([]),
            fn(): int => $this->aiBuild([]),
        ] as $step) {
            $exitCode = $step();

            if ($exitCode !== 0) {
                return $exitCode;
            }
        }

        if ($this->runtimeMode() === 'local') {
            return $this->start();
        }

        $this->write(sprintf("PachyBase is ready at %s\n", $this->envManager()->appUrl()));

        return 0;
    }

    private function start(): int
    {
        if ($this->runtimeMode() === 'docker') {
            $exitCode = $this->prepareDockerRuntime();

            if ($exitCode === 0) {
                $this->write(sprintf("Docker runtime started at %s\n", $this->envManager()->appUrl()));
            }

            return $exitCode;
        }

        $host = $this->appHost();
        $port = $this->appPort();
        $payload = (new LocalRuntimeManager($this->basePath))->start($host, $port);

        $this->write(sprintf(
            "Local runtime started at %s (PID %d)\n",
            $payload['url'],
            $payload['pid']
        ));

        return 0;
    }

    private function stop(): int
    {
        if ($this->runtimeMode() === 'docker') {
            return $this->dockerDown();
        }

        $payload = (new LocalRuntimeManager($this->basePath))->stop($this->appHost(), $this->appPort());
        $this->write(sprintf("Local runtime stopped for %s\n", $payload['url']));

        return 0;
    }

    /**
     * @param array<int, string> $arguments
     */
    private function doctor(array $arguments): int
    {
        return $this->runCommand($this->localPhpCommand(['scripts/doctor.php', ...$arguments]));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function acceptanceCheck(array $arguments): int
    {
        return $this->runCommand($this->localPhpCommand(['scripts/acceptance-check.php', ...$arguments]));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function httpSmoke(array $arguments): int
    {
        return $this->runCommand($this->localPhpCommand(['scripts/http-smoke.php', ...$arguments]));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function benchmarkLocal(array $arguments): int
    {
        return $this->runCommand($this->localPhpCommand(['scripts/benchmark-local.php', ...$arguments]));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function status(array $arguments): int
    {
        return $this->runCommand($this->localPhpCommand(['scripts/status.php', ...$arguments]));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function envSync(array $arguments): int
    {
        $payload = $this->envManager()->syncFromTemplate($this->hasFlag($arguments, '--force'));
        $this->write(sprintf(".env %s at %s\n", $payload['status'], $payload['path']));

        if ($payload['added'] !== []) {
            $this->write('Added keys: ' . implode(', ', $payload['added']) . PHP_EOL);
        }

        return 0;
    }

    /**
     * @param array<int, string> $arguments
     */
    private function envValidate(array $arguments): int
    {
        return $this->runCommand($this->localPhpCommand(['scripts/env-validate.php', ...$arguments]));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function appKey(array $arguments): int
    {
        if (
            !$this->hasFlag($arguments, '--force')
            && trim((string) $this->envManager()->getValue('APP_KEY', '')) !== ''
        ) {
            $this->write("APP_KEY is already configured. Use --force to regenerate it.\n");

            return 0;
        }

        $value = 'base64:' . base64_encode(random_bytes(32));
        $this->envManager()->setValue('APP_KEY', $value);
        $this->write("APP_KEY updated successfully.\n");

        return 0;
    }

    private function dockerSync(): int
    {
        if (!$this->envManager()->envExists()) {
            $this->error('Missing .env. Run "env:sync" first.');

            return 1;
        }

        return $this->runCommand($this->localPhpCommand(['scripts/docker-install.php', '--write-only']));
    }

    private function dockerUp(): int
    {
        if (!$this->composeFileExists()) {
            $this->error('docker/docker-compose.yml was not found. Run "docker:sync" first.');

            return 1;
        }

        return $this->runCommand($this->composeCommand(['up', '-d']));
    }

    private function prepareDockerRuntime(): int
    {
        $exitCode = $this->dockerSync();

        if ($exitCode !== 0) {
            return $exitCode;
        }

        return $this->dockerUp();
    }

    private function dockerDown(): int
    {
        if (!$this->composeFileExists()) {
            $this->error('docker/docker-compose.yml was not found. Run "docker:sync" first.');

            return 1;
        }

        return $this->runCommand($this->composeCommand(['down']));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function dockerLogs(array $arguments): int
    {
        if (!$this->composeFileExists()) {
            $this->error('docker/docker-compose.yml was not found. Run "docker:sync" first.');

            return 1;
        }

        return $this->runCommand($this->composeCommand(['logs', ...$arguments]));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function dbSetup(array $arguments): int
    {
        return $this->runRuntimePhpScript('scripts/bootstrap-database.php', ['--skip-seeds', ...$arguments]);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function dbMigrate(array $arguments): int
    {
        return $this->runRuntimePhpScript('scripts/migrate.php', ['up', ...$arguments]);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function dbRollback(array $arguments): int
    {
        return $this->runRuntimePhpScript('scripts/migrate.php', ['down', ...$arguments]);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function dbSeed(array $arguments): int
    {
        return $this->runRuntimePhpScript('scripts/seed.php', ['run', ...$arguments]);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function dbFresh(array $arguments): int
    {
        return $this->runRuntimePhpScript('scripts/db-fresh.php', $arguments);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function projectProvision(array $arguments): int
    {
        return $this->runRuntimePhpScript('scripts/project-provision.php', $arguments);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function projectBackup(array $arguments): int
    {
        return $this->runRuntimePhpScript('scripts/project-backup.php', $arguments);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function projectRestore(array $arguments): int
    {
        return $this->runRuntimePhpScript('scripts/project-restore.php', $arguments);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function jobsWork(array $arguments): int
    {
        return $this->runRuntimePhpScript('scripts/jobs-work.php', $arguments);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function makeModule(array $arguments): int
    {
        return $this->writeGeneratedPath($this->scaffold()->createModule(
            $this->requiredNameArgument($arguments, 'make:module'),
            $this->hasFlag($arguments, '--force')
        ));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function makeEntity(array $arguments): int
    {
        return $this->writeGeneratedPath($this->scaffold()->registerCrudEntity(
            $this->requiredNameArgument($arguments, 'make:entity'),
            $this->optionValue($arguments, '--table='),
            $this->hasFlag($arguments, '--force')
        ));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function makeMigration(array $arguments): int
    {
        return $this->writeGeneratedPath($this->scaffold()->createMigration(
            $this->requiredNameArgument($arguments, 'make:migration'),
            $this->hasFlag($arguments, '--force')
        ));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function makeSeed(array $arguments): int
    {
        return $this->writeGeneratedPath($this->scaffold()->createSeed(
            $this->requiredNameArgument($arguments, 'make:seed'),
            $this->hasFlag($arguments, '--force')
        ));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function makeController(array $arguments): int
    {
        return $this->writeGeneratedPath($this->scaffold()->createController(
            $this->requiredNameArgument($arguments, 'make:controller'),
            $this->hasFlag($arguments, '--force')
        ));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function makeService(array $arguments): int
    {
        return $this->writeGeneratedPath($this->scaffold()->createService(
            $this->requiredNameArgument($arguments, 'make:service'),
            $this->hasFlag($arguments, '--force')
        ));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function makeMiddleware(array $arguments): int
    {
        return $this->writeGeneratedPath($this->scaffold()->createMiddleware(
            $this->requiredNameArgument($arguments, 'make:middleware'),
            $this->hasFlag($arguments, '--force')
        ));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function makeTest(array $arguments): int
    {
        return $this->writeGeneratedPath($this->scaffold()->createTest(
            $this->requiredNameArgument($arguments, 'make:test'),
            $this->optionValue($arguments, '--type=') ?? 'unit',
            $this->hasFlag($arguments, '--force')
        ));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function entityList(array $arguments): int
    {
        return $this->runRuntimePhpScript('scripts/inspect-entities.php', $arguments);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function crudSync(array $arguments): int
    {
        return $this->runRuntimePhpScript('scripts/crud-sync.php', $arguments);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function crudGenerate(array $arguments): int
    {
        $name = $this->firstPositionalArgument($arguments);

        if ($name !== null) {
            return $this->makeEntity($arguments);
        }

        $runtimeArguments = $arguments;

        if (!$this->hasFlag($runtimeArguments, '--expose-new')) {
            $runtimeArguments[] = '--expose-new';
        }

        return $this->runRuntimePhpScript('scripts/crud-sync.php', $runtimeArguments);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function authInstall(array $arguments): int
    {
        if (
            $this->hasFlag($arguments, '--force-key')
            || trim((string) $this->envManager()->getValue('APP_KEY', '')) === ''
        ) {
            $exitCode = $this->appKey(['--force']);

            if ($exitCode !== 0) {
                return $exitCode;
            }
        }

        if (
            $this->hasFlag($arguments, '--force-secret')
            || trim((string) $this->envManager()->getValue('AUTH_JWT_SECRET', '')) === ''
        ) {
            $this->envManager()->setValue(
                'AUTH_JWT_SECRET',
                (string) $this->envManager()->getValue('APP_KEY', 'base64:' . base64_encode(random_bytes(32)))
            );
            $this->write("AUTH_JWT_SECRET configured.\n");
        }

        if ($this->hasFlag($arguments, '--skip-db')) {
            return 0;
        }

        foreach ([fn(): int => $this->dbMigrate([]), fn(): int => $this->dbSeed([])] as $step) {
            $exitCode = $step();

            if ($exitCode !== 0) {
                return $exitCode;
            }
        }

        return 0;
    }

    /**
     * @param array<int, string> $arguments
     */
    private function authTokenCreate(array $arguments): int
    {
        return $this->runRuntimePhpScript('scripts/auth-token-create.php', $arguments);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function mcpServe(array $arguments): int
    {
        return $this->runRuntimePhpScript('scripts/mcp-serve.php', $arguments);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function openapiBuild(array $arguments): int
    {
        return $this->runRuntimePhpScript('scripts/openapi-generate.php', $arguments);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function aiBuild(array $arguments): int
    {
        return $this->runRuntimePhpScript('scripts/ai-build.php', $arguments);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function test(array $arguments): int
    {
        if ($this->runtimeMode() === 'docker') {
            return $this->runCommand(
                $this->composeCommand(['run', '--rm', 'php', 'vendor/bin/phpunit', '--testdox', ...$arguments])
            );
        }

        return $this->runCommand($this->command([PHP_BINARY, 'vendor/bin/phpunit', '--testdox', ...$arguments]));
    }

    /**
     * @param array<int, string> $arguments
     */
    private function hasFlag(array $arguments, string $flag): bool
    {
        return in_array($flag, $arguments, true);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function optionValue(array $arguments, string $prefix): ?string
    {
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, $prefix)) {
                return substr($argument, strlen($prefix));
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $arguments
     */
    private function requiredNameArgument(array $arguments, string $command): string
    {
        $name = $this->firstPositionalArgument($arguments);

        if ($name === null || $name === '') {
            throw new RuntimeException(sprintf('The %s command requires a name argument.', $command));
        }

        return $name;
    }

    /**
     * @param array<int, string> $arguments
     */
    private function firstPositionalArgument(array $arguments): ?string
    {
        foreach ($arguments as $argument) {
            if (!str_starts_with($argument, '--')) {
                return $argument;
            }
        }

        return null;
    }

    private function ensureLocalDependencies(): int
    {
        if (is_file($this->path('vendor/autoload.php'))) {
            return 0;
        }

        $composer = $this->findComposerExecutable();

        if ($composer === null) {
            $this->error('Composer dependencies are missing and Composer was not found on the host.');

            return 1;
        }

        return $this->runCommand($this->command([$composer, 'install', '--no-interaction']));
    }

    private function findComposerExecutable(): ?string
    {
        foreach (['composer', 'composer.bat'] as $candidate) {
            $exitCode = 0;
            exec($this->command([$candidate, '--version']), $output, $exitCode);

            if ($exitCode === 0) {
                return $candidate;
            }
        }

        return null;
    }

    private function writeGeneratedPath(string $path): int
    {
        $this->write(sprintf("Generated: %s\n", $path));

        return 0;
    }

    private function appHost(): string
    {
        return trim((string) $this->envManager()->getValue('APP_HOST', '127.0.0.1'));
    }

    private function appPort(): int
    {
        return max(1, (int) $this->envManager()->getValue('APP_PORT', '8080'));
    }

    private function runtimeMode(): string
    {
        return $this->envManager()->runtimeMode();
    }

    /**
     * @param array<int, string> $arguments
     */
    private function localPhpCommand(array $arguments): string
    {
        return $this->command([PHP_BINARY, ...$arguments]);
    }

    /**
     * @param array<int, string> $arguments
     */
    private function composeCommand(array $arguments): string
    {
        return $this->command(['docker', 'compose', '-f', self::COMPOSE_FILE, ...$arguments]);
    }

    private function runRuntimePhpScript(string $scriptPath, array $arguments = []): int
    {
        if ($this->runtimeMode() === 'docker') {
            if (!$this->composeFileExists()) {
                $this->error('docker/docker-compose.yml was not found. Run "docker:sync" first.');

                return 1;
            }

            return $this->runCommand(
                $this->composeCommand(['run', '--rm', 'php', 'php', $scriptPath, ...$arguments])
            );
        }

        return $this->runCommand($this->localPhpCommand([$scriptPath, ...$arguments]));
    }

    private function unknownCommand(string $command): int
    {
        $this->error(sprintf('Unknown command "%s". Run "help" to list the available commands.', $command));

        return 1;
    }

    private function composeFileExists(): bool
    {
        return is_file($this->path(self::COMPOSE_FILE));
    }

    private function normalizeCommand(string $command): string
    {
        return self::ALIASES[$command] ?? $command;
    }

    /**
     * @param array<int, string> $arguments
     */
    private function command(array $arguments): string
    {
        return implode(
            ' ',
            array_map(
                static fn(string $argument): string => escapeshellarg($argument),
                $arguments
            )
        );
    }

    private function runCommand(string $command): int
    {
        return ($this->runner ?? new SystemProcessRunner())->run($command, $this->basePath);
    }

    private function path(string $relativePath): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }

    private function envManager(): EnvironmentFileManager
    {
        return new EnvironmentFileManager($this->basePath);
    }

    private function scaffold(): ScaffoldGenerator
    {
        return new ScaffoldGenerator($this->basePath);
    }

    private function error(string $message): void
    {
        file_put_contents('php://stderr', $message . PHP_EOL);
    }

    private function write(string $message): void
    {
        file_put_contents('php://output', $message);
    }
}
