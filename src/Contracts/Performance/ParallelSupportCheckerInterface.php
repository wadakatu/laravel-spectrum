<?php

declare(strict_types=1);

namespace LaravelSpectrum\Contracts\Performance;

/**
 * Interface for checking parallel processing support.
 */
interface ParallelSupportCheckerInterface
{
    /**
     * Check if parallel processing is supported in the current environment.
     */
    public function isSupported(): bool;
}
