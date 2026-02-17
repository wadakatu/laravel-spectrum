<?php

declare(strict_types=1);

namespace LaravelSpectrum\SDK;

use Symfony\Component\Process\Process;

final class SymfonyProcessSdkCommandRunner implements SdkCommandRunnerInterface
{
    /**
     * @param  array<int, string>  $command
     */
    public function run(array $command, string $workingDirectory): SdkCommandResult
    {
        $process = new Process($command, $workingDirectory);
        $process->setTimeout(null);
        $process->run();

        return new SdkCommandResult(
            exitCode: (int) ($process->getExitCode() ?? 1),
            output: $process->getOutput(),
            errorOutput: $process->getErrorOutput()
        );
    }
}
