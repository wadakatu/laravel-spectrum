<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents the result of authentication analysis across all routes.
 *
 * This DTO encapsulates the collection of authentication schemes used
 * and the per-route authentication information.
 */
final readonly class AuthenticationResult
{
    /**
     * @param  array<string, AuthenticationScheme>  $schemes  Map of scheme name to scheme definition
     * @param  array<int, RouteAuthentication>  $routes  Map of route index to route authentication
     */
    public function __construct(
        public array $schemes,
        public array $routes,
    ) {}

    /**
     * Create an empty result.
     */
    public static function empty(): self
    {
        return new self(schemes: [], routes: []);
    }

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $schemes = [];
        foreach ($data['schemes'] ?? [] as $name => $scheme) {
            $schemes[$name] = $scheme instanceof AuthenticationScheme
                ? $scheme
                : AuthenticationScheme::fromArray($scheme);
        }

        $routes = [];
        foreach ($data['routes'] ?? [] as $index => $route) {
            $routes[$index] = $route instanceof RouteAuthentication
                ? $route
                : RouteAuthentication::fromArray($route);
        }

        return new self(
            schemes: $schemes,
            routes: $routes,
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $schemes = [];
        foreach ($this->schemes as $name => $scheme) {
            $schemes[$name] = $scheme->toArray();
        }

        $routes = [];
        foreach ($this->routes as $index => $route) {
            $routes[$index] = $route->toArray();
        }

        return [
            'schemes' => $schemes,
            'routes' => $routes,
        ];
    }

    /**
     * Check if this result is empty (no schemes and no routes).
     */
    public function isEmpty(): bool
    {
        return empty($this->schemes) && empty($this->routes);
    }

    /**
     * Check if there are any authentication schemes.
     */
    public function hasSchemes(): bool
    {
        return ! empty($this->schemes);
    }

    /**
     * Get a scheme by name.
     */
    public function getScheme(string $name): ?AuthenticationScheme
    {
        return $this->schemes[$name] ?? null;
    }

    /**
     * Get route authentication by route index.
     */
    public function getRouteAuthentication(int $index): ?RouteAuthentication
    {
        return $this->routes[$index] ?? null;
    }

    /**
     * Check if a route has authentication.
     */
    public function hasRouteAuthentication(int $index): bool
    {
        return isset($this->routes[$index]);
    }

    /**
     * Count the number of authentication schemes.
     */
    public function countSchemes(): int
    {
        return count($this->schemes);
    }

    /**
     * Count the number of authenticated routes.
     */
    public function countAuthenticatedRoutes(): int
    {
        return count($this->routes);
    }

    /**
     * Get all scheme names.
     *
     * @return array<string>
     */
    public function getSchemeNames(): array
    {
        return array_keys($this->schemes);
    }
}
