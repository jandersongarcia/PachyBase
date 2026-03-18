<?php

declare(strict_types=1);

namespace PachyBase\Cli;

interface ProcessRunnerInterface
{
    public function run(string $command, ?string $workingDirectory = null): int;
}
