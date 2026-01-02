<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents type information for a field in an API Resource.
 *
 * This DTO encapsulates the type, nullability, source, and other metadata
 * extracted from API Resource classes by ResourceStructureVisitor.
 * It supports conditional fields (when/whenLoaded), nested resources,
 * and resource collections.
 */
final readonly class ResourceFieldInfo
{
    /**
     * @param  string  $type  The OpenAPI type (mixed, string, integer, number, boolean, array, object)
     * @param  bool  $nullable  Whether the field can be null
     * @param  string|null  $source  Source of the value (property, enum)
     * @param  string|null  $property  Property name when source is 'property'
     * @param  mixed  $example  Example value
     * @param  string|null  $format  Format hint (date-time, etc.)
     * @param  bool  $conditional  Whether this is a conditional field
     * @param  string|null  $condition  Condition type (when, whenLoaded)
     * @param  string|null  $relation  Relation name for whenLoaded
     * @param  bool  $hasTransformation  Whether whenLoaded has transformation closure
     * @param  array<string, mixed>|null  $properties  Nested properties for object types
     * @param  array<string, mixed>|null  $items  Item definition for array types
     * @param  string|null  $resource  Referenced resource class name
     * @param  string|null  $expression  Expression string for complex values
     */
    private function __construct(
        public string $type,
        public bool $nullable = false,
        public ?string $source = null,
        public ?string $property = null,
        public mixed $example = null,
        public ?string $format = null,
        public bool $conditional = false,
        public ?string $condition = null,
        public ?string $relation = null,
        public bool $hasTransformation = false,
        public ?array $properties = null,
        public ?array $items = null,
        public ?string $resource = null,
        public ?string $expression = null,
    ) {}

    /**
     * Create a basic field info with just the type.
     */
    public static function basic(string $type): self
    {
        return new self(type: $type);
    }

    /**
     * Create a mixed type field.
     */
    public static function mixed(): self
    {
        return new self(type: 'mixed');
    }

    /**
     * Create a string type field.
     */
    public static function string(): self
    {
        return new self(type: 'string');
    }

    /**
     * Create an integer type field.
     */
    public static function integer(): self
    {
        return new self(type: 'integer');
    }

    /**
     * Create a number type field.
     */
    public static function number(): self
    {
        return new self(type: 'number');
    }

    /**
     * Create a boolean type field.
     */
    public static function boolean(): self
    {
        return new self(type: 'boolean');
    }

    /**
     * Create an array type field.
     */
    public static function array(): self
    {
        return new self(type: 'array');
    }

    /**
     * Create an object type field.
     */
    public static function object(): self
    {
        return new self(type: 'object');
    }

    /**
     * Create a property-sourced field.
     */
    public static function property(string $propertyName, string $type, mixed $example = null): self
    {
        return new self(
            type: $type,
            source: 'property',
            property: $propertyName,
            example: $example,
        );
    }

    /**
     * Create an enum-sourced field.
     */
    public static function enum(bool $nullable = false): self
    {
        return new self(
            type: 'string',
            nullable: $nullable,
            source: 'enum',
        );
    }

    /**
     * Create a conditional field.
     */
    public static function conditional(string $condition, string $type): self
    {
        return new self(
            type: $type,
            conditional: true,
            condition: $condition,
        );
    }

    /**
     * Create a whenLoaded conditional field.
     */
    public static function whenLoaded(string $relation, string $type, bool $hasTransformation = false): self
    {
        return new self(
            type: $type,
            conditional: true,
            condition: 'whenLoaded',
            relation: $relation,
            hasTransformation: $hasTransformation,
        );
    }

    /**
     * Create a whenCounted conditional field.
     * Returns integer type since counts are always integers.
     */
    public static function whenCounted(string $relation): self
    {
        return new self(
            type: 'integer',
            conditional: true,
            condition: 'whenCounted',
            relation: $relation,
        );
    }

    /**
     * Create a whenAggregated conditional field.
     * Returns number type since aggregates can be floats (avg, sum, etc.).
     */
    public static function whenAggregated(string $relation): self
    {
        return new self(
            type: 'number',
            conditional: true,
            condition: 'whenAggregated',
            relation: $relation,
        );
    }

    /**
     * Create a resource collection field.
     *
     * @param  array<string, mixed>|null  $items
     */
    public static function resourceCollection(string $resourceClass, ?array $items = null): self
    {
        return new self(
            type: 'array',
            items: $items,
            resource: $resourceClass,
        );
    }

    /**
     * Create a nested resource field.
     */
    public static function nestedResource(string $resourceClass): self
    {
        return new self(
            type: 'object',
            resource: $resourceClass,
        );
    }

    /**
     * Create a nested resource field with whenLoaded condition.
     */
    public static function conditionalNestedResource(
        string $resourceClass,
        string $relation,
        bool $hasTransformation = false,
    ): self {
        return new self(
            type: 'object',
            conditional: true,
            condition: 'whenLoaded',
            relation: $relation,
            hasTransformation: $hasTransformation,
            resource: $resourceClass,
        );
    }

    /**
     * Create a resource collection with whenLoaded condition.
     *
     * @param  array<string, mixed>|null  $items
     */
    public static function conditionalResourceCollection(
        string $resourceClass,
        string $relation,
        ?array $items = null,
        bool $hasTransformation = false,
    ): self {
        return new self(
            type: 'array',
            conditional: true,
            condition: 'whenLoaded',
            relation: $relation,
            hasTransformation: $hasTransformation,
            items: $items,
            resource: $resourceClass,
        );
    }

    /**
     * Create a date-time field.
     */
    public static function dateTime(?string $example = null): self
    {
        return new self(
            type: 'string',
            example: $example,
            format: 'date-time',
        );
    }

    /**
     * Create a field with an expression.
     */
    public static function withExpression(string $expression): self
    {
        return new self(
            type: 'mixed',
            expression: $expression,
        );
    }

    /**
     * Create a copy with nullable set to true.
     */
    public function withNullable(): self
    {
        return new self(
            type: $this->type,
            nullable: true,
            source: $this->source,
            property: $this->property,
            example: $this->example,
            format: $this->format,
            conditional: $this->conditional,
            condition: $this->condition,
            relation: $this->relation,
            hasTransformation: $this->hasTransformation,
            properties: $this->properties,
            items: $this->items,
            resource: $this->resource,
            expression: $this->expression,
        );
    }

    /**
     * Create a copy with conditional set to true and a condition type.
     */
    public function withConditional(string $condition): self
    {
        return new self(
            type: $this->type,
            nullable: $this->nullable,
            source: $this->source,
            property: $this->property,
            example: $this->example,
            format: $this->format,
            conditional: true,
            condition: $condition,
            relation: $this->relation,
            hasTransformation: $this->hasTransformation,
            properties: $this->properties,
            items: $this->items,
            resource: $this->resource,
            expression: $this->expression,
        );
    }

    /**
     * Create a copy with properties.
     *
     * @param  array<string, mixed>  $properties
     */
    public function withProperties(array $properties): self
    {
        return new self(
            type: $this->type,
            nullable: $this->nullable,
            source: $this->source,
            property: $this->property,
            example: $this->example,
            format: $this->format,
            conditional: $this->conditional,
            condition: $this->condition,
            relation: $this->relation,
            hasTransformation: $this->hasTransformation,
            properties: $properties,
            items: $this->items,
            resource: $this->resource,
            expression: $this->expression,
        );
    }

    /**
     * Create a copy with items.
     *
     * @param  array<string, mixed>  $items
     */
    public function withItems(array $items): self
    {
        return new self(
            type: $this->type,
            nullable: $this->nullable,
            source: $this->source,
            property: $this->property,
            example: $this->example,
            format: $this->format,
            conditional: $this->conditional,
            condition: $this->condition,
            relation: $this->relation,
            hasTransformation: $this->hasTransformation,
            properties: $this->properties,
            items: $items,
            resource: $this->resource,
            expression: $this->expression,
        );
    }

    /**
     * Create a copy with an example.
     */
    public function withExample(mixed $example): self
    {
        return new self(
            type: $this->type,
            nullable: $this->nullable,
            source: $this->source,
            property: $this->property,
            example: $example,
            format: $this->format,
            conditional: $this->conditional,
            condition: $this->condition,
            relation: $this->relation,
            hasTransformation: $this->hasTransformation,
            properties: $this->properties,
            items: $this->items,
            resource: $this->resource,
            expression: $this->expression,
        );
    }

    /**
     * Check if this is a mixed type.
     */
    public function isMixed(): bool
    {
        return $this->type === 'mixed';
    }

    /**
     * Check if this is a string type.
     */
    public function isString(): bool
    {
        return $this->type === 'string';
    }

    /**
     * Check if this is an integer type.
     */
    public function isInteger(): bool
    {
        return $this->type === 'integer';
    }

    /**
     * Check if this is a number type.
     */
    public function isNumber(): bool
    {
        return $this->type === 'number';
    }

    /**
     * Check if this is a boolean type.
     */
    public function isBoolean(): bool
    {
        return $this->type === 'boolean';
    }

    /**
     * Check if this is an array type.
     */
    public function isArray(): bool
    {
        return $this->type === 'array';
    }

    /**
     * Check if this is an object type.
     */
    public function isObject(): bool
    {
        return $this->type === 'object';
    }

    /**
     * Check if this is a scalar type (string, integer, number, boolean).
     */
    public function isScalar(): bool
    {
        return in_array($this->type, ['string', 'integer', 'number', 'boolean'], true);
    }

    /**
     * Check if this field is nullable.
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * Check if this is a conditional field.
     */
    public function isConditional(): bool
    {
        return $this->conditional;
    }

    /**
     * Check if this field references another resource.
     */
    public function isResourceReference(): bool
    {
        return $this->resource !== null;
    }

    /**
     * Check if this field has a format.
     */
    public function hasFormat(): bool
    {
        return $this->format !== null;
    }

    /**
     * Check if this field has properties (for object types).
     */
    public function hasProperties(): bool
    {
        return $this->properties !== null && count($this->properties) > 0;
    }

    /**
     * Convert to array format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
            'nullable' => $this->nullable,
        ];

        if ($this->source !== null) {
            $result['source'] = $this->source;
        }

        if ($this->property !== null) {
            $result['property'] = $this->property;
        }

        if ($this->example !== null) {
            $result['example'] = $this->example;
        }

        if ($this->format !== null) {
            $result['format'] = $this->format;
        }

        if ($this->conditional) {
            $result['conditional'] = $this->conditional;
        }

        if ($this->condition !== null) {
            $result['condition'] = $this->condition;
        }

        if ($this->relation !== null) {
            $result['relation'] = $this->relation;
        }

        if ($this->hasTransformation) {
            $result['hasTransformation'] = $this->hasTransformation;
        }

        if ($this->properties !== null) {
            $result['properties'] = $this->properties;
        }

        if ($this->items !== null) {
            $result['items'] = $this->items;
        }

        if ($this->resource !== null) {
            $result['resource'] = $this->resource;
        }

        if ($this->expression !== null) {
            $result['expression'] = $this->expression;
        }

        return $result;
    }

    /**
     * Create from array representation.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'] ?? 'mixed',
            nullable: $data['nullable'] ?? false,
            source: $data['source'] ?? null,
            property: $data['property'] ?? null,
            example: $data['example'] ?? null,
            format: $data['format'] ?? null,
            conditional: $data['conditional'] ?? false,
            condition: $data['condition'] ?? null,
            relation: $data['relation'] ?? null,
            hasTransformation: $data['hasTransformation'] ?? false,
            properties: $data['properties'] ?? null,
            items: $data['items'] ?? null,
            resource: $data['resource'] ?? null,
            expression: $data['expression'] ?? null,
        );
    }
}
