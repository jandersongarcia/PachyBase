<?php

declare(strict_types=1);

namespace PachyBase\Cli;

use RuntimeException;

final class LocalRuntimeManager
{
    private const STARTUP_TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly string $basePath
    ) {
    }

    /**
     * @return array{mode: string, pid: int, host: string, port: int, url: string, log: string}
     */
    public function start(string $host, int $port): array
    {
        if (!$this->phpBinaryAvailable()) {
            throw new RuntimeException('PHP is required to start the local runtime.');
        }

        $status = $this->status($host, $port);
        if ($status['running']) {
            return [
                'mode' => 'local',
                'pid' => (int) $status['pid'],
                'host' => $host,
                'port' => $port,
                'url' => $this->url($host, $port),
                'log' => $this->logFile(),
            ];
        }

        $this->ensureRuntimeDirectory();
        $command = sprintf('-S %s:%d -t public public/router.php', $host, $port);
        $pid = $this->spawnInBackground($command);
        file_put_contents($this->pidFile(), (string) $pid);
        $this->waitUntilReachable($host, $port);

        return [
            'mode' => 'local',
            'pid' => $pid,
            'host' => $host,
            'port' => $port,
            'url' => $this->url($host, $port),
            'log' => $this->logFile(),
        ];
    }

    /**
     * @return array{mode: string, running: bool, pid: int|null, url: string, log: string}
     */
    public function stop(string $host, int $port): array
    {
        $status = $this->status($host, $port);

        if (!$status['running'] || $status['pid'] === null) {
            @unlink($this->pidFile());

            return [
                'mode' => 'local',
                'running' => false,
                'pid' => null,
                'url' => $this->url($host, $port),
                'log' => $this->logFile(),
            ];
        }

        $pid = (int) $status['pid'];
        $exitCode = 0;

        if ($this->isWindows()) {
            exec(sprintf('taskkill /PID %d /T /F', $pid), $output, $exitCode);
        } else {
            exec(sprintf('kill %d', $pid), $output, $exitCode);
        }

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf('Failed to stop local runtime PID %d.', $pid));
        }

        @unlink($this->pidFile());

        return [
            'mode' => 'local',
            'running' => false,
            'pid' => $pid,
            'url' => $this->url($host, $port),
            'log' => $this->logFile(),
        ];
    }

    /**
     * @return array{mode: string, running: bool, pid: int|null, url: string, log: string}
     */
    public function status(string $host, int $port): array
    {
        $pid = is_file($this->pidFile()) ? (int) trim((string) file_get_contents($this->pidFile())) : null;
        $running = $pid !== null && $pid > 0 && $this->processExists($pid);

        if (!$running && is_file($this->pidFile())) {
            @unlink($this->pidFile());
        }

        return [
            'mode' => 'local',
            'running' => $running,
            'pid' => $running ? $pid : null,
            'url' => $this->url($host, $port),
            'log' => $this->logFile(),
        ];
    }

    public function phpBinaryAvailable(): bool
    {
        $exitCode = 0;

        exec($this->isWindows() ? 'where php' : 'command -v php', $output, $exitCode);

        return $exitCode === 0;
    }

    private function spawnInBackground(string $command): int
    {
        if ($this->isWindows()) {
            $powershellCommand = sprintf(
                "\$p = Start-Process -FilePath 'php' -ArgumentList '%s' -WorkingDirectory '%s' -WindowStyle Hidden -RedirectStandardOutput '%s' -RedirectStandardError '%s' -PassThru; Write-Output \$p.Id",
                str_replace("'", "''", $command),
                str_replace("'", "''", $this->basePath),
                str_replace("'", "''", $this->logFile()),
                str_replace("'", "''", $this->logFile())
            );

            $output = shell_exec('powershell -NoProfile -Command "' . $powershellCommand . '"');
            $pid = (int) trim((string) $output);

            if ($pid <= 0) {
                throw new RuntimeException('Failed to start the local runtime process.');
            }

            return $pid;
        }

        $escapedWorkdir = addcslashes($this->basePath, "\\'");
        $escapedLog = addcslashes($this->logFile(), "\\'");
        $shellCommand = sprintf(
            "cd '%s' && php %s > '%s' 2>&1 & echo $!",
            $escapedWorkdir,
            $command,
            $escapedLog
        );

        $output = shell_exec("sh -c \"$shellCommand\"");
        $pid = (int) trim((string) $output);

        if ($pid <= 0) {
            throw new RuntimeException('Failed to start the local runtime process.');
        }

        return $pid;
    }

    private function waitUntilReachable(string $host, int $port): void
    {
        $deadline = microtime(true) + self::STARTUP_TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            $connection = @fsockopen($host, $port, $errno, $error, 1.0);

            if (is_resource($connection)) {
                fclose($connection);
                return;
            }

            usleep(250000);
        }

        throw new RuntimeException('The local runtime did not become reachable in time.');
    }

    private function processExists(int $pid): bool
    {
        $exitCode = 0;

        if ($this->isWindows()) {
            exec(sprintf('tasklist /FI "PID eq %d"', $pid), $output, $exitCode);

            return $exitCode === 0
                && count(array_filter($output, static fn(string $line): bool => str_contains($line, (string) $pid))) > 0;
        }

        exec(sprintf('kill -0 %d', $pid), $output, $exitCode);

        return $exitCode === 0;
    }

    private function ensureRuntimeDirectory(): void
    {
        if (!is_dir($this->runtimeDirectory()) && !mkdir($concurrentDirectory = $this->runtimeDirectory(), 0777, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Unable to create runtime directory "%s".', $this->runtimeDirectory()));
        }
    }

    private function runtimeDirectory(): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . '.pachybase' . DIRECTORY_SEPARATOR . 'runtime';
    }

    private function pidFile(): string
    {
        return $this->runtimeDirectory() . DIRECTORY_SEPARATOR . 'local-server.pid';
    }

    private function logFile(): string
    {
        return $this->runtimeDirectory() . DIRECTORY_SEPARATOR . 'local-server.log';
    }

    private function url(string $host, int $port): string
    {
        return sprintf('http://%s:%d', $host, $port);
    }

    private function isWindows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }
}
