<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents an enum parameter detected from controller method signature.
 */
final readonly class EnumParameterInfo
{
    /**
     * @param  string  $name  The parameter name
     * @param  string  $type  The OpenAPI type ('string' or 'integer')
     * @param  array<int, string|int>  $enum  The enum values
     * @param  bool  $required  Whether the parameter is required
     * @param  string  $description  Human-readable description
     * @param  string  $in  Where the parameter is located ('path', 'query')
     * @param  string  $enumClass  The PHP enum class name
     */
    public function __construct(
        public string $name,
        public string $type,
        public array $enum,
        public bool $required = true,
        public string $description = '',
        public string $in = 'path',
        public string $enumClass = '',
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
            type: $data['type'] ?? 'string',
            enum: $data['enum'] ?? [],
            required: $data['required'] ?? true,
            description: $data['description'] ?? '',
            in: $data['in'] ?? 'path',
            enumClass: $data['enumClass'] ?? '',
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
            'type' => $this->type,
            'enum' => $this->enum,
            'required' => $this->required,
            'description' => $this->description,
            'in' => $this->in,
            'enumClass' => $this->enumClass,
        ];
    }

    /**
     * Check if this is a path parameter.
     */
    public function isPathParameter(): bool
    {
        return $this->in === 'path';
    }

    /**
     * Check if this is a query parameter.
     */
    public function isQueryParameter(): bool
    {
        return $this->in === 'query';
    }

    /**
     * Check if this is a string-backed enum.
     */
    public function isStringBacked(): bool
    {
        return $this->type === 'string';
    }

    /**
     * Check if this is an integer-backed enum.
     */
    public function isIntegerBacked(): bool
    {
        return $this->type === 'integer';
    }
}
