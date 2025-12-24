<?php

declare(strict_types=1);

namespace LaravelSpectrum\Contracts\Analyzers;

/**
 * Interface for analyzers that analyze a specific method within a class.
 *
 * Implementations analyze controller methods or other class methods
 * by their class name and method name.
 */
interface MethodAnalyzer extends Analyzer
{
    /**
     * Analyze a method within a class and return the analysis results.
     *
     * @param  class-string  $class  The fully qualified class name
     * @param  string  $method  The method name to analyze
     * @return array<string, mixed> The analysis results (empty array if class/method not found)
     */
    public function analyze(string $class, string $method): array;
}
