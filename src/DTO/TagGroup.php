<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents a tag group for OpenAPI x-tagGroups extension.
 *
 * Tag groups are used by documentation viewers like Redoc to organize
 * API endpoints into logical categories in the sidebar navigation.
 */
final readonly class TagGroup
{
    /**
     * @param  string  $name  The display name of the tag group
     * @param  array<string>  $tags  List of tag names belonging to this group
     */
    public function __construct(
        public string $name,
        public array $tags,
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
            tags: $data['tags'] ?? [],
        );
    }

    /**
     * Convert to array.
     *
     * @return array{name: string, tags: array<string>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'tags' => $this->tags,
        ];
    }

    /**
     * Check if this group has any tags.
     */
    public function hasTags(): bool
    {
        return count($this->tags) > 0;
    }

    /**
     * Get the number of tags in this group.
     */
    public function getTagCount(): int
    {
        return count($this->tags);
    }

    /**
     * Check if this group contains a specific tag.
     */
    public function containsTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }
}
