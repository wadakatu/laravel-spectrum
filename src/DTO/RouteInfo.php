<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents route information extracted from Laravel's router.
 */
final readonly class RouteInfo
{
    /**
     * @param  string  $uri  The route URI pattern
     * @param  array<int, string>  $httpMethods  The HTTP methods (GET, POST, etc.)
     * @param  string|null  $controller  The controller class name
     * @param  string|null  $method  The controller method name
     * @param  string|null  $name  The route name
     * @param  array<int, string>  $middleware  The middleware stack
     * @param  array<int, RouteParameterInfo>  $parameters  The route parameters
     */
    public function __construct(
        public string $uri,
        public array $httpMethods,
        public ?string $controller = null,
        public ?string $method = null,
        public ?string $name = null,
        public array $middleware = [],
        public array $parameters = [],
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $parameters = [];
        foreach ($data['parameters'] ?? [] as $param) {
            $parameters[] = $param instanceof RouteParameterInfo
                ? $param
                : RouteParameterInfo::fromArray($param);
        }

        return new self(
            uri: $data['uri'],
            httpMethods: $data['httpMethods'],
            controller: $data['controller'] ?? null,
            method: $data['method'] ?? null,
            name: $data['name'] ?? null,
            middleware: $data['middleware'] ?? [],
            parameters: $parameters,
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uri' => $this->uri,
            'httpMethods' => $this->httpMethods,
            'controller' => $this->controller,
            'method' => $this->method,
            'name' => $this->name,
            'middleware' => $this->middleware,
            'parameters' => array_map(fn (RouteParameterInfo $p) => $p->toArray(), $this->parameters),
        ];
    }

    /**
     * Check if a controller is assigned to this route.
     */
    public function hasController(): bool
    {
        return $this->controller !== null;
    }

    /**
     * Get the full action string (Controller@method).
     */
    public function getFullAction(): ?string
    {
        if ($this->controller === null || $this->method === null) {
            return null;
        }

        return $this->controller.'@'.$this->method;
    }

    /**
     * Check if the route has a specific middleware (exact match).
     */
    public function hasMiddleware(string $middleware): bool
    {
        return in_array($middleware, $this->middleware, true);
    }

    /**
     * Check if the route has middleware starting with a given prefix.
     */
    public function hasMiddlewareStartingWith(string $prefix): bool
    {
        foreach ($this->middleware as $mw) {
            if (str_starts_with($mw, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the route has a name.
     */
    public function hasName(): bool
    {
        return $this->name !== null && $this->name !== '';
    }

    /**
     * Check if the route has parameters.
     */
    public function hasParameters(): bool
    {
        return count($this->parameters) > 0;
    }

    /**
     * Get the primary HTTP method (first in the list).
     *
     * @throws \LogicException If httpMethods array is empty
     */
    public function getPrimaryMethod(): string
    {
        if (count($this->httpMethods) === 0) {
            throw new \LogicException('RouteInfo must have at least one HTTP method');
        }

        return $this->httpMethods[0];
    }

    /**
     * Check if this route responds to only one HTTP method.
     */
    public function isSingleMethod(): bool
    {
        return count($this->httpMethods) === 1;
    }

    /**
     * Generate a unique route identifier.
     */
    public function getRouteId(): string
    {
        return 'route:'.implode(':', $this->httpMethods).':'.$this->uri;
    }

    /**
     * Convert URI to OpenAPI path format (with leading slash).
     */
    public function toOpenApiPath(): string
    {
        return str_starts_with($this->uri, '/') ? $this->uri : '/'.$this->uri;
    }
}
