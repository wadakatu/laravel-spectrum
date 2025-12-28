<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents the analysis result of a controller method.
 *
 * This DTO encapsulates all information extracted from analyzing
 * a controller method, including FormRequest, Resource, validation,
 * pagination, and query parameter information.
 */
final readonly class ControllerInfo
{
    /**
     * @param  string|null  $formRequest  The FormRequest class name if detected
     * @param  array<string, mixed>|null  $inlineValidation  Inline validation rules if detected (to be replaced with DTO in #226)
     * @param  string|null  $resource  The Resource class name if detected
     * @param  bool  $returnsCollection  Whether the method returns a collection
     * @param  FractalInfo|null  $fractal  Fractal transformer info if detected
     * @param  PaginationInfo|null  $pagination  Pagination info if detected
     * @param  array<int, QueryParameterInfo>  $queryParameters  Query parameters if detected
     * @param  array<int, EnumParameterInfo>  $enumParameters  Enum parameters from method signature
     * @param  ResponseInfo|null  $response  Response analysis info
     */
    public function __construct(
        public ?string $formRequest = null,
        public ?array $inlineValidation = null,
        public ?string $resource = null,
        public bool $returnsCollection = false,
        public ?FractalInfo $fractal = null,
        public ?PaginationInfo $pagination = null,
        public array $queryParameters = [],
        public array $enumParameters = [],
        public ?ResponseInfo $response = null,
    ) {}

    /**
     * Create an empty ControllerInfo instance.
     */
    public static function empty(): self
    {
        return new self;
    }

    /**
     * Create from an array (for backward compatibility).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        // Convert fractal array to DTO
        $fractal = null;
        if (isset($data['fractal']) && is_array($data['fractal'])) {
            $fractal = FractalInfo::fromArray($data['fractal']);
        }

        // Convert pagination array to DTO
        $pagination = null;
        if (isset($data['pagination']) && is_array($data['pagination'])) {
            $pagination = PaginationInfo::fromArray($data['pagination']);
        }

        // Convert queryParameters arrays to DTOs
        $queryParameters = [];
        if (isset($data['queryParameters']) && is_array($data['queryParameters'])) {
            foreach ($data['queryParameters'] as $param) {
                $queryParameters[] = QueryParameterInfo::fromArray($param);
            }
        }

        // Convert enumParameters arrays to DTOs
        $enumParameters = [];
        if (isset($data['enumParameters']) && is_array($data['enumParameters'])) {
            foreach ($data['enumParameters'] as $param) {
                $enumParameters[] = EnumParameterInfo::fromArray($param);
            }
        }

        // Convert response array to DTO
        $response = null;
        if (isset($data['response']) && is_array($data['response'])) {
            $response = ResponseInfo::fromArray($data['response']);
        }

        return new self(
            formRequest: $data['formRequest'] ?? null,
            inlineValidation: $data['inlineValidation'] ?? null,
            resource: $data['resource'] ?? null,
            returnsCollection: $data['returnsCollection'] ?? false,
            fractal: $fractal,
            pagination: $pagination,
            queryParameters: $queryParameters,
            enumParameters: $enumParameters,
            response: $response,
        );
    }

    /**
     * Convert to array (for backward compatibility).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'formRequest' => $this->formRequest,
            'inlineValidation' => $this->inlineValidation,
            'resource' => $this->resource,
            'returnsCollection' => $this->returnsCollection,
            'fractal' => $this->fractal?->toArray(),
            'pagination' => $this->pagination?->toArray(),
            'queryParameters' => array_map(fn (QueryParameterInfo $p) => $p->toArray(), $this->queryParameters),
            'enumParameters' => array_map(fn (EnumParameterInfo $p) => $p->toArray(), $this->enumParameters),
            'response' => $this->response?->toArray(),
        ];
    }

    /**
     * Check if a FormRequest class was detected.
     */
    public function hasFormRequest(): bool
    {
        return $this->formRequest !== null;
    }

    /**
     * Check if inline validation was detected.
     */
    public function hasInlineValidation(): bool
    {
        return $this->inlineValidation !== null;
    }

    /**
     * Check if any form of validation (FormRequest or inline) was detected.
     */
    public function hasValidation(): bool
    {
        return $this->hasFormRequest() || $this->hasInlineValidation();
    }

    /**
     * Check if a Resource class was detected.
     */
    public function hasResource(): bool
    {
        return $this->resource !== null;
    }

    /**
     * Check if pagination was detected.
     */
    public function hasPagination(): bool
    {
        return $this->pagination !== null;
    }

    /**
     * Check if Fractal transformer was detected.
     */
    public function hasFractal(): bool
    {
        return $this->fractal !== null;
    }

    /**
     * Check if query parameters were detected.
     */
    public function hasQueryParameters(): bool
    {
        return count($this->queryParameters) > 0;
    }

    /**
     * Check if enum parameters were detected.
     */
    public function hasEnumParameters(): bool
    {
        return count($this->enumParameters) > 0;
    }

    /**
     * Check if response info was detected.
     */
    public function hasResponse(): bool
    {
        return $this->response !== null;
    }

    /**
     * Check if this is an empty result (no useful information detected).
     */
    public function isEmpty(): bool
    {
        return ! $this->hasFormRequest()
            && ! $this->hasInlineValidation()
            && ! $this->hasResource()
            && ! $this->hasFractal()
            && ! $this->hasPagination()
            && ! $this->hasQueryParameters()
            && ! $this->hasEnumParameters()
            && ! $this->hasResponse();
    }
}
