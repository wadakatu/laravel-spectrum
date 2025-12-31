<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents the result of detecting a Laravel API Resource from controller code.
 *
 * Used by ControllerAnalyzer to communicate whether a resource class was found
 * and whether it represents a single item or a collection.
 *
 * Supports both single resource types and PHP 8 union return types
 * (e.g., UserResource|PostResource) which will generate oneOf schemas.
 */
final readonly class ResourceDetectionResult
{
    /**
     * @param  string|null  $resourceClass  The fully qualified resource class name, or null if not found
     * @param  array<int, string>  $resourceClasses  All resource class names (for union types)
     * @param  bool  $isCollection  Whether the resource is used as a collection
     */
    private function __construct(
        public ?string $resourceClass,
        public array $resourceClasses,
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
     * Check if multiple resource classes were detected (union type).
     */
    public function hasMultipleResources(): bool
    {
        return count($this->resourceClasses) > 1;
    }

    /**
     * Create a result indicating no resource was found.
     */
    public static function notFound(): self
    {
        return new self(
            resourceClass: null,
            resourceClasses: [],
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
            resourceClasses: [$resourceClass],
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
            resourceClasses: [$resourceClass],
            isCollection: true,
        );
    }

    /**
     * Create a result for a union of multiple resource types.
     *
     * @param  array<int, string>  $resourceClasses  The resource class names in the union (must have at least 2 elements)
     *
     * @throws \InvalidArgumentException if less than 2 resource classes provided
     */
    public static function union(array $resourceClasses): self
    {
        if (count($resourceClasses) < 2) {
            throw new \InvalidArgumentException('Union requires at least 2 resource classes');
        }

        return new self(
            resourceClass: $resourceClasses[0],
            resourceClasses: $resourceClasses,
            isCollection: false,
        );
    }

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        // Support new resourceClasses array format
        if (isset($data['resourceClasses']) && is_array($data['resourceClasses'])) {
            $resourceClasses = $data['resourceClasses'];
            $resourceClass = $resourceClasses[0] ?? null;
            $isCollection = $data['isCollection'] ?? false;

            return new self(
                resourceClass: $resourceClass,
                resourceClasses: $resourceClasses,
                isCollection: $isCollection,
            );
        }

        // Backward compatibility: single resourceClass
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
     * @return array{resourceClass: string|null, resourceClasses: array<int, string>, isCollection: bool}
     */
    public function toArray(): array
    {
        return [
            'resourceClass' => $this->resourceClass,
            'resourceClasses' => $this->resourceClasses,
            'isCollection' => $this->isCollection,
        ];
    }
}
