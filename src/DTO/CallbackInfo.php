<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents an OpenAPI callback definition.
 *
 * Callbacks are used to define webhook/asynchronous API callbacks
 * at the operation level in the OpenAPI specification.
 */
final readonly class CallbackInfo
{
    /**
     * @param  string  $name  The callback name (e.g., 'onOrderStatusChange')
     * @param  string  $expression  The runtime expression for the callback URL (e.g., '{$request.body#/callbackUrl}')
     * @param  string  $method  The HTTP method for the callback (default: 'post')
     * @param  array<string, mixed>|null  $requestBody  The request body schema for the callback
     * @param  array<string, mixed>|null  $responses  The response definitions for the callback
     * @param  string|null  $description  A description of the callback
     * @param  string|null  $summary  A short summary of the callback
     * @param  string|null  $ref  Reference name for components/callbacks
     */
    public function __construct(
        public string $name,
        public string $expression,
        public string $method = 'post',
        public ?array $requestBody = null,
        public ?array $responses = null,
        public ?string $description = null,
        public ?string $summary = null,
        public ?string $ref = null,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            expression: $data['expression'],
            method: $data['method'] ?? 'post',
            requestBody: $data['requestBody'] ?? null,
            responses: $data['responses'] ?? null,
            description: $data['description'] ?? null,
            summary: $data['summary'] ?? null,
            ref: $data['ref'] ?? null,
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
            'name' => $this->name,
            'expression' => $this->expression,
            'method' => $this->method,
            'requestBody' => $this->requestBody,
            'responses' => $this->responses,
            'description' => $this->description,
            'summary' => $this->summary,
            'ref' => $this->ref,
        ];
    }

    /**
     * Check if this callback has a component reference.
     */
    public function hasRef(): bool
    {
        return $this->ref !== null;
    }

    /**
     * Check if this callback has a request body definition.
     */
    public function hasRequestBody(): bool
    {
        return $this->requestBody !== null;
    }

    /**
     * Check if this callback has response definitions.
     */
    public function hasResponses(): bool
    {
        return $this->responses !== null;
    }
}
