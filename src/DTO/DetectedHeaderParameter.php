<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * DTO representing a detected header access in controller code.
 * This is used internally by the header detector before conversion to HeaderParameterInfo.
 */
final readonly class DetectedHeaderParameter
{
    /**
     * @param  string  $name  The header name
     * @param  string  $method  The method used (header, hasHeader, bearerToken)
     * @param  string|int|float|bool|array<array-key, mixed>|null  $default  Default value if any
     * @param  array<string, mixed>  $context  Additional context from analysis
     */
    public function __construct(
        public string $name,
        public string $method,
        public string|int|float|bool|array|null $default = null,
        public array $context = [],
    ) {}

    /**
     * Factory method for creating a detected header parameter.
     *
     * @param  string|int|float|bool|array<array-key, mixed>|null  $default
     * @param  array<string, mixed>  $context
     */
    public static function create(
        string $name,
        string $method,
        string|int|float|bool|array|null $default = null,
        array $context = [],
    ): self {
        return new self($name, $method, $default, $context);
    }

    /**
     * Check if this is a bearer token access.
     */
    public function isBearerToken(): bool
    {
        return $this->method === 'bearerToken';
    }

    /**
     * Check if this is a hasHeader() check.
     */
    public function isHasHeaderCheck(): bool
    {
        return $this->method === 'hasHeader';
    }

    /**
     * Check if a default value is set.
     */
    public function hasDefault(): bool
    {
        return $this->default !== null;
    }

    /**
     * Check if a context flag exists.
     */
    public function hasContextFlag(string $flag): bool
    {
        return isset($this->context[$flag]) && $this->context[$flag] === true;
    }

    /**
     * Create from array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            method: $data['method'],
            default: $data['default'] ?? null,
            context: $data['context'] ?? [],
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
            'method' => $this->method,
            'default' => $this->default,
            'context' => $this->context,
        ];
    }
}
