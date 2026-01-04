<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents an OpenAPI Schema Object.
 *
 * @see https://spec.openapis.org/oas/v3.0.3#schema-object
 *
 * @phpstan-type OpenApiSchemaType array{
 *     type?: string,
 *     format?: string,
 *     title?: string,
 *     description?: string,
 *     default?: mixed,
 *     example?: mixed,
 *     enum?: array<int, mixed>,
 *     nullable?: bool,
 *     properties?: array<string, array<string, mixed>>,
 *     items?: array<string, mixed>,
 *     required?: array<int, string>,
 *     minimum?: int|float,
 *     maximum?: int|float,
 *     minLength?: int,
 *     maxLength?: int,
 *     minItems?: int,
 *     maxItems?: int,
 *     pattern?: string,
 *     allOf?: array<int, array<string, mixed>>,
 *     anyOf?: array<int, array<string, mixed>>,
 *     oneOf?: array<int, array<string, mixed>>,
 *     additionalProperties?: bool|array<string, mixed>,
 *     '\$ref'?: string,
 *     discriminator?: array{propertyName: string, mapping?: array<string, string>}
 * }
 */
final readonly class OpenApiSchema
{
    /**
     * @param  string  $type  The data type (string, integer, number, boolean, array, object)
     * @param  string|null  $format  The format (int32, int64, float, double, date, date-time, etc.)
     * @param  mixed  $default  Default value
     * @param  array<string|int>|null  $enum  Allowed values
     * @param  OpenApiSchema|null  $items  Schema for array items
     * @param  int|null  $minimum  Minimum value for numeric types
     * @param  int|null  $maximum  Maximum value for numeric types
     * @param  int|null  $minLength  Minimum length for string types
     * @param  int|null  $maxLength  Maximum length for string types
     * @param  string|null  $pattern  Regex pattern for string types
     * @param  bool|null  $nullable  Whether the value can be null
     */
    public function __construct(
        public string $type,
        public ?string $format = null,
        public mixed $default = null,
        public ?array $enum = null,
        public ?self $items = null,
        public ?int $minimum = null,
        public ?int $maximum = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public ?string $pattern = null,
        public ?bool $nullable = null,
    ) {}

    /**
     * Create a simple string schema.
     */
    public static function string(?string $default = null): self
    {
        return new self(type: 'string', default: $default);
    }

    /**
     * Create an integer schema.
     */
    public static function integer(?int $default = null): self
    {
        return new self(type: 'integer', default: $default);
    }

    /**
     * Create a number schema.
     */
    public static function number(float|int|null $default = null): self
    {
        return new self(type: 'number', default: $default);
    }

    /**
     * Create a boolean schema.
     */
    public static function boolean(?bool $default = null): self
    {
        return new self(type: 'boolean', default: $default);
    }

    /**
     * Create an array schema with string items.
     *
     * @param  array<string>|null  $default
     */
    public static function stringArray(?array $default = null): self
    {
        return new self(
            type: 'array',
            default: $default,
            items: self::string(),
        );
    }

    /**
     * Create a schema from type string.
     */
    public static function fromType(string $type, mixed $default = null): self
    {
        return match ($type) {
            'integer', 'int' => self::integer($default),
            'number', 'float', 'double' => self::number($default),
            'boolean', 'bool' => self::boolean($default),
            'array' => self::stringArray($default),
            default => self::string($default),
        };
    }

    /**
     * Create from an array (for backward compatibility).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $items = null;
        if (isset($data['items'])) {
            $items = is_array($data['items'])
                ? self::fromArray($data['items'])
                : $data['items'];
        }

        return new self(
            type: $data['type'] ?? 'string',
            format: $data['format'] ?? null,
            default: $data['default'] ?? null,
            enum: $data['enum'] ?? null,
            items: $items,
            minimum: $data['minimum'] ?? null,
            maximum: $data['maximum'] ?? null,
            minLength: $data['minLength'] ?? null,
            maxLength: $data['maxLength'] ?? null,
            pattern: $data['pattern'] ?? null,
            nullable: $data['nullable'] ?? null,
        );
    }

    /**
     * Convert to array for OpenAPI output.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = ['type' => $this->type];

        if ($this->format !== null) {
            $result['format'] = $this->format;
        }

        if ($this->default !== null) {
            $result['default'] = $this->default;
        }

        if ($this->enum !== null) {
            $result['enum'] = $this->enum;
        }

        if ($this->items !== null) {
            $result['items'] = $this->items->toArray();
        }

        if ($this->minimum !== null) {
            $result['minimum'] = $this->minimum;
        }

        if ($this->maximum !== null) {
            $result['maximum'] = $this->maximum;
        }

        if ($this->minLength !== null) {
            $result['minLength'] = $this->minLength;
        }

        if ($this->maxLength !== null) {
            $result['maxLength'] = $this->maxLength;
        }

        if ($this->pattern !== null) {
            $result['pattern'] = $this->pattern;
        }

        if ($this->nullable !== null) {
            $result['nullable'] = $this->nullable;
        }

        return $result;
    }

    /**
     * Create a new instance with enum values.
     *
     * @param  array<string|int>  $enum
     */
    public function withEnum(array $enum): self
    {
        return new self(
            type: $this->type,
            format: $this->format,
            default: $this->default,
            enum: $enum,
            items: $this->items,
            minimum: $this->minimum,
            maximum: $this->maximum,
            minLength: $this->minLength,
            maxLength: $this->maxLength,
            pattern: $this->pattern,
            nullable: $this->nullable,
        );
    }

    /**
     * Create a new instance with constraints.
     */
    public function withConstraints(?int $minimum = null, ?int $maximum = null): self
    {
        return new self(
            type: $this->type,
            format: $this->format,
            default: $this->default,
            enum: $this->enum,
            items: $this->items,
            minimum: $minimum ?? $this->minimum,
            maximum: $maximum ?? $this->maximum,
            minLength: $this->minLength,
            maxLength: $this->maxLength,
            pattern: $this->pattern,
            nullable: $this->nullable,
        );
    }
}
