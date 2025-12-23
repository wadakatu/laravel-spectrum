<?php

namespace LaravelSpectrum\Generators;

use Illuminate\Support\Str;

/**
 * Generates OpenAPI tags from route information.
 *
 * Tags are used to group related API operations in the documentation.
 * This generator supports:
 * - Custom tag mappings via configuration
 * - Automatic tag extraction from URI segments
 * - Controller name fallback when URI-based tags are unavailable
 */
class TagGenerator
{
    /**
     * Generate tags for an API route.
     *
     * @param  array{uri: string, controller?: string}  $route  Route information
     * @return array<string> List of tags
     */
    public function generate(array $route): array
    {
        // Check custom mappings from configuration
        $customTag = $this->getCustomTag($route['uri']);
        if ($customTag !== null) {
            return (array) $customTag;
        }

        // Generate tags from URI segments
        $tags = $this->generateFromUri($route['uri']);

        // Fallback to controller name if no tags found
        if (empty($tags) && isset($route['controller'])) {
            $tags = $this->generateFromController($route['controller']);
        }

        return array_values(array_unique($tags));
    }

    /**
     * Get custom tag from configuration if exists.
     *
     * Supports both exact matches and wildcard patterns.
     *
     * @param  string  $uri  The route URI
     * @return string|array<string>|null Custom tag(s) or null if not found
     */
    protected function getCustomTag(string $uri): string|array|null
    {
        $customMappings = config('spectrum.tags', []);

        // Check exact match first
        if (isset($customMappings[$uri])) {
            return $customMappings[$uri];
        }

        // Check wildcard patterns
        foreach ($customMappings as $pattern => $tag) {
            if (Str::is($pattern, $uri)) {
                return $tag;
            }
        }

        return null;
    }

    /**
     * Generate tags from URI segments.
     *
     * Filters out common prefixes (api, v1, v2, etc.) and route parameters.
     *
     * @param  string  $uri  The route URI
     * @return array<string> Generated tags
     */
    protected function generateFromUri(string $uri): array
    {
        $segments = explode('/', trim($uri, '/'));
        $tags = [];

        // Filter out common prefixes
        $ignorePrefixes = ['api', 'v1', 'v2', 'v3'];
        $segments = array_values(array_filter($segments, function ($segment) use ($ignorePrefixes) {
            return ! in_array($segment, $ignorePrefixes);
        }));

        foreach ($segments as $segment) {
            // Skip route parameters ({param} format)
            if (preg_match('/^\{[^}]+\}$/', $segment)) {
                continue;
            }

            // Extract name from segments containing parameters
            $cleanSegment = preg_replace('/\{[^}]+\}/', '', $segment);
            if (! empty($cleanSegment)) {
                $tags[] = Str::studly(Str::singular($cleanSegment));
            }
        }

        return $tags;
    }

    /**
     * Generate tag from controller class name.
     *
     * @param  string  $controller  Controller class name
     * @return array<string> Generated tags
     */
    protected function generateFromController(string $controller): array
    {
        $controllerName = class_basename($controller);
        $controllerName = str_replace('Controller', '', $controllerName);

        if (empty($controllerName)) {
            return [];
        }

        return [Str::studly(Str::singular($controllerName))];
    }
}
