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
        $resource = $this->extractResourceName($route['uri']);

        return match ($method) {
            'get' => Str::contains($route['uri'], '{')
                ? "Get {$resource} by ID"
                : "List all {$resource}",
            'post' => "Create a new {$resource}",
            'put', 'patch' => "Update {$resource}",
            'delete' => "Delete {$resource}",
            default => ucfirst($method)." {$resource}",
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
     *
     * @param  string  $uri  Route URI
     * @return string Extracted resource name in StudlyCase
     */
    public function extractResourceName(string $uri): string
    {
        $segments = explode('/', trim($uri, '/'));

        // Iterate from last to first to find a meaningful resource name
        for ($i = count($segments) - 1; $i >= 0; $i--) {
            $segment = $segments[$i];

            // Remove parameters from segment
            $cleanSegment = preg_replace('/\\{[^}]+\\}/', '', $segment);

            if (! empty($cleanSegment)) {
                return Str::studly(Str::singular($cleanSegment));
            }
        }

        return '';
    }
}
