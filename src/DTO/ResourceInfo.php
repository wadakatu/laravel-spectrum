<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents the result of analyzing an API Resource class.
 */
final readonly class ResourceInfo
{
    /**
     * @param  array<string, array<string, mixed>>  $properties  The resource properties/schema
     * @param  array<string, array<string, mixed>>  $with  Additional data from 'with' method
     * @param  bool  $hasExamples  Whether the resource has example data
     * @param  mixed  $customExample  Custom example data
     * @param  array<int, array<string, mixed>>|null  $customExamples  Multiple custom examples
     * @param  bool  $isCollection  Whether this is a collection resource
     * @param  array<string, mixed>  $conditionalFields  Conditional field definitions
     * @param  array<int, string>  $nestedResources  List of nested resource class names
     */
    public function __construct(
        public array $properties,
        public array $with = [],
        public bool $hasExamples = false,
        public mixed $customExample = null,
        public ?array $customExamples = null,
        public bool $isCollection = false,
        public array $conditionalFields = [],
        public array $nestedResources = [],
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException If data is malformed
     */
    public static function fromArray(array $data): self
    {
        $properties = $data['properties'] ?? [];
        if (! is_array($properties)) {
            throw new \InvalidArgumentException('ResourceInfo properties must be an array');
        }

        $with = $data['with'] ?? [];
        if (! is_array($with)) {
            throw new \InvalidArgumentException('ResourceInfo with must be an array');
        }

        $conditionalFields = $data['conditionalFields'] ?? [];
        if (! is_array($conditionalFields)) {
            throw new \InvalidArgumentException('ResourceInfo conditionalFields must be an array');
        }

        $nestedResources = $data['nestedResources'] ?? [];
        if (! is_array($nestedResources)) {
            throw new \InvalidArgumentException('ResourceInfo nestedResources must be an array');
        }

        return new self(
            properties: $properties,
            with: $with,
            hasExamples: $data['hasExamples'] ?? false,
            customExample: $data['customExample'] ?? null,
            customExamples: $data['customExamples'] ?? null,
            isCollection: $data['isCollection'] ?? false,
            conditionalFields: $conditionalFields,
            nestedResources: $nestedResources,
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'properties' => $this->properties,
        ];

        if (count($this->with) > 0) {
            $result['with'] = $this->with;
        }

        if ($this->hasExamples) {
            $result['hasExamples'] = $this->hasExamples;
        }

        if ($this->customExample !== null) {
            $result['customExample'] = $this->customExample;
        }

        if ($this->customExamples !== null) {
            $result['customExamples'] = $this->customExamples;
        }

        if ($this->isCollection) {
            $result['isCollection'] = $this->isCollection;
        }

        if (count($this->conditionalFields) > 0) {
            $result['conditionalFields'] = $this->conditionalFields;
        }

        if (count($this->nestedResources) > 0) {
            $result['nestedResources'] = $this->nestedResources;
        }

        return $result;
    }

    /**
     * Create an empty resource info.
     */
    public static function empty(): self
    {
        return new self(
            properties: [],
        );
    }

    /**
     * Check if this resource info is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->properties) === 0;
    }

    /**
     * Check if this resource has a custom example.
     */
    public function hasCustomExample(): bool
    {
        return $this->customExample !== null;
    }

    /**
     * Check if this resource has multiple custom examples.
     */
    public function hasCustomExamples(): bool
    {
        return $this->customExamples !== null;
    }

    /**
     * Check if this resource has 'with' data.
     */
    public function hasWithData(): bool
    {
        return count($this->with) > 0;
    }

    /**
     * Get a property by name.
     *
     * @return array<string, mixed>|null
     */
    public function getPropertyByName(string $name): ?array
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * Get all property names.
     *
     * @return array<int, string>
     */
    public function getPropertyNames(): array
    {
        return array_keys($this->properties);
    }

    /**
     * Count the number of properties.
     */
    public function count(): int
    {
        return count($this->properties);
    }

    /**
     * Get all properties including 'with' data.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllProperties(): array
    {
        return array_merge($this->properties, $this->with);
    }
}
