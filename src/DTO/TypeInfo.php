<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents type information inferred from AST nodes.
 *
 * Used by AstTypeInferenceEngine to provide OpenAPI-compatible type information.
 */
final readonly class TypeInfo
{
    /**
     * @param  string  $type  The OpenAPI type (string, integer, number, boolean, array, object, null)
     * @param  array<string, TypeInfo>|null  $properties  For objects, a map of property names to their types
     * @param  string|null  $format  Optional format hint (date-time, email, uri, uuid, etc.)
     */
    public function __construct(
        public string $type,
        public ?array $properties = null,
        public ?string $format = null,
    ) {}

    /**
     * Create a string type.
     */
    public static function string(): self
    {
        return new self(type: 'string');
    }

    /**
     * Create a string type with format.
     */
    public static function stringWithFormat(string $format): self
    {
        return new self(type: 'string', format: $format);
    }

    /**
     * Create an integer type.
     */
    public static function integer(): self
    {
        return new self(type: 'integer');
    }

    /**
     * Create a number type.
     */
    public static function number(): self
    {
        return new self(type: 'number');
    }

    /**
     * Create a boolean type.
     */
    public static function boolean(): self
    {
        return new self(type: 'boolean');
    }

    /**
     * Create an array type.
     */
    public static function array(): self
    {
        return new self(type: 'array');
    }

    /**
     * Create a null type.
     */
    public static function null(): self
    {
        return new self(type: 'null');
    }

    /**
     * Create an object type with optional properties.
     *
     * @param  array<string, TypeInfo>  $properties
     */
    public static function object(array $properties = []): self
    {
        return new self(type: 'object', properties: $properties);
    }

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? 'string';
        $format = $data['format'] ?? null;
        $properties = null;

        if (isset($data['properties']) && is_array($data['properties'])) {
            $properties = [];
            foreach ($data['properties'] as $key => $propertyData) {
                if ($propertyData instanceof self) {
                    $properties[$key] = $propertyData;
                } elseif (is_array($propertyData)) {
                    $properties[$key] = self::fromArray($propertyData);
                }
            }
        }

        return new self(
            type: $type,
            properties: $properties,
            format: $format,
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = ['type' => $this->type];

        if ($this->properties !== null) {
            $result['properties'] = [];
            foreach ($this->properties as $key => $property) {
                $result['properties'][$key] = $property->toArray();
            }
        }

        if ($this->format !== null) {
            $result['format'] = $this->format;
        }

        return $result;
    }

    /**
     * Check if this is an object type.
     */
    public function isObject(): bool
    {
        return $this->type === 'object';
    }

    /**
     * Check if this is an array type.
     */
    public function isArray(): bool
    {
        return $this->type === 'array';
    }

    /**
     * Check if this is a scalar type (string, integer, number, boolean).
     */
    public function isScalar(): bool
    {
        return in_array($this->type, ['string', 'integer', 'number', 'boolean'], true);
    }

    /**
     * Check if this type has a format.
     */
    public function hasFormat(): bool
    {
        return $this->format !== null;
    }

    /**
     * Check if this type has properties.
     */
    public function hasProperties(): bool
    {
        return $this->properties !== null && count($this->properties) > 0;
    }
}
