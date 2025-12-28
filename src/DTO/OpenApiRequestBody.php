<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents an OpenAPI request body object.
 */
final readonly class OpenApiRequestBody
{
    /**
     * @param  array<string, array<string, mixed>>  $content  Content by media type
     * @param  bool  $required  Whether the request body is required
     * @param  string|null  $description  Request body description
     */
    public function __construct(
        public array $content,
        public bool $required = false,
        public ?string $description = null,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            content: $data['content'] ?? [],
            required: $data['required'] ?? false,
            description: $data['description'] ?? null,
        );
    }

    /**
     * Convert to OpenAPI request body array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'required' => $this->required,
            'content' => $this->content,
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        return $result;
    }

    /**
     * Check if the content type is JSON.
     */
    public function isJson(): bool
    {
        return isset($this->content['application/json']);
    }

    /**
     * Check if the content type is multipart/form-data.
     */
    public function isMultipart(): bool
    {
        return isset($this->content['multipart/form-data']);
    }

    /**
     * Check if the content type is application/x-www-form-urlencoded.
     */
    public function isFormUrlEncoded(): bool
    {
        return isset($this->content['application/x-www-form-urlencoded']);
    }

    /**
     * Get all content types.
     *
     * @return array<int, string>
     */
    public function getContentTypes(): array
    {
        return array_keys($this->content);
    }

    /**
     * Get the schema for a specific content type.
     *
     * @return array<string, mixed>|null
     */
    public function getSchemaFor(string $contentType): ?array
    {
        return $this->content[$contentType]['schema'] ?? null;
    }
}
