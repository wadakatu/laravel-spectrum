<?php

namespace LaravelSpectrum\Analyzers;

use LaravelSpectrum\Support\PaginationDetector;
use PhpParser\Error;
use PhpParser\Parser;
use ReflectionMethod;

class PaginationAnalyzer
{
    private PaginationDetector $paginationDetector;

    private Parser $parser;

    public function __construct(
        Parser $parser,
        PaginationDetector $paginationDetector
    ) {
        $this->parser = $parser;
        $this->paginationDetector = $paginationDetector;
    }

    /**
     * Analyze a controller method for pagination usage
     *
     * @return array|null ['type' => 'paginate'|'simplePaginate'|'cursorPaginate', 'model' => string, 'resource' => string|null]
     */
    public function analyzeMethod(ReflectionMethod $method): ?array
    {
        try {
            $methodContent = $this->getMethodContent($method);

            if (! $methodContent) {
                return null;
            }

            $ast = $this->parser->parse('<?php '.$methodContent);

            if (! $ast) {
                return null;
            }

            $paginationInfo = $this->paginationDetector->detectPaginationCalls($ast);

            return ! empty($paginationInfo) ? $paginationInfo[0] : null;
        } catch (Error $e) {
            // Log parsing error
            return null;
        }
    }

    /**
     * Analyze method return type for pagination interfaces
     *
     * @return array|null ['type' => 'paginate'|'simplePaginate'|'cursorPaginate']
     */
    public function analyzeReturnType(ReflectionMethod $method): ?array
    {
        $returnType = $method->getReturnType();

        if (! $returnType) {
            return null;
        }

        $typeName = ltrim((string) $returnType, '?');

        return match ($typeName) {
            'Illuminate\Contracts\Pagination\LengthAwarePaginator' => ['type' => 'paginate'],
            'Illuminate\Pagination\LengthAwarePaginator' => ['type' => 'paginate'],
            'Illuminate\Contracts\Pagination\Paginator' => ['type' => 'simplePaginate'],
            'Illuminate\Pagination\Paginator' => ['type' => 'simplePaginate'],
            'Illuminate\Contracts\Pagination\CursorPaginator' => ['type' => 'cursorPaginate'],
            'Illuminate\Pagination\CursorPaginator' => ['type' => 'cursorPaginate'],
            default => null
        };
    }

    /**
     * Get method content from reflection
     */
    private function getMethodContent(ReflectionMethod $method): ?string
    {
        $filename = $method->getFileName();

        if (! $filename || ! file_exists($filename)) {
            return null;
        }

        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        if (! $startLine || ! $endLine) {
            return null;
        }

        $lines = file($filename);

        if (! $lines) {
            return null;
        }

        // Extract method body
        $methodLines = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);
        $methodContent = implode('', $methodLines);

        // Extract just the method body (between first { and last })
        if (preg_match('/\{(.*)\}/s', $methodContent, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
