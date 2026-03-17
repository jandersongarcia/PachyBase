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
        $this->assertStringContainsString('env:init', $output);
        $this->assertStringContainsString('crud:sync', $output);
        $this->assertStringContainsString('openapi:generate', $output);
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
        $this->assertStringContainsString('Created .env', (string) $firstOutput);
        $this->assertStringContainsString('already exists', (string) $secondOutput);
        $this->assertSame("APP_NAME=Changed\n", file_get_contents($projectPath . DIRECTORY_SEPARATOR . '.env'));
    }

    public function testInstallRunsDockerPreparationStartupAndBootstrapFlow(): void
    {
        $projectPath = $this->createProjectSkeleton(withCompose: true);
        $runner = new RecordingProcessRunner();
        $cli = new PachybaseCli($projectPath, $runner);

        $exitCode = $cli->run(['install']);

        $this->assertSame(0, $exitCode);
        $this->assertCount(5, $runner->calls);
        $this->assertStringContainsString('scripts/docker-install.php', $runner->calls[0]['command']);
        $this->assertStringContainsString('build', $runner->calls[1]['command']);
        $this->assertStringContainsString('composer', $runner->calls[2]['command']);
        $this->assertStringContainsString('up', $runner->calls[3]['command']);
        $this->assertStringContainsString('bootstrap-database.php', $runner->calls[4]['command']);
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

    public function testUnknownCommandReturnsErrorExitCode(): void
    {
        $projectPath = $this->createProjectSkeleton();
        $cli = new PachybaseCli($projectPath, new RecordingProcessRunner());

        $exitCode = $cli->run(['phase12:missing']);

        $this->assertSame(1, $exitCode);
    }

    private function createProjectSkeleton(bool $withCompose = false): string
    {
        $projectPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pachybase-cli-' . bin2hex(random_bytes(6));

        mkdir($projectPath, 0777, true);
        mkdir($projectPath . DIRECTORY_SEPARATOR . 'scripts', 0777, true);
        mkdir($projectPath . DIRECTORY_SEPARATOR . 'docker', 0777, true);
        file_put_contents($projectPath . DIRECTORY_SEPARATOR . '.env.example', "APP_NAME=PachyBase\n");

        if ($withCompose) {
            file_put_contents($projectPath . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'docker-compose.yml', "services:\n");
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

        return 0;
    }
}
