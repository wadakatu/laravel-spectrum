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
            $this->logDebug('Cannot read method source: file not found or internal method', [
                'class' => $method->getDeclaringClass()->getName(),
                'method' => $method->getName(),
                'filename' => $filename,
            ]);

            return '';
        }

        $lines = file($filename);
        if ($lines === false) {
            $this->logDebug('Cannot read method source: file read failed', [
                'class' => $method->getDeclaringClass()->getName(),
                'method' => $method->getName(),
                'filename' => $filename,
            ]);

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
        $pattern = '/function\s+'.preg_quote($method->getName(), '/').'\s*\([^)]*\)\s*(?::\s*[?\w\\\\|]+\s*)?{(.*)}/s';

        if (preg_match($pattern, $source, $matches)) {
            return $matches[1];
        }

        $this->logDebug('Regex body extraction failed, returning full source', [
            'class' => $method->getDeclaringClass()->getName(),
            'method' => $method->getName(),
        ]);

        return $source;
    }

    /**
     * Log a debug message if the Laravel logger is available.
     *
     * @param  string  $message  The message to log
     * @param  array<string, mixed>  $context  Additional context data
     */
    private function logDebug(string $message, array $context = []): void
    {
        if (function_exists('logger')) {
            logger()->debug("[MethodSourceExtractor] {$message}", $context);
        }
    }
}
