<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents an OpenAPI operation object.
 *
 * @phpstan-type OpenApiOperationType array{
 *     operationId?: string,
 *     summary?: string,
 *     description?: string,
 *     tags?: array<int, string>,
 *     parameters?: array<int, array<string, mixed>>,
 *     requestBody?: array{content: array<string, array{schema: array<string, mixed>}>},
 *     responses: array<string, array{description: string, content?: array<string, mixed>}>,
 *     security?: array<int, array<string, array<int, string>>>,
 *     deprecated?: bool,
 *     x-middleware?: array<int, string>,
 *     x-rate-limit?: array{limit: int, period: string}
 * }
 */
final readonly class OpenApiOperation
{
    /**
     * @param  string  $operationId  Unique operation identifier
     * @param  string|null  $summary  Short summary of the operation
     * @param  array<int, string>  $tags  Tags for API documentation control
     * @param  array<int, OpenApiParameter>  $parameters  Path, query, header, cookie parameters
     * @param  array<string, OpenApiResponse>  $responses  Response definitions keyed by status code
     * @param  string|null  $description  Verbose explanation of the operation
     * @param  OpenApiRequestBody|null  $requestBody  Request body definition
     * @param  array<int, array<string, array<int, string>>>|null  $security  Security requirements
     * @param  bool  $deprecated  Whether the operation is deprecated
     */
    public function __construct(
        public string $operationId,
        public ?string $summary,
        public array $tags,
        public array $parameters,
        public array $responses,
        public ?string $description = null,
        public ?OpenApiRequestBody $requestBody = null,
        public ?array $security = null,
        public bool $deprecated = false,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $requestBody = null;
        if (isset($data['requestBody']) && is_array($data['requestBody'])) {
            $requestBody = OpenApiRequestBody::fromArray($data['requestBody']);
        }

        // Convert parameter arrays to DTOs
        $parameters = [];
        foreach ($data['parameters'] ?? [] as $param) {
            $parameters[] = $param instanceof OpenApiParameter
                ? $param
                : OpenApiParameter::fromArray($param);
        }

        // Convert response arrays to DTOs
        $responses = [];
        foreach ($data['responses'] ?? [] as $statusCode => $responseData) {
            $responses[(string) $statusCode] = $responseData instanceof OpenApiResponse
                ? $responseData
                : OpenApiResponse::fromArray(array_merge($responseData, ['status_code' => $statusCode]));
        }

        return new self(
            operationId: $data['operationId'] ?? '',
            summary: $data['summary'] ?? null,
            tags: $data['tags'] ?? [],
            parameters: $parameters,
            responses: $responses,
            description: $data['description'] ?? null,
            requestBody: $requestBody,
            security: $data['security'] ?? null,
            deprecated: $data['deprecated'] ?? false,
        );
    }

    /**
     * Convert to OpenAPI operation array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        // Convert response DTOs to arrays
        $responses = [];
        foreach ($this->responses as $statusCode => $response) {
            $responses[$statusCode] = $response->toArray();
        }

        $result = [
            'operationId' => $this->operationId,
            'tags' => $this->tags,
            'parameters' => array_map(
                fn (OpenApiParameter $param) => $param->toArray(),
                $this->parameters
            ),
            'responses' => $responses,
        ];

        if ($this->summary !== null) {
            $result['summary'] = $this->summary;
        }

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->requestBody !== null) {
            $result['requestBody'] = $this->requestBody->toArray();
        }

        if ($this->security !== null) {
            $result['security'] = $this->security;
        }

        if ($this->deprecated) {
            $result['deprecated'] = true;
        }

        return $result;
    }

    /**
     * Check if this operation has parameters.
     */
    public function hasParameters(): bool
    {
        return count($this->parameters) > 0;
    }

    /**
     * Check if this operation has a request body.
     */
    public function hasRequestBody(): bool
    {
        return $this->requestBody !== null;
    }

    /**
     * Check if this operation has security requirements.
     */
    public function hasSecurity(): bool
    {
        return $this->security !== null && count($this->security) > 0;
    }

    /**
     * Check if this operation is deprecated.
     */
    public function isDeprecated(): bool
    {
        return $this->deprecated;
    }

    /**
     * Get the number of tags.
     */
    public function getTagCount(): int
    {
        return count($this->tags);
    }
}
