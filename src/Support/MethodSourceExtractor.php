<?php

declare(strict_types=1);

namespace LaravelSpectrum\Support;

/**
 * Extracts source code from PHP methods using Reflection.
 *
 * Provides utility methods to extract either the full method source
 * (including signature) or just the method body.
 */
final class MethodSourceExtractor
{
    /**
     * Extract the full source code of a method including signature.
     *
     * @param  \ReflectionMethod  $method  The method to extract source from
     * @return string The full method source code, or empty string if file cannot be read
     */
    public function extractSource(\ReflectionMethod $method): string
    {
        $filename = $method->getFileName();
        if ($filename === false || ! file_exists($filename)) {
            return '';
        }

        $lines = file($filename);
        if ($lines === false) {
            return '';
        }

        $startLine = $method->getStartLine() - 1;
        $endLine = $method->getEndLine();
        $length = $endLine - $startLine;

        return implode('', array_slice($lines, $startLine, $length));
    }

    /**
     * Extract only the body of a method (excluding signature and braces).
     *
     * @param  \ReflectionMethod  $method  The method to extract body from
     * @return string The method body, or full source if body extraction fails
     */
    public function extractBody(\ReflectionMethod $method): string
    {
        $source = $this->extractSource($method);
        if ($source === '') {
            return '';
        }

        // Match method body within braces, handling various signature formats
        $pattern = '/function\s+' . preg_quote($method->getName(), '/') . '\s*\([^)]*\)\s*(?::\s*[?\w\\\\|]+\s*)?{(.*)}/s';

        if (preg_match($pattern, $source, $matches)) {
            return $matches[1];
        }

        return $source;
    }
}
