<?php

declare(strict_types=1);

namespace LaravelSpectrum\Contracts\Analyzers;

/**
 * Base marker interface for all code analyzers.
 *
 * This interface serves as a common base for all analyzer types.
 * Specific analyze() method signatures are defined in sub-interfaces.
 *
 * Analyzers that need error collection should also implement HasErrors.
 */
interface Analyzer
{
    // Marker interface - specific analyze() methods defined in sub-interfaces
}
