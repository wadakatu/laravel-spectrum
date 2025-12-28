<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents Fractal transformer information detected from controller analysis.
 */
final readonly class FractalInfo
{
    /**
     * @param  string  $transformer  The transformer class name
     * @param  bool  $isCollection  Whether this is a collection transformation
     * @param  string  $type  The transformation type ('item' or 'collection')
     * @param  bool  $hasIncludes  Whether parseIncludes is used
     */
    public function __construct(
        public string $transformer,
        public bool $isCollection,
        public string $type,
        public bool $hasIncludes = false,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            transformer: $data['transformer'],
            isCollection: $data['collection'] ?? $data['isCollection'] ?? false,
            type: $data['type'] ?? ($data['collection'] ?? false ? 'collection' : 'item'),
            hasIncludes: $data['hasIncludes'] ?? false,
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
            'transformer' => $this->transformer,
            'collection' => $this->isCollection,
            'type' => $this->type,
            'hasIncludes' => $this->hasIncludes,
        ];
    }

    /**
     * Check if this is an item transformation.
     */
    public function isItem(): bool
    {
        return $this->type === 'item';
    }
}
