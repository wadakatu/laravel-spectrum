<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents the result of analyzing a Fractal Transformer class.
 */
final readonly class FractalTransformerResult
{
    /**
     * @param  array<string, array<string, mixed>>  $properties  Properties extracted from transform() method
     * @param  array<string, array<string, mixed>>  $availableIncludes  Available includes with their configuration
     * @param  array<int, string>  $defaultIncludes  Default includes (automatically loaded)
     * @param  array<string, mixed>  $meta  Meta data (reserved for future use)
     * @param  bool  $isValid  Whether this represents a valid analysis result (vs error/empty result)
     */
    public function __construct(
        public array $properties,
        public array $availableIncludes = [],
        public array $defaultIncludes = [],
        public array $meta = [],
        public string $type = 'fractal',
        public bool $isValid = true,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        // Empty array represents an error/invalid result
        if (empty($data)) {
            return self::empty();
        }

        return new self(
            properties: $data['properties'] ?? [],
            availableIncludes: $data['availableIncludes'] ?? [],
            defaultIncludes: $data['defaultIncludes'] ?? [],
            meta: $data['meta'] ?? [],
            isValid: true,
        );
    }

    /**
     * Convert to array.
     *
     * Returns an empty array if the result is invalid (for backward compatibility).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if (! $this->isValid) {
            return [];
        }

        return [
            'type' => $this->type,
            'properties' => $this->properties,
            'availableIncludes' => $this->availableIncludes,
            'defaultIncludes' => $this->defaultIncludes,
            'meta' => $this->meta,
        ];
    }

    /**
     * Create an empty/invalid result (represents analysis failure).
     */
    public static function empty(): self
    {
        return new self(
            properties: [],
            isValid: false,
        );
    }

    /**
     * Check if this result is empty (no properties).
     */
    public function isEmpty(): bool
    {
        return count($this->properties) === 0;
    }

    /**
     * Check if this result has available includes.
     */
    public function hasAvailableIncludes(): bool
    {
        return count($this->availableIncludes) > 0;
    }

    /**
     * Check if a specific include is available.
     */
    public function hasInclude(string $name): bool
    {
        return isset($this->availableIncludes[$name]);
    }

    /**
     * Check if this result has default includes.
     */
    public function hasDefaultIncludes(): bool
    {
        return count($this->defaultIncludes) > 0;
    }

    /**
     * Check if a specific include is a default include.
     */
    public function isDefaultInclude(string $name): bool
    {
        return in_array($name, $this->defaultIncludes, true);
    }

    /**
     * Get a property by name.
     *
     * @return array<string, mixed>|null
     */
    public function getProperty(string $name): ?array
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * Get an include by name.
     *
     * @return array<string, mixed>|null
     */
    public function getInclude(string $name): ?array
    {
        return $this->availableIncludes[$name] ?? null;
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
}
