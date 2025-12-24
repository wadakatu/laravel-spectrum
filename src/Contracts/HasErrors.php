<?php

declare(strict_types=1);

namespace LaravelSpectrum\Contracts;

use LaravelSpectrum\Support\ErrorCollector;

/**
 * Interface for classes that collect errors during analysis.
 *
 * Implement this interface to indicate that a class can report
 * errors and warnings through an ErrorCollector.
 */
interface HasErrors
{
    /**
     * Get the error collector instance.
     */
    public function getErrorCollector(): ErrorCollector;
}
