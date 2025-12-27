<?php

declare(strict_types=1);

namespace LaravelSpectrum\Contracts\Performance;

/**
 * Interface for resolving optimal worker count for parallel processing.
 */
interface WorkerCountResolverInterface
{
    /**
     * Resolve the optimal number of workers.
     */
    public function resolve(): int;
}
