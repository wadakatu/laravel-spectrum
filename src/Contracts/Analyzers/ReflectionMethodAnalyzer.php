<?php

declare(strict_types=1);

namespace LaravelSpectrum\Contracts\Analyzers;

use ReflectionMethod;

/**
 * Interface for analyzers that analyze a method using reflection.
 *
 * Implementations use ReflectionMethod to extract type information,
 * parameter details, and other metadata from methods.
 */
interface ReflectionMethodAnalyzer extends Analyzer
{
    /**
     * Analyze a method using reflection and return the analysis results.
     *
     * @param  ReflectionMethod  $method  The reflection method to analyze
     * @return array<string, mixed> The analysis results (empty array if analysis yields no results)
     */
    public function analyze(ReflectionMethod $method): array;
}
