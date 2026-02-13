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
     * @param  InlineValidationInfo|null  $inlineValidation  Inline validation rules if detected
     * @param  string|null  $resource  The Resource class name if detected (backward compatible)
     * @param  array<int, string>  $resourceClasses  All resource class names (for union types)
     * @param  bool  $returnsCollection  Whether the method returns a collection
     * @param  FractalInfo|null  $fractal  Fractal transformer info if detected
     * @param  PaginationInfo|null  $pagination  Pagination info if detected
     * @param  array<int, QueryParameterInfo>  $queryParameters  Query parameters if detected
     * @param  array<int, HeaderParameterInfo>  $headerParameters  Header parameters if detected
     * @param  array<int, EnumParameterInfo>  $enumParameters  Enum parameters from method signature
     * @param  ResponseInfo|null  $response  Response analysis info
     * @param  bool  $deprecated  Whether the controller method is marked as deprecated
     * @param  array<int, CallbackInfo>  $callbacks  OpenAPI callback definitions
     */
    public function __construct(
        public ?string $formRequest = null,
        public ?InlineValidationInfo $inlineValidation = null,
        public ?string $resource = null,
        public array $resourceClasses = [],
        public bool $returnsCollection = false,
        public ?FractalInfo $fractal = null,
        public ?PaginationInfo $pagination = null,
        public array $queryParameters = [],
        public array $headerParameters = [],
        public array $enumParameters = [],
        public ?ResponseInfo $response = null,
        public bool $deprecated = false,
        public array $callbacks = [],
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

        // Convert headerParameters arrays to DTOs
        $headerParameters = [];
        if (isset($data['headerParameters']) && is_array($data['headerParameters'])) {
            foreach ($data['headerParameters'] as $param) {
                $headerParameters[] = HeaderParameterInfo::fromArray($param);
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

        // Convert inlineValidation array to DTO
        $inlineValidation = null;
        if (isset($data['inlineValidation']) && is_array($data['inlineValidation'])) {
            $inlineValidation = InlineValidationInfo::fromArray($data['inlineValidation']);
        }

        // Handle resourceClasses
        $resourceClasses = [];
        if (isset($data['resourceClasses']) && is_array($data['resourceClasses'])) {
            $resourceClasses = $data['resourceClasses'];
        }

        // Convert callbacks arrays to DTOs
        $callbacks = [];
        if (isset($data['callbacks']) && is_array($data['callbacks'])) {
            foreach ($data['callbacks'] as $cb) {
                $callbacks[] = CallbackInfo::fromArray($cb);
            }
        }

        return new self(
            formRequest: $data['formRequest'] ?? null,
            inlineValidation: $inlineValidation,
            resource: $data['resource'] ?? null,
            resourceClasses: $resourceClasses,
            returnsCollection: $data['returnsCollection'] ?? false,
            fractal: $fractal,
            pagination: $pagination,
            queryParameters: $queryParameters,
            headerParameters: $headerParameters,
            enumParameters: $enumParameters,
            response: $response,
            deprecated: $data['deprecated'] ?? false,
            callbacks: $callbacks,
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
            'inlineValidation' => $this->inlineValidation?->toArray(),
            'resource' => $this->resource,
            'resourceClasses' => $this->resourceClasses,
            'returnsCollection' => $this->returnsCollection,
            'fractal' => $this->fractal?->toArray(),
            'pagination' => $this->pagination?->toArray(),
            'queryParameters' => array_map(fn (QueryParameterInfo $p) => $p->toArray(), $this->queryParameters),
            'headerParameters' => array_map(fn (HeaderParameterInfo $p) => $p->toArray(), $this->headerParameters),
            'enumParameters' => array_map(fn (EnumParameterInfo $p) => $p->toArray(), $this->enumParameters),
            'response' => $this->response?->toArray(),
            'deprecated' => $this->deprecated,
            'callbacks' => array_map(fn (CallbackInfo $cb) => $cb->toArray(), $this->callbacks),
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
     * Check if multiple resource classes were detected (union type).
     */
    public function hasMultipleResources(): bool
    {
        return count($this->resourceClasses) > 1;
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
     * Check if header parameters were detected.
     */
    public function hasHeaderParameters(): bool
    {
        return count($this->headerParameters) > 0;
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
     * Check if callbacks were detected.
     */
    public function hasCallbacks(): bool
    {
        return count($this->callbacks) > 0;
    }

    /**
     * Check if the controller method is marked as deprecated.
     */
    public function isDeprecated(): bool
    {
        return $this->deprecated;
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
            && ! $this->hasHeaderParameters()
            && ! $this->hasEnumParameters()
            && ! $this->hasResponse()
            && ! $this->hasCallbacks();
    }
}
