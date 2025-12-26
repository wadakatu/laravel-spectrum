<?php

declare(strict_types=1);

namespace LaravelSpectrum\Performance\Support;

use LaravelSpectrum\Contracts\Performance\ParallelSupportCheckerInterface;

/**
 * Default implementation for checking parallel processing support.
 */
class DefaultParallelSupportChecker implements ParallelSupportCheckerInterface
{
    public function isSupported(): bool
    {
        // PCNTL extension is required
        if (! $this->isPcntlLoaded()) {
            return false;
        }

        // Windows is not supported
        if ($this->isWindows()) {
            return false;
        }

        // Check configuration
        if ($this->isDisabledByConfig()) {
            return false;
        }

        return true;
    }

    /**
     * Check if PCNTL extension is loaded.
     */
    protected function isPcntlLoaded(): bool
    {
        return extension_loaded('pcntl');
    }

    /**
     * Check if running on Windows.
     */
    protected function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /**
     * Check if disabled by configuration.
     */
    protected function isDisabledByConfig(): bool
    {
        if (function_exists('config')) {
            try {
                return config('spectrum.performance.parallel_processing', true) === false;
            } catch (\Throwable $e) {
                // Container not available
                return false;
            }
        }

        return false;
    }
}
