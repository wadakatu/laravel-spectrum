<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents authentication information for a specific route.
 *
 * This DTO encapsulates the authentication scheme, middleware, and
 * requirement status for a single API route.
 */
final readonly class RouteAuthentication
{
    /**
     * @param  AuthenticationScheme  $scheme  The authentication scheme for this route
     * @param  array<string>  $middleware  Middleware applied to this route
     * @param  bool  $required  Whether authentication is required (default: true)
     */
    public function __construct(
        public AuthenticationScheme $scheme,
        public array $middleware,
        public bool $required = true,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $scheme = $data['scheme'] ?? [];

        return new self(
            scheme: $scheme instanceof AuthenticationScheme
                ? $scheme
                : AuthenticationScheme::fromArray($scheme),
            middleware: $data['middleware'] ?? [],
            required: $data['required'] ?? true,
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
            'scheme' => $this->scheme->toArray(),
            'middleware' => $this->middleware,
            'required' => $this->required,
        ];
    }

    /**
     * Check if authentication is required for this route.
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Get the scheme name.
     */
    public function getSchemeName(): string
    {
        return $this->scheme->name;
    }

    /**
     * Check if this route has a specific middleware.
     */
    public function hasMiddleware(string $middleware): bool
    {
        return in_array($middleware, $this->middleware, true);
    }
}
