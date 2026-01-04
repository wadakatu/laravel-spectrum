<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents a detected query parameter from controller method analysis.
 *
 * This DTO encapsulates information about how a request parameter is accessed
 * in controller code, including the access method, default value, and context.
 */
final readonly class DetectedQueryParameter
{
    /**
     * Methods that return typed values.
     */
    private const TYPED_METHODS = [
        'boolean', 'bool',
        'integer', 'int',
        'float', 'double',
        'string', 'array', 'date',
    ];

    /**
     * @param  string|int|float|bool|array<array-key, mixed>|null  $default
     * @param  array<string, mixed>  $context
     */
    private function __construct(
        public string $name,
        public string $method,
        public string|int|float|bool|array|null $default,
        public array $context,
    ) {}

    /**
     * Create a new detected query parameter.
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
        return new self(
            name: $name,
            method: $method,
            default: $default,
            context: $context,
        );
    }

    /**
     * Check if the parameter was accessed via magic property access.
     */
    public function isMagicAccess(): bool
    {
        return $this->method === 'magic';
    }

    /**
     * Check if the parameter uses a typed access method.
     */
    public function isTypedMethod(): bool
    {
        return in_array($this->method, self::TYPED_METHODS, true);
    }

    /**
     * Check if the parameter has a default value.
     */
    public function hasDefault(): bool
    {
        return $this->default !== null;
    }

    /**
     * Check if a specific context flag exists.
     */
    public function hasContextFlag(string $flag): bool
    {
        return array_key_exists($flag, $this->context);
    }

    /**
     * Create from an array representation.
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
     * Convert to array format for backward compatibility.
     *
     * @return array{name: string, method: string, default: string|int|float|bool|array<array-key, mixed>|null, context: array<string, mixed>}
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
