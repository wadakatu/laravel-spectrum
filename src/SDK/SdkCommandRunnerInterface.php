<?php

declare(strict_types=1);

namespace LaravelSpectrum\SDK;

interface SdkCommandRunnerInterface
{
    /**
     * @param  array<int, string>  $command
     */
    public function run(array $command, string $workingDirectory): SdkCommandResult;
}
