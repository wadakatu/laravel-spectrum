<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents an OpenAPI Server object.
 *
 * @see https://spec.openapis.org/oas/v3.1.0#server-object
 */
final readonly class OpenApiServer
{
    /**
     * @param  string  $url  The URL to the target host
     * @param  string  $description  An optional description of the host
     * @param  array<string, array{default?: string, description?: string, enum?: array<int, string>}>|null  $variables  Server variables
     */
    public function __construct(
        public string $url,
        public string $description = '',
        public ?array $variables = null,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'] ?? '',
            description: $data['description'] ?? '',
            variables: $data['variables'] ?? null,
        );
    }

    /**
     * Convert to OpenAPI server array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'url' => $this->url,
        ];

        if ($this->description !== '') {
            $result['description'] = $this->description;
        }

        if ($this->variables !== null) {
            $result['variables'] = $this->variables;
        }

        return $result;
    }

    /**
     * Check if this server has variables.
     */
    public function hasVariables(): bool
    {
        return $this->variables !== null && count($this->variables) > 0;
    }

    /**
     * Check if this server has a description.
     */
    public function hasDescription(): bool
    {
        return $this->description !== '';
    }
}
