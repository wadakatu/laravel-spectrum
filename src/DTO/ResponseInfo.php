<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents the result of analyzing a controller method's response.
 */
final readonly class ResponseInfo
{
    /**
     * @param  ResponseType  $type  The type of response
     * @param  array<string, array<string, mixed>>  $properties  The response properties/schema
     * @param  string|null  $resourceClass  The resource class name (for resource type)
     * @param  string|null  $error  Error message if analysis failed
     * @param  string|null  $contentType  The content type (e.g., "application/pdf")
     * @param  string|null  $fileName  The file name for download responses
     */
    public function __construct(
        public ResponseType $type,
        public array $properties = [],
        public ?string $resourceClass = null,
        public ?string $error = null,
        public ?string $contentType = null,
        public ?string $fileName = null,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $typeString = $data['type'] ?? 'unknown';
        $type = ResponseType::tryFrom($typeString) ?? ResponseType::UNKNOWN;

        return new self(
            type: $type,
            properties: $data['properties'] ?? [],
            resourceClass: $data['class'] ?? null,
            error: $data['error'] ?? null,
            contentType: $data['contentType'] ?? null,
            fileName: $data['fileName'] ?? null,
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
            'type' => $this->type->value,
            'properties' => $this->properties,
        ];

        if ($this->resourceClass !== null) {
            $result['class'] = $this->resourceClass;
        }

        if ($this->error !== null) {
            $result['error'] = $this->error;
        }

        if ($this->contentType !== null) {
            $result['contentType'] = $this->contentType;
        }

        if ($this->fileName !== null) {
            $result['fileName'] = $this->fileName;
        }

        return $result;
    }

    /**
     * Create a void response info.
     */
    public static function void(): self
    {
        return new self(
            type: ResponseType::VOID,
            properties: [],
        );
    }

    /**
     * Create an unknown response info.
     */
    public static function unknown(): self
    {
        return new self(
            type: ResponseType::UNKNOWN,
            properties: [],
        );
    }

    /**
     * Create an unknown response info with error.
     */
    public static function unknownWithError(string $error): self
    {
        return new self(
            type: ResponseType::UNKNOWN,
            properties: [],
            error: $error,
        );
    }

    /**
     * Create a binary file response info.
     */
    public static function binaryFile(string $contentType, ?string $fileName = null): self
    {
        return new self(
            type: ResponseType::BINARY_FILE,
            properties: [],
            contentType: $contentType,
            fileName: $fileName,
        );
    }

    /**
     * Create a streamed response info.
     */
    public static function streamed(string $contentType): self
    {
        return new self(
            type: ResponseType::STREAMED,
            properties: [],
            contentType: $contentType,
        );
    }

    /**
     * Create a custom content type response info.
     */
    public static function customContentType(string $contentType): self
    {
        return new self(
            type: ResponseType::CUSTOM,
            properties: [],
            contentType: $contentType,
        );
    }

    /**
     * Check if this is a void response.
     */
    public function isVoid(): bool
    {
        return $this->type->isVoid();
    }

    /**
     * Check if this is a collection response.
     */
    public function isCollection(): bool
    {
        return $this->type->isCollection();
    }

    /**
     * Check if this is a resource response.
     */
    public function isResource(): bool
    {
        return $this->type->isResource();
    }

    /**
     * Check if this response has an error.
     */
    public function hasError(): bool
    {
        return $this->error !== null;
    }

    /**
     * Check if this response has properties.
     */
    public function hasProperties(): bool
    {
        return count($this->properties) > 0;
    }

    /**
     * Check if this response has a resource class.
     */
    public function hasResourceClass(): bool
    {
        return $this->resourceClass !== null;
    }

    /**
     * Get all property names.
     *
     * @return array<int, string>
     */
    public function getPropertyNames(): array
    {
        return array_keys($this->properties);
    }

    /**
     * Get the content type for this response.
     *
     * Returns explicit content type if set, otherwise returns default based on type.
     */
    public function getContentType(): string
    {
        if ($this->contentType !== null) {
            return $this->contentType;
        }

        return match ($this->type) {
            ResponseType::BINARY_FILE, ResponseType::STREAMED => 'application/octet-stream',
            ResponseType::PLAIN_TEXT => 'text/plain',
            ResponseType::XML => 'application/xml',
            ResponseType::HTML => 'text/html',
            default => 'application/json',
        };
    }
}
