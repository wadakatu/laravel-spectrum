<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents a tag definition for OpenAPI tags section.
 *
 * Tag definitions provide metadata about tags used in the API,
 * including optional descriptions that appear in documentation.
 */
final readonly class TagDefinition
{
    /**
     * @param  string  $name  The tag name (must match tags used in operations)
     * @param  string|null  $description  Optional human-readable description
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            description: $data['description'] ?? null,
        );
    }

    /**
     * Convert to array.
     *
     * Returns an array with 'name' key always present.
     * The 'description' key is only included if description is not null and not empty.
     *
     * @return array{name: string, description?: string}
     */
    public function toArray(): array
    {
        $result = ['name' => $this->name];

        if ($this->hasDescription()) {
            $result['description'] = $this->description;
        }

        return $result;
    }

    /**
     * Check if this tag definition has a description.
     */
    public function hasDescription(): bool
    {
        return $this->description !== null && $this->description !== '';
    }
}
