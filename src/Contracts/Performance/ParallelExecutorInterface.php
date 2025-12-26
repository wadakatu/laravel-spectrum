<?php

declare(strict_types=1);

namespace LaravelSpectrum\Contracts\Performance;

/**
 * Interface for executing tasks in parallel.
 */
interface ParallelExecutorInterface
{
    /**
     * Execute tasks in parallel.
     *
     * @param  array<callable>  $tasks  Array of callable tasks
     * @param  int  $workers  Number of concurrent workers
     * @return array<mixed> Results from each task
     */
    public function execute(array $tasks, int $workers): array;

    /**
     * Check if the executor is available.
     */
    public function isAvailable(): bool;
}
