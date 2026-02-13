<?php

namespace LaravelSpectrum\Generators;

use Illuminate\Support\Str;

/**
 * Generates OpenAPI operation metadata (summary, operationId).
 *
 * This generator creates human-readable summaries and unique operation IDs
 * for API endpoints based on HTTP method and route information.
 */
class OperationMetadataGenerator
{
    /**
     * Generate a summary for an API operation.
     *
     * Creates human-readable descriptions based on HTTP method and resource name.
     *
     * @param  array{uri: string}  $route  Route information
     * @param  string  $method  HTTP method (get, post, put, patch, delete)
     * @return string Generated summary
     */
    public function generateSummary(array $route, string $method): string
    {
        $normalizedMethod = strtolower($method);
        $resource = $this->extractResourceName($route['uri'], $normalizedMethod);
        $controllerMethod = strtolower((string) ($route['method'] ?? ''));

        return match ($normalizedMethod) {
            'get' => $this->hasTrailingPathParameter($route['uri'])
                ? "Get {$resource} by ID"
                : ($this->isSingularGetEndpoint($route['uri'], $controllerMethod)
                    ? "Get {$resource}"
                    : "List all {$resource}"),
            'post' => "Create a new {$resource}",
            'put', 'patch' => "Update {$resource}",
            'delete' => "Delete {$resource}",
            default => ucfirst($normalizedMethod)." {$resource}",
        };
    }

    /**
     * Generate a unique operation ID for an API endpoint.
     *
     * Uses route name if available, otherwise generates from URI and method.
     *
     * @param  array{uri: string, name?: string}  $route  Route information
     * @param  string  $method  HTTP method
     * @return string Generated operation ID in camelCase
     */
    public function generateOperationId(array $route, string $method): string
    {
        if (! empty($route['name'])) {
            // Replace dots with underscores before converting to camelCase
            return Str::camel(str_replace('.', '_', $route['name']));
        }

        $uri = str_replace(['/', '{', '}', '?'], ['_', '', '', ''], $route['uri']);

        return Str::camel($method.'_'.$uri);
    }

    /**
     * Convert Laravel URI to OpenAPI path format.
     *
     * Converts optional parameters {param?} to required format {param}.
     *
     * @param  string  $uri  Laravel route URI
     * @return string OpenAPI formatted path
     */
    public function convertToOpenApiPath(string $uri): string
    {
        return '/'.preg_replace('/\{([^}]+)\?\}/', '{$1}', $uri);
    }

    /**
     * Extract resource name from URI.
     *
     * Gets the last meaningful segment from the URI, removing parameters.
     * If the last segment is only a parameter, looks at previous segments.
     * Falls back to "Resource" if no meaningful segment is found.
     *
     * @param  string  $uri  Route URI
     * @return string Extracted resource name in StudlyCase
     */
    public function extractResourceName(string $uri, ?string $httpMethod = null): string
    {
        $segments = $this->extractMeaningfulSegments($uri);
        if ($segments === []) {
            return 'Resource';
        }

        $lastSegment = end($segments);
        if ($lastSegment === false) {
            return 'Resource';
        }

        $resource = Str::studly(Str::singular($lastSegment));
        if ($httpMethod === 'post' && $this->shouldIncludeParentContextForPost($segments)) {
            $parentSegment = $segments[count($segments) - 2];
            $resource = Str::studly(Str::singular($parentSegment)).$resource;
        }

        return $resource;
    }

    /**
     * Determine if route ends with a path parameter.
     */
    private function hasTrailingPathParameter(string $uri): bool
    {
        $segments = explode('/', trim($uri, '/'));
        if ($segments === []) {
            return false;
        }

        $lastSegment = end($segments);
        if ($lastSegment === false) {
            return false;
        }

        return preg_match('/^\{[^}]+\??\}$/', $lastSegment) === 1;
    }

    /**
     * Determine whether a GET route likely returns a singular resource.
     */
    private function isSingularGetEndpoint(string $uri, string $controllerMethod): bool
    {
        if ($this->isListLikeControllerMethod($controllerMethod)) {
            return false;
        }

        $segments = $this->extractMeaningfulSegments($uri);
        if ($segments === []) {
            return false;
        }

        $lastSegment = end($segments);
        if ($lastSegment === false) {
            return false;
        }

        return $this->isSingularSegment($lastSegment);
    }

    /**
     * Check if controller method name indicates a list endpoint.
     */
    private function isListLikeControllerMethod(string $controllerMethod): bool
    {
        if ($controllerMethod === '') {
            return false;
        }

        return in_array($controllerMethod, ['index', 'list', 'search', 'browse'], true);
    }

    /**
     * Determine if URI segment appears singular.
     */
    private function isSingularSegment(string $segment): bool
    {
        $normalized = strtolower($segment);
        $singular = strtolower(Str::singular($segment));
        $plural = strtolower(Str::plural($segment));

        return $normalized === $singular && $normalized !== $plural;
    }

    /**
     * Extract static URI segments, excluding API/version markers and parameters.
     *
     * @return array<int, string>
     */
    private function extractMeaningfulSegments(string $uri): array
    {
        $segments = explode('/', trim($uri, '/'));
        $meaningful = [];

        foreach ($segments as $segment) {
            if ($segment === '' || preg_match('/^\{[^}]+\??\}$/', $segment) === 1) {
                continue;
            }

            $cleanSegment = preg_replace('/\\{[^}]+\\??\\}/', '', $segment);
            if ($cleanSegment === null || $cleanSegment === '') {
                continue;
            }

            if ($this->isIgnorableSegment($cleanSegment)) {
                continue;
            }

            $meaningful[] = $cleanSegment;
        }

        return $meaningful;
    }

    /**
     * Determine whether parent segment should be added for POST summaries.
     *
     * @param  array<int, string>  $segments
     */
    private function shouldIncludeParentContextForPost(array $segments): bool
    {
        if (count($segments) < 2) {
            return false;
        }

        $lastSegment = end($segments);
        if ($lastSegment === false) {
            return false;
        }

        return Str::contains($lastSegment, ['-', '_']);
    }

    /**
     * Ignore generic prefix/version segments when inferring resource names.
     */
    private function isIgnorableSegment(string $segment): bool
    {
        $lower = strtolower($segment);

        return $lower === 'api' || preg_match('/^v\d+$/', $lower) === 1;
    }
}
