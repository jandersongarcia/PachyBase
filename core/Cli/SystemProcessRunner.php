<?php

declare(strict_types=1);

namespace PachyBase\Cli;

use RuntimeException;

final class SystemProcessRunner implements ProcessRunnerInterface
{
    public function run(string $command, ?string $workingDirectory = null): int
    {
        $process = proc_open(
            $command,
            [
                0 => STDIN,
                1 => STDOUT,
                2 => STDERR,
            ],
            $pipes,
            $workingDirectory
        );

        if (!is_resource($process)) {
            throw new RuntimeException(sprintf('Unable to start process: %s', $command));
        }

        return proc_close($process);
    }
}
