<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents pagination information detected from controller analysis.
 */
final readonly class PaginationInfo
{
    /**
     * @param  string  $type  The pagination type ('paginate', 'simplePaginate', 'cursorPaginate')
     * @param  string|null  $model  The model class being paginated
     * @param  string|null  $resource  The resource class if using resource collection
     * @param  int|null  $perPage  The per page value if detected
     * @param  bool  $hasCustomPerPage  Whether a custom per page value is used
     */
    public function __construct(
        public string $type,
        public ?string $model = null,
        public ?string $resource = null,
        public ?int $perPage = null,
        public bool $hasCustomPerPage = false,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'] ?? 'paginate',
            model: $data['model'] ?? null,
            resource: $data['resource'] ?? null,
            perPage: isset($data['perPage']) ? (int) $data['perPage'] : null,
            hasCustomPerPage: $data['hasCustomPerPage'] ?? false,
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
            'type' => $this->type,
        ];

        if ($this->model !== null) {
            $result['model'] = $this->model;
        }

        if ($this->resource !== null) {
            $result['resource'] = $this->resource;
        }

        if ($this->perPage !== null) {
            $result['perPage'] = $this->perPage;
        }

        if ($this->hasCustomPerPage) {
            $result['hasCustomPerPage'] = $this->hasCustomPerPage;
        }

        return $result;
    }

    /**
     * Check if this is standard pagination.
     */
    public function isPaginated(): bool
    {
        return $this->type === 'paginate';
    }

    /**
     * Check if this is simple pagination.
     */
    public function isSimplePaginated(): bool
    {
        return $this->type === 'simplePaginate';
    }

    /**
     * Check if this is cursor-based pagination.
     */
    public function isCursorBased(): bool
    {
        return $this->type === 'cursorPaginate';
    }

    /**
     * Check if a model class is available.
     */
    public function hasModel(): bool
    {
        return $this->model !== null;
    }

    /**
     * Check if a resource class is available.
     */
    public function hasResource(): bool
    {
        return $this->resource !== null;
    }
}
