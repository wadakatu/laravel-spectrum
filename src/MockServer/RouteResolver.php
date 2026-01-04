<?php

namespace LaravelSpectrum\MockServer;

use Illuminate\Support\Str;
use LaravelSpectrum\DTO\OpenApiOperation;

/**
 * @phpstan-import-type RouteDefinition from OpenApiOperation
 */
class RouteResolver
{
    /**
     * Resolve a request path and method to an OpenAPI route.
     *
     * @param  array<string, mixed>  $openapi
     * @return RouteDefinition|null
     */
    public function resolve(string $path, string $method, array $openapi): ?array
    {
        $method = strtolower($method);

        // Remove query string
        $pathWithoutQuery = parse_url($path, PHP_URL_PATH) ?? $path;

        // Remove trailing slash
        $pathWithoutQuery = rtrim($pathWithoutQuery, '/');
        if ($pathWithoutQuery === '') {
            $pathWithoutQuery = '/';
        }

        $paths = $openapi['paths'] ?? [];

        // First, try exact match
        if (isset($paths[$pathWithoutQuery][$method])) {
            return [
                'path' => $pathWithoutQuery,
                'method' => $method,
                'operation' => $paths[$pathWithoutQuery][$method],
                'params' => [
                    '_path' => $pathWithoutQuery,
                ],
            ];
        }

        // Then, try parameterized routes
        $matches = [];

        foreach ($paths as $pathPattern => $methods) {
            if (! isset($methods[$method])) {
                continue;
            }

            // Skip if this is an exact match (already handled above)
            if ($pathPattern === $pathWithoutQuery) {
                continue;
            }

            // Convert OpenAPI path to regex
            $regex = $this->convertPathToRegex($pathPattern);

            if (preg_match($regex, $pathWithoutQuery, $paramMatches)) {
                // Extract parameter names
                preg_match_all('/\{([^}]+)\}/', $pathPattern, $paramNames);

                $params = ['_path' => $pathWithoutQuery];
                foreach ($paramNames[1] as $index => $paramName) {
                    if (isset($paramMatches[$index + 1])) {
                        $params[$paramName] = urldecode($paramMatches[$index + 1]);
                    }
                }

                $matches[] = [
                    'path' => $pathPattern,
                    'method' => $method,
                    'operation' => $methods[$method],
                    'params' => $params,
                    'specificity' => $this->calculateSpecificity($pathPattern),
                ];
            }
        }

        if (empty($matches)) {
            return null;
        }

        // Sort by specificity (more specific routes first)
        usort($matches, function ($a, $b) {
            return $b['specificity'] - $a['specificity'];
        });

        // Remove specificity from result
        $result = $matches[0];
        unset($result['specificity']);

        return $result;
    }

    /**
     * Convert OpenAPI path pattern to regex
     */
    private function convertPathToRegex(string $pathPattern): string
    {
        // Start with the pattern
        $regex = $pathPattern;

        // Escape special regex characters except braces
        $regex = str_replace(
            ['/', '.', '*', '+', '?', '^', '$', '(', ')', '[', ']', '|'],
            ['\/', '\.', '\*', '\+', '\?', '\^', '\$', '\(', '\)', '\[', '\]', '\|'],
            $regex
        );

        // Replace {param} with named capture groups
        $regex = preg_replace('/\{([^}]+)\}/', '([^\/]+)', $regex);

        return '/^'.$regex.'$/';
    }

    /**
     * Calculate route specificity (higher number = more specific)
     */
    private function calculateSpecificity(string $pathPattern): int
    {
        // Count static segments (more static = more specific)
        $segments = explode('/', trim($pathPattern, '/'));
        $staticSegments = 0;

        foreach ($segments as $segment) {
            if (! Str::contains($segment, '{')) {
                $staticSegments++;
            }
        }

        // Also consider total length (longer paths are more specific)
        return ($staticSegments * 100) + strlen($pathPattern);
    }
}
