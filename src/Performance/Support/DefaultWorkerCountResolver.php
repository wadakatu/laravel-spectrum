<?php

declare(strict_types=1);

namespace LaravelSpectrum\Performance\Support;

use LaravelSpectrum\Contracts\Performance\WorkerCountResolverInterface;

/**
 * Default implementation for resolving optimal worker count.
 */
class DefaultWorkerCountResolver implements WorkerCountResolverInterface
{
    private int $minWorkers;

    private int $maxWorkers;

    private int $multiplier;

    public function __construct(
        int $minWorkers = 2,
        int $maxWorkers = 16,
        int $multiplier = 2
    ) {
        $this->minWorkers = $minWorkers;
        $this->maxWorkers = $maxWorkers;
        $this->multiplier = $multiplier;
    }

    public function resolve(): int
    {
        $cores = $this->detectCpuCores();

        return max($this->minWorkers, min($this->maxWorkers, $cores * $this->multiplier));
    }

    /**
     * Detect the number of CPU cores.
     */
    protected function detectCpuCores(): int
    {
        if (function_exists('swoole_cpu_num')) {
            return swoole_cpu_num();
        }

        if (is_file('/proc/cpuinfo')) {
            $content = file_get_contents('/proc/cpuinfo');

            return $content !== false ? substr_count($content, 'processor') : 1;
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            $result = shell_exec('sysctl -n hw.ncpu');

            return $result !== null ? (int) $result : 1;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $processors = getenv('NUMBER_OF_PROCESSORS');

            return $processors !== false ? (int) $processors : 1;
        }

        return 1;
    }
}
