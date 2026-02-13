<?php

namespace LaravelSpectrum\Generators;

use Illuminate\Support\Str;

/**
 * Generates OpenAPI tags from route information.
 *
 * Tags are used to group related API operations in the documentation.
 * This generator supports:
 * - Custom tag mappings via configuration
 * - Controller-based tag generation (default)
 * - URI-based fallback tagging when controller tags are unavailable
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

        // Prefer controller-based tag for more meaningful resource grouping
        if (isset($route['controller'])) {
            $controllerTags = $this->generateFromController($route['controller']);
            if (! empty($controllerTags)) {
                return array_values(array_unique($controllerTags));
            }
        }

        // Fallback to URI-based tags
        $tags = $this->generateFromUri($route['uri']);

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
     * Generate tags from URI segments (fallback strategy).
     *
     * Filters out common prefixes (api, v1, v2, etc.) and route parameters.
     * Only the first N meaningful segments are used, where N is controlled by
     * the `spectrum.tag_depth` configuration (default: 1).
     *
     * @param  string  $uri  The route URI
     * @return array<string> Generated tags
     */
    protected function generateFromUri(string $uri): array
    {
        $segments = explode('/', trim($uri, '/'));

        // Filter out common prefixes
        $segments = array_values(array_filter(
            $segments,
            fn (string $segment): bool => ! $this->isIgnoredPrefix($segment)
        ));

        // Normalize to meaningful segments only (strip route parameters)
        $meaningfulSegments = [];
        foreach ($segments as $segment) {
            // Skip route parameters ({param} format)
            if (preg_match('/^\{[^}]+\}$/', $segment)) {
                continue;
            }

            // Extract name from segments containing parameters
            $cleanSegment = preg_replace('/\{[^}]+\}/', '', $segment);
            if (! empty($cleanSegment)) {
                $meaningfulSegments[] = $cleanSegment;
            }
        }

        $tagDepth = $this->resolveTagDepth();
        if ($tagDepth === 0) {
            return [];
        }

        $meaningfulSegments = array_slice($meaningfulSegments, 0, $tagDepth);

        return array_map(
            fn (string $segment): string => Str::studly(Str::singular($segment)),
            $meaningfulSegments
        );
    }

    /**
     * Resolve configured tag depth.
     *
     * 0 disables URI-based tag generation and falls back to controller-based tags.
     */
    protected function resolveTagDepth(): int
    {
        $depth = config('spectrum.tag_depth', 1);

        if (! is_int($depth) && ! is_numeric($depth)) {
            return 1;
        }

        $depth = (int) $depth;

        return $depth >= 0 ? $depth : 1;
    }

    /**
     * Determine whether the segment should be ignored as API prefix/version.
     */
    protected function isIgnoredPrefix(string $segment): bool
    {
        if (strtolower($segment) === 'api') {
            return true;
        }

        return preg_match('/^v\d+$/i', $segment) === 1;
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
