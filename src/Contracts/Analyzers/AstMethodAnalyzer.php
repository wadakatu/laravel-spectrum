<?php

declare(strict_types=1);

namespace LaravelSpectrum\Contracts\Analyzers;

use PhpParser\Node\Stmt\ClassMethod;

/**
 * Interface for analyzers that analyze method AST nodes.
 *
 * Implementations use PHP-Parser AST nodes to perform static code analysis
 * on method bodies and extract validation rules or other patterns.
 */
interface AstMethodAnalyzer extends Analyzer
{
    /**
     * Analyze a method AST node and return the analysis results.
     *
     * @param  ClassMethod  $method  The AST node representing a class method
     * @return array<string, mixed> The analysis results (empty array if no patterns detected)
     */
    public function analyze(ClassMethod $method): array;
}
