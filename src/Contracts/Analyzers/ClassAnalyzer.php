<?php

declare(strict_types=1);

namespace LaravelSpectrum\Contracts\Analyzers;

/**
 * Interface for analyzers that analyze a class by its fully qualified name.
 *
 * Implementations analyze classes such as FormRequest, Resource, or Transformer
 * and return structured analysis results.
 */
interface ClassAnalyzer extends Analyzer
{
    /**
     * Analyze a class and return the analysis results.
     *
     * @param  class-string  $class  The fully qualified class name to analyze
     * @return array<string, mixed> The analysis results
     */
    public function analyze(string $class): array;
}
