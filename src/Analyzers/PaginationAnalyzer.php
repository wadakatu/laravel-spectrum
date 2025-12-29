<?php

namespace LaravelSpectrum\Analyzers;

use LaravelSpectrum\DTO\PaginationInfo;
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
     * Analyze a controller method for pagination usage.
     */
    public function analyzeMethod(ReflectionMethod $method): ?PaginationInfo
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

            if (empty($paginationInfo)) {
                return null;
            }

            return PaginationInfo::fromArray($paginationInfo[0]);
        } catch (Error $e) {
            // Log parsing error
            return null;
        }
    }

    /**
     * Analyze method return type for pagination interfaces.
     */
    public function analyzeReturnType(ReflectionMethod $method): ?PaginationInfo
    {
        $returnType = $method->getReturnType();

        if (! $returnType) {
            return null;
        }

        $typeName = ltrim((string) $returnType, '?');

        $type = match ($typeName) {
            'Illuminate\Contracts\Pagination\LengthAwarePaginator' => 'paginate',
            'Illuminate\Pagination\LengthAwarePaginator' => 'paginate',
            'Illuminate\Contracts\Pagination\Paginator' => 'simplePaginate',
            'Illuminate\Pagination\Paginator' => 'simplePaginate',
            'Illuminate\Contracts\Pagination\CursorPaginator' => 'cursorPaginate',
            'Illuminate\Pagination\CursorPaginator' => 'cursorPaginate',
            default => null
        };

        if ($type === null) {
            return null;
        }

        return new PaginationInfo(type: $type);
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
