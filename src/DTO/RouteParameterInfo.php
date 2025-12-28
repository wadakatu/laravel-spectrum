<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents a route parameter extracted from URI.
 */
final readonly class RouteParameterInfo
{
    /**
     * @param  string  $name  The parameter name
     * @param  bool  $required  Whether the parameter is required
     * @param  string  $in  The parameter location (always 'path' for route parameters)
     * @param  array<string, mixed>  $schema  The OpenAPI schema for the parameter
     */
    public function __construct(
        public string $name,
        public bool $required = true,
        public string $in = 'path',
        public array $schema = ['type' => 'string'],
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            required: $data['required'] ?? true,
            in: $data['in'] ?? 'path',
            schema: $data['schema'] ?? ['type' => 'string'],
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
            'name' => $this->name,
            'required' => $this->required,
            'in' => $this->in,
            'schema' => $this->schema,
        ];
    }

    /**
     * Check if this parameter is optional.
     */
    public function isOptional(): bool
    {
        return ! $this->required;
    }

    /**
     * Get the schema type if defined.
     */
    public function getSchemaType(): ?string
    {
        return $this->schema['type'] ?? null;
    }
}
