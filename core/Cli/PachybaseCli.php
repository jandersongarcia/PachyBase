<?php

declare(strict_types=1);

namespace PachyBase\Cli;

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
        'crud:generate' => 'crud:sync',
        'release:check' => 'doctor',
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

        return match ($command) {
            'help' => $this->help(),
            'version' => $this->version(),
            'install' => $this->install(),
            'env:init' => $this->envInit($arguments),
            'doctor' => $this->doctor($arguments),
            'docker:install' => $this->dockerInstall(),
            'docker:up' => $this->dockerUp(),
            'docker:down' => $this->dockerDown(),
            'migrate' => $this->dockerPhpCommand(['php', 'scripts/migrate.php', 'up', ...$arguments]),
            'migrate:rollback' => $this->dockerPhpCommand(['php', 'scripts/migrate.php', 'down', ...$arguments]),
            'seed' => $this->dockerPhpCommand(['php', 'scripts/seed.php', 'run', ...$arguments]),
            'entity:list' => $this->dockerPhpCommand(['php', 'scripts/inspect-entities.php', ...$arguments]),
            'crud:sync' => $this->dockerPhpCommand(['php', 'scripts/crud-sync.php', ...$arguments]),
            'openapi:generate' => $this->dockerPhpCommand(['php', 'scripts/openapi-generate.php', ...$arguments]),
            'test' => $this->dockerPhpCommand(['vendor/bin/phpunit', '--testdox', ...$arguments]),
            default => $this->unknownCommand($command),
        };
    }

    private function help(): int
    {
        $this->write(<<<'TEXT'
PachyBase CLI

Usage:
  php pachybase <command> [options]

Commands:
  version             Print the current project version
  install             Prepare Docker, install dependencies, start services, and bootstrap the database
  env:init            Create .env from .env.example
  doctor              Validate release-critical configuration and Docker posture
  release:check       Alias of doctor
  docker:install      Generate docker-compose.yml, build the PHP image, and install Composer dependencies
  docker:up           Start the project containers
  docker:down         Stop the project containers
  migrate             Apply pending migrations
  migrate:rollback    Roll back migrations
  seed                Run pending seeders
  entity:list         Inspect the normalized entity metadata
  crud:sync           Sync config/CrudEntities.php from the current schema
  crud:generate       Alias of crud:sync
  openapi:generate    Generate a static OpenAPI document
  test                Run the PHPUnit suite in the PHP container

Examples:
  php pachybase version
  php pachybase env:init
  php pachybase doctor
  php pachybase install
  php pachybase migrate
  php pachybase crud:sync --expose-new
  php pachybase openapi:generate --output=docs-site/static/openapi.json

TEXT);

        return 0;
    }

    private function version(): int
    {
        return $this->runCommand($this->phpCommand(['scripts/version.php']));
    }

    private function install(): int
    {
        if (!is_file($this->path('.env'))) {
            $exitCode = $this->envInit([]);

            if ($exitCode !== 0) {
                return $exitCode;
            }
        }

        $this->write("Preparing Docker runtime and Composer dependencies..." . PHP_EOL);
        $exitCode = $this->dockerInstall();
        if ($exitCode !== 0) {
            return $exitCode;
        }

        $this->write("Starting containers..." . PHP_EOL);
        $exitCode = $this->dockerUp();
        if ($exitCode !== 0) {
            return $exitCode;
        }

        $this->write("Bootstrapping database schema and seeds..." . PHP_EOL);

        return $this->runCommand(
            $this->composeCommand(['run', '--rm', 'php', 'php', 'scripts/bootstrap-database.php'])
        );
    }

    /**
     * @param array<int, string> $arguments
     */
    private function envInit(array $arguments): int
    {
        $force = in_array('--force', $arguments, true);
        $targetPath = $this->path('.env');
        $sourcePath = $this->path('.env.example');

        if (!is_file($sourcePath)) {
            $this->error('.env.example was not found.');
            return 1;
        }

        if (is_file($targetPath) && !$force) {
            $this->write(".env already exists. Use --force to overwrite it." . PHP_EOL);
            return 0;
        }

        if (!copy($sourcePath, $targetPath)) {
            $this->error('Failed to create .env from .env.example.');
            return 1;
        }

        $this->write("Created .env from .env.example." . PHP_EOL);

        return 0;
    }

    private function dockerInstall(): int
    {
        if (!is_file($this->path('.env'))) {
            $this->error('Missing .env. Run "php pachybase env:init" first.');
            return 1;
        }

        $commands = [
            $this->phpCommand(['scripts/docker-install.php', '--dry-run']),
            $this->composeCommand(['build', 'php']),
            $this->composeCommand(['run', '--rm', '--no-deps', 'php', 'composer', 'install', '--no-interaction']),
        ];

        foreach ($commands as $command) {
            $exitCode = $this->runCommand($command);

            if ($exitCode !== 0) {
                return $exitCode;
            }
        }

        return 0;
    }

    /**
     * @param array<int, string> $arguments
     */
    private function doctor(array $arguments): int
    {
        return $this->runCommand($this->phpCommand(['scripts/doctor.php', ...$arguments]));
    }

    private function dockerUp(): int
    {
        if (!$this->composeFileExists()) {
            $this->error('docker/docker-compose.yml was not found. Run "php pachybase docker:install" first.');
            return 1;
        }

        return $this->runCommand($this->composeCommand(['up', '-d']));
    }

    private function dockerDown(): int
    {
        if (!$this->composeFileExists()) {
            $this->error('docker/docker-compose.yml was not found. Run "php pachybase docker:install" first.');
            return 1;
        }

        return $this->runCommand($this->composeCommand(['down']));
    }

    /**
     * @param array<int, string> $command
     */
    private function dockerPhpCommand(array $command): int
    {
        if (!$this->composeFileExists()) {
            $this->error('docker/docker-compose.yml was not found. Run "php pachybase docker:install" first.');
            return 1;
        }

        return $this->runCommand(
            $this->composeCommand(['run', '--rm', 'php', ...$command])
        );
    }

    private function unknownCommand(string $command): int
    {
        $this->error(sprintf('Unknown command "%s". Run "php pachybase help" to list the available commands.', $command));

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
    private function phpCommand(array $arguments): string
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

    private function error(string $message): void
    {
        file_put_contents('php://stderr', $message . PHP_EOL);
    }

    private function write(string $message): void
    {
        file_put_contents('php://output', $message);
    }
}
