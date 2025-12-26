<?php

declare(strict_types=1);

namespace LaravelSpectrum\Performance\Support;

use LaravelSpectrum\Contracts\Performance\ParallelExecutorInterface;
use Spatie\Fork\Fork;

/**
 * Parallel executor using Spatie Fork.
 */
class ForkParallelExecutor implements ParallelExecutorInterface
{
    public function execute(array $tasks, int $workers): array
    {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('Fork is not available');
        }

        $fork = Fork::new()
            ->concurrent($workers)
            ->before(function () {
                $this->initializeChildProcess();
            });

        return $fork->run(...$tasks);
    }

    public function isAvailable(): bool
    {
        return class_exists(Fork::class);
    }

    /**
     * Initialize child process (e.g., reset database connections).
     */
    protected function initializeChildProcess(): void
    {
        if (class_exists('\Illuminate\Support\Facades\DB')) {
            try {
                \Illuminate\Support\Facades\DB::reconnect();
            } catch (\Exception $e) {
                // Ignore connection errors
            }
        }
    }
}
