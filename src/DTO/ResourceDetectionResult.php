<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents the result of detecting a Laravel API Resource from controller code.
 *
 * Used by ControllerAnalyzer to communicate whether a resource class was found
 * and whether it represents a single item or a collection.
 */
final readonly class ResourceDetectionResult
{
    /**
     * @param  string|null  $resourceClass  The fully qualified resource class name, or null if not found
     * @param  bool  $isCollection  Whether the resource is used as a collection
     */
    private function __construct(
        public ?string $resourceClass,
        public bool $isCollection,
    ) {}

    /**
     * Check if a resource class was detected.
     */
    public function hasResource(): bool
    {
        return $this->resourceClass !== null;
    }

    /**
     * Create a result indicating no resource was found.
     */
    public static function notFound(): self
    {
        return new self(
            resourceClass: null,
            isCollection: false,
        );
    }

    /**
     * Create a result for a single resource.
     */
    public static function single(string $resourceClass): self
    {
        return new self(
            resourceClass: $resourceClass,
            isCollection: false,
        );
    }

    /**
     * Create a result for a resource collection.
     */
    public static function collection(string $resourceClass): self
    {
        return new self(
            resourceClass: $resourceClass,
            isCollection: true,
        );
    }

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $resourceClass = $data['resourceClass'] ?? null;
        $isCollection = $data['isCollection'] ?? false;

        if ($resourceClass === null) {
            return self::notFound();
        }

        return $isCollection
            ? self::collection($resourceClass)
            : self::single($resourceClass);
    }

    /**
     * Convert to array.
     *
     * @return array{resourceClass: string|null, isCollection: bool}
     */
    public function toArray(): array
    {
        return [
            'resourceClass' => $this->resourceClass,
            'isCollection' => $this->isCollection,
        ];
    }
}
