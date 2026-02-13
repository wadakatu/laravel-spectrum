<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents an OpenAPI response object.
 */
final readonly class OpenApiResponse
{
    /**
     * @param  int|string  $statusCode  HTTP status code (e.g., '200', 201, '404')
     * @param  string  $description  Response description
     * @param  array<string, array<string, mixed>>|null  $content  Content by media type
     * @param  array<string, array<string, mixed>>|null  $links  Link objects keyed by link name
     */
    public function __construct(
        public int|string $statusCode,
        public string $description,
        public ?array $content = null,
        public ?array $links = null,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            statusCode: $data['status_code'] ?? $data['statusCode'] ?? '',
            description: $data['description'] ?? '',
            content: $data['content'] ?? null,
            links: $data['links'] ?? null,
        );
    }

    /**
     * Convert to OpenAPI response array (without status code key).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'description' => $this->description,
        ];

        if ($this->content !== null) {
            $result['content'] = $this->content;
        }

        if ($this->links !== null) {
            $result['links'] = $this->links;
        }

        return $result;
    }

    /**
     * Check if this response has content.
     */
    public function hasContent(): bool
    {
        return $this->content !== null;
    }

    /**
     * Check if this response has links.
     */
    public function hasLinks(): bool
    {
        return $this->links !== null && count($this->links) > 0;
    }

    /**
     * Check if this is a success response (2xx).
     */
    public function isSuccess(): bool
    {
        $code = (int) $this->statusCode;

        return $code >= 200 && $code < 300;
    }

    /**
     * Check if this is an error response (4xx or 5xx).
     */
    public function isError(): bool
    {
        $code = (int) $this->statusCode;

        return $code >= 400;
    }

    /**
     * Check if this is a client error response (4xx).
     */
    public function isClientError(): bool
    {
        $code = (int) $this->statusCode;

        return $code >= 400 && $code < 500;
    }

    /**
     * Check if this is a server error response (5xx).
     */
    public function isServerError(): bool
    {
        $code = (int) $this->statusCode;

        return $code >= 500 && $code < 600;
    }

    /**
     * Get the status code as a string.
     */
    public function getStatusCodeAsString(): string
    {
        return (string) $this->statusCode;
    }
}
