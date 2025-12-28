<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents image dimension constraints for file upload validation.
 */
final readonly class FileDimensions
{
    public function __construct(
        public ?int $minWidth = null,
        public ?int $maxWidth = null,
        public ?int $minHeight = null,
        public ?int $maxHeight = null,
        public ?string $ratio = null,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            minWidth: $data['min_width'] ?? null,
            maxWidth: $data['max_width'] ?? null,
            minHeight: $data['min_height'] ?? null,
            maxHeight: $data['max_height'] ?? null,
            ratio: $data['ratio'] ?? null,
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->minWidth !== null) {
            $result['min_width'] = $this->minWidth;
        }

        if ($this->maxWidth !== null) {
            $result['max_width'] = $this->maxWidth;
        }

        if ($this->minHeight !== null) {
            $result['min_height'] = $this->minHeight;
        }

        if ($this->maxHeight !== null) {
            $result['max_height'] = $this->maxHeight;
        }

        if ($this->ratio !== null) {
            $result['ratio'] = $this->ratio;
        }

        return $result;
    }

    /**
     * Create an empty dimensions instance.
     */
    public static function empty(): self
    {
        return new self;
    }

    /**
     * Check if this dimensions instance has no constraints.
     */
    public function isEmpty(): bool
    {
        return $this->minWidth === null
            && $this->maxWidth === null
            && $this->minHeight === null
            && $this->maxHeight === null
            && $this->ratio === null;
    }

    /**
     * Check if width constraints are defined.
     */
    public function hasWidthConstraints(): bool
    {
        return $this->minWidth !== null || $this->maxWidth !== null;
    }

    /**
     * Check if height constraints are defined.
     */
    public function hasHeightConstraints(): bool
    {
        return $this->minHeight !== null || $this->maxHeight !== null;
    }

    /**
     * Check if a ratio constraint is defined.
     */
    public function hasRatio(): bool
    {
        return $this->ratio !== null;
    }
}
