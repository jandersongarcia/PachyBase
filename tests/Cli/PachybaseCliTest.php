<?php

declare(strict_types=1);

namespace Tests\Cli;

use PachyBase\Cli\PachybaseCli;
use PachyBase\Cli\ProcessRunnerInterface;
use PHPUnit\Framework\TestCase;

class PachybaseCliTest extends TestCase
{
    public function testHelpListsExpectedCommands(): void
    {
        $projectPath = $this->createProjectSkeleton();
        $cli = new PachybaseCli($projectPath, new RecordingProcessRunner());

        ob_start();
        $exitCode = $cli->run(['help']);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertIsString($output);
        $this->assertStringContainsString('version', $output);
        $this->assertStringContainsString('doctor', $output);
        $this->assertStringContainsString('acceptance:check', $output);
        $this->assertStringContainsString('http:smoke', $output);
        $this->assertStringContainsString('benchmark:local', $output);
        $this->assertStringContainsString('env:sync', $output);
        $this->assertStringContainsString('crud:generate', $output);
        $this->assertStringContainsString('openapi:build', $output);
        $this->assertStringContainsString('auth:token:create', $output);
        $this->assertStringContainsString('mcp:serve', $output);
    }

    public function testVersionCommandRunsVersionScriptLocally(): void
    {
        $projectPath = $this->createProjectSkeleton();
        $runner = new RecordingProcessRunner();
        $cli = new PachybaseCli($projectPath, $runner);

        $exitCode = $cli->run(['version']);

        $this->assertSame(0, $exitCode);
        $this->assertCount(1, $runner->calls);
        $this->assertStringContainsString('scripts/version.php', $runner->calls[0]['command']);
    }

    public function testDoctorAliasRunsDoctorScriptLocally(): void
    {
        $projectPath = $this->createProjectSkeleton();
        $runner = new RecordingProcessRunner();
        $cli = new PachybaseCli($projectPath, $runner);

        $exitCode = $cli->run(['release:check', '--json']);

        $this->assertSame(0, $exitCode);
        $this->assertCount(1, $runner->calls);
        $this->assertStringContainsString('scripts/doctor.php', $runner->calls[0]['command']);
        $this->assertStringContainsString('--json', $runner->calls[0]['command']);
    }

    public function testAcceptanceCheckRunsAcceptanceScriptLocally(): void
    {
        $projectPath = $this->createProjectSkeleton();
        $runner = new RecordingProcessRunner();
        $cli = new PachybaseCli($projectPath, $runner);

        $exitCode = $cli->run(['acceptance:check', '--json']);

        $this->assertSame(0, $exitCode);
        $this->assertCount(1, $runner->calls);
        $this->assertStringContainsString('scripts/acceptance-check.php', $runner->calls[0]['command']);
        $this->assertStringContainsString('--json', $runner->calls[0]['command']);
    }

    public function testHttpSmokeRunsHttpSmokeScriptLocally(): void
    {
        $projectPath = $this->createProjectSkeleton();
        $runner = new RecordingProcessRunner();
        $cli = new PachybaseCli($projectPath, $runner);

        $exitCode = $cli->run(['http:smoke', '--json']);

        $this->assertSame(0, $exitCode);
        $this->assertCount(1, $runner->calls);
        $this->assertStringContainsString('scripts/http-smoke.php', $runner->calls[0]['command']);
        $this->assertStringContainsString('--json', $runner->calls[0]['command']);
    }

    public function testBenchmarkLocalRunsBenchmarkScriptLocally(): void
    {
        $projectPath = $this->createProjectSkeleton();
        $runner = new RecordingProcessRunner();
        $cli = new PachybaseCli($projectPath, $runner);

        $exitCode = $cli->run(['benchmark:local', '--json']);

        $this->assertSame(0, $exitCode);
        $this->assertCount(1, $runner->calls);
        $this->assertStringContainsString('scripts/benchmark-local.php', $runner->calls[0]['command']);
        $this->assertStringContainsString('--json', $runner->calls[0]['command']);
    }

