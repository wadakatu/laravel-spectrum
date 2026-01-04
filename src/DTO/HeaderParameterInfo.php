<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * DTO representing an HTTP header parameter detected in controller code.
 */
final readonly class HeaderParameterInfo
{
    /**
     * @param  string  $name  The header name (e.g., 'X-Request-Id', 'Authorization')
     * @param  bool  $required  Whether the header is required
     * @param  string  $type  The OpenAPI type (always 'string' for headers)
     * @param  string|int|float|bool|array<array-key, mixed>|null  $default  Default value if any
     * @param  string  $source  The source method (header, hasHeader, bearerToken)
     * @param  string|null  $description  Human-readable description
     * @param  bool  $isBearerToken  Whether this is a bearer token (Authorization header)
     * @param  array<string, mixed>  $context  Additional context from analysis
     */
    public function __construct(
        public string $name,
        public bool $required = false,
        public string $type = 'string',
        public string|int|float|bool|array|null $default = null,
        public string $source = 'header',
        public ?string $description = null,
        public bool $isBearerToken = false,
        public array $context = [],
    ) {}

    /**
     * Create instance from array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            required: $data['required'] ?? false,
            type: $data['type'] ?? 'string',
            default: $data['default'] ?? null,
            source: $data['source'] ?? 'header',
            description: $data['description'] ?? null,
            isBearerToken: $data['isBearerToken'] ?? $data['is_bearer_token'] ?? false,
            context: $data['context'] ?? [],
        );
    }

    /**
     * Convert to array for OpenAPI generation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'name' => $this->name,
            'in' => 'header',
            'required' => $this->required,
            'type' => $this->type,
            'schema' => [
                'type' => $this->type,
            ],
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->default !== null) {
            $result['schema']['default'] = $this->default;
        }

        if ($this->isBearerToken) {
            $result['schema']['format'] = 'bearer';
        }

        return $result;
    }

    /**
     * Generate description for the header.
     */
    public function generateDescription(): string
    {
        if ($this->description !== null) {
            return $this->description;
        }

        if ($this->isBearerToken) {
            return 'Bearer token for authentication';
        }

        return "Request header: {$this->name}";
    }
}
