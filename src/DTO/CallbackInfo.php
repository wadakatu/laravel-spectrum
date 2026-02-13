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
    /** @var string The HTTP method for the callback, always lowercase */
    public string $method;

    /**
     * @param  string  $name  The callback name (e.g., 'onOrderStatusChange')
     * @param  string  $expression  The runtime expression for the callback URL (e.g., '{$request.body#/callbackUrl}')
     * @param  string  $method  The HTTP method for the callback (default: 'post')
     * @param  array<string, mixed>|null  $requestBody  The request body schema for the callback
     * @param  array<string, mixed>|null  $responses  The response definitions for the callback
     * @param  string|null  $description  A description of the callback
     * @param  string|null  $summary  A short summary of the callback
     * @param  string|null  $ref  When set, emitted as a $ref to #/components/callbacks/{ref} instead of inline definition
     */
    public function __construct(
        public string $name,
        public string $expression,
        string $method = 'post',
        public ?array $requestBody = null,
        public ?array $responses = null,
        public ?string $description = null,
        public ?string $summary = null,
        public ?string $ref = null,
    ) {
        $this->method = strtolower($method);
    }

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['name']) || ! is_string($data['name'])) {
            throw new \InvalidArgumentException(
                "Callback config requires a 'name' key (string). Got keys: ".implode(', ', array_keys($data))
            );
        }
        if (! isset($data['expression']) || ! is_string($data['expression'])) {
            throw new \InvalidArgumentException(
                "Callback config for '{$data['name']}' requires an 'expression' key (string)."
            );
        }

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
        $result = [
            'name' => $this->name,
            'expression' => $this->expression,
            'method' => $this->method,
        ];

        if ($this->requestBody !== null) {
            $result['requestBody'] = $this->requestBody;
        }
        if ($this->responses !== null) {
            $result['responses'] = $this->responses;
        }
        if ($this->description !== null) {
            $result['description'] = $this->description;
        }
        if ($this->summary !== null) {
            $result['summary'] = $this->summary;
        }
        if ($this->ref !== null) {
            $result['ref'] = $this->ref;
        }

        return $result;
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