    public function testEnvInitCreatesEnvFileWithoutOverwritingByDefault(): void
    {
        $projectPath = $this->createProjectSkeleton();
        $cli = new PachybaseCli($projectPath, new RecordingProcessRunner());

        ob_start();
        $firstExitCode = $cli->run(['env:init']);
        $firstOutput = ob_get_clean();

        file_put_contents($projectPath . DIRECTORY_SEPARATOR . '.env', "APP_NAME=Changed\n");

        ob_start();
        $secondExitCode = $cli->run(['env:init']);
        $secondOutput = ob_get_clean();

        $this->assertSame(0, $firstExitCode);
        $this->assertSame(0, $secondExitCode);
        $this->assertStringContainsString('.env created', strtolower((string) $firstOutput));
        $this->assertStringContainsString('.env updated', strtolower((string) $secondOutput));
        $envContents = (string) file_get_contents($projectPath . DIRECTORY_SEPARATOR . '.env');
        $this->assertStringContainsString('APP_NAME=Changed', $envContents);
        $this->assertStringContainsString('APP_ENV=development', $envContents);
    }

    public function testInstallRunsDockerPreparationStartupAndBootstrapFlow(): void
    {
        $projectPath = $this->createProjectSkeleton();
        $runner = new RecordingProcessRunner();
        $cli = new PachybaseCli($projectPath, $runner);

        $exitCode = $cli->run(['install']);

        $this->assertSame(0, $exitCode);
        $this->assertCount(6, $runner->calls);
        $this->assertStringContainsString('scripts/docker-install.php', $runner->calls[0]['command']);
        $this->assertStringContainsString('--write-only', $runner->calls[0]['command']);
        $this->assertStringContainsString('up', $runner->calls[1]['command']);
        $this->assertStringContainsString('bootstrap-database.php', $runner->calls[2]['command']);
        $this->assertStringContainsString('scripts/seed.php', $runner->calls[3]['command']);
        $this->assertStringContainsString('scripts/openapi-generate.php', $runner->calls[4]['command']);
        $this->assertStringContainsString('scripts/ai-build.php', $runner->calls[5]['command']);
    }

    public function testStartSynchronizesComposeBeforeBootingDockerRuntime(): void
    {
        $projectPath = $this->createProjectSkeleton(withEnv: true);
        $runner = new RecordingProcessRunner();
        $cli = new PachybaseCli($projectPath, $runner);

        $exitCode = $cli->run(['start']);

        $this->assertSame(0, $exitCode);
        $this->assertCount(2, $runner->calls);
        $this->assertStringContainsString('scripts/docker-install.php', $runner->calls[0]['command']);
        $this->assertStringContainsString('--write-only', $runner->calls[0]['command']);
        $this->assertStringContainsString("'docker' 'compose' '-f' 'docker/docker-compose.yml' 'up' '-d'", $runner->calls[1]['command']);
    }

    public function testComposeSyncAliasRunsDockerSyncFlow(): void
    {
        $projectPath = $this->createProjectSkeleton(withEnv: true);
        $runner = new RecordingProcessRunner();
        $cli = new PachybaseCli($projectPath, $runner);

        $exitCode = $cli->run(['compose-sync']);

        $this->assertSame(0, $exitCode);
        $this->assertCount(1, $runner->calls);
        $this->assertStringContainsString('scripts/docker-install.php', $runner->calls[0]['command']);
        $this->assertStringContainsString('--write-only', $runner->calls[0]['command']);
    }

    public function testCrudGenerateAliasUsesCrudSyncScriptInsidePhpContainer(): void
    {
        $projectPath = $this->createProjectSkeleton(withCompose: true);
        $runner = new RecordingProcessRunner();
        $cli = new PachybaseCli($projectPath, $runner);

        $exitCode = $cli->run(['crud:generate', '--expose-new']);

        $this->assertSame(0, $exitCode);
        $this->assertCount(1, $runner->calls);
        $this->assertStringContainsString('run', $runner->calls[0]['command']);
        $this->assertStringContainsString('scripts/crud-sync.php', $runner->calls[0]['command']);
        $this->assertStringContainsString('--expose-new', $runner->calls[0]['command']);
    }

    public function testTestCommandRunsPhpunitInsidePhpContainer(): void
    {
        $projectPath = $this->createProjectSkeleton(withCompose: true);
        $runner = new RecordingProcessRunner();
        $cli = new PachybaseCli($projectPath, $runner);

        $exitCode = $cli->run(['test', '--filter', 'RouterTest']);

        $this->assertSame(0, $exitCode);
        $this->assertCount(1, $runner->calls);
        $this->assertStringContainsString('run', $runner->calls[0]['command']);
        $this->assertStringContainsString('vendor/bin/phpunit', $runner->calls[0]['command']);
        $this->assertStringContainsString('--testdox', $runner->calls[0]['command']);
        $this->assertStringContainsString('RouterTest', $runner->calls[0]['command']);
    }

    public function testAuthTokenCreateRunsInsidePhpContainer(): void
    {
        $projectPath = $this->createProjectSkeleton(withCompose: true);
        $runner = new RecordingProcessRunner();
        $cli = new PachybaseCli($projectPath, $runner);

        $exitCode = $cli->run(['auth:token:create', 'Codex Agent', '--scope=crud:read']);

        $this->assertSame(0, $exitCode);
        $this->assertCount(1, $runner->calls);
        $this->assertStringContainsString('scripts/auth-token-create.php', $runner->calls[0]['command']);
        $this->assertStringContainsString('Codex Agent', $runner->calls[0]['command']);
        $this->assertStringContainsString('--scope=crud:read', $runner->calls[0]['command']);
    }

    public function testMcpServeRunsInsidePhpContainer(): void
    {
        $projectPath = $this->createProjectSkeleton(withCompose: true);
        $runner = new RecordingProcessRunner();
        $cli = new PachybaseCli($projectPath, $runner);

        $exitCode = $cli->run(['mcp:serve', '--base-url=http://localhost:8080']);

        $this->assertSame(0, $exitCode);
        $this->assertCount(1, $runner->calls);
        $this->assertStringContainsString('scripts/mcp-serve.php', $runner->calls[0]['command']);
        $this->assertStringContainsString('--base-url=http://localhost:8080', $runner->calls[0]['command']);
    }

    public function testUnknownCommandReturnsErrorExitCode(): void
    {
        $projectPath = $this->createProjectSkeleton();
        $cli = new PachybaseCli($projectPath, new RecordingProcessRunner());

        $exitCode = $cli->run(['phase12:missing']);

        $this->assertSame(1, $exitCode);
    }

    private function createProjectSkeleton(bool $withCompose = false, bool $withEnv = false): string
    {
        $projectPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-cli-' . bin2hex(random_bytes(6));

        mkdir($projectPath, 0777, true);
        mkdir($projectPath . DIRECTORY_SEPARATOR . 'scripts', 0777, true);
        mkdir($projectPath . DIRECTORY_SEPARATOR . 'docker', 0777, true);
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . '.env.example', implode(PHP_EOL, [
            'APP_NAME=PachyBase',
            'APP_ENV=development',
            'APP_DEBUG=true',
            'APP_RUNTIME=docker',
            'APP_HOST=127.0.0.1',
            'APP_PORT=8080',
            'APP_URL=http://localhost:8080',
            'DB_DRIVER=mysql',
            'DB_HOST=db',
            'DB_PORT=3306',
            'DB_DATABASE=pachybase',
            'DB_USERNAME=pachybase',
            'DB_PASSWORD=secret',
        ]) . PHP_EOL);

        if ($withCompose) {
            file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'docker-compose.yml', "services:\n");
        }

        if ($withEnv) {
            copy(
                $projectPath . DIRECTORY_SEPARATOR . '.env.example',
                $projectPath . DIRECTORY_SEPARATOR . '.env'
            );
        }

        return $projectPath;
    }
}

final class RecordingProcessRunner implements ProcessRunnerInterface
{
    /**
     * @var array<int, array{command: string, cwd: ?string}>
     */
    public array $calls = [];

    public function run(string $command, ?string $workingDirectory = null): int
    {
        $this->calls[] = [
            'command' => $command,
            'cwd' => $workingDirectory,
        ];

        if (
            $workingDirectory !== null
            && str_contains($command, 'scripts/docker-install.php')
        ) {
            $composePath = $workingDirectory . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'docker-compose.yml';

            if (!is_dir(dirname($composePath))) {
                mkdir(dirname($composePath), 0777, true);
            }

            file_put_contents($composePath, "services:\n");
        }

        return 0;
    }
}
