<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents an authentication scheme for OpenAPI security definitions.
 */
final readonly class AuthenticationScheme
{
    /**
     * @param  AuthenticationType  $type  The authentication type (http, apiKey, oauth2)
     * @param  string  $name  The unique name for this scheme
     * @param  string|null  $scheme  The HTTP scheme (bearer, basic) - for http type
     * @param  string|null  $bearerFormat  The bearer format (e.g., JWT) - for bearer scheme
     * @param  string|null  $in  Where the API key is located (header, query, cookie) - for apiKey type
     * @param  string|null  $headerName  The actual header/query/cookie name - for apiKey type
     * @param  array<string, array<string, mixed>>|null  $flows  OAuth2 flows - for oauth2 type
     * @param  string|null  $description  Description of the authentication scheme
     */
    public function __construct(
        public AuthenticationType $type,
        public string $name,
        public ?string $scheme = null,
        public ?string $bearerFormat = null,
        public ?string $in = null,
        public ?string $headerName = null,
        public ?array $flows = null,
        public ?string $description = null,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $typeString = $data['type'] ?? 'http';
        $type = AuthenticationType::tryFrom($typeString) ?? AuthenticationType::HTTP;

        return new self(
            type: $type,
            name: $data['name'] ?? '',
            scheme: $data['scheme'] ?? null,
            bearerFormat: $data['bearerFormat'] ?? null,
            in: $data['in'] ?? null,
            headerName: $data['headerName'] ?? null,
            flows: $data['flows'] ?? null,
            description: $data['description'] ?? null,
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
            'name' => $this->name,
        ];

        if ($this->scheme !== null) {
            $result['scheme'] = $this->scheme;
        }

        if ($this->bearerFormat !== null) {
            $result['bearerFormat'] = $this->bearerFormat;
        }

        if ($this->in !== null) {
            $result['in'] = $this->in;
        }

        if ($this->headerName !== null) {
            $result['headerName'] = $this->headerName;
        }

        if ($this->flows !== null) {
            $result['flows'] = $this->flows;
        }

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        return $result;
    }

    /**
     * Convert to OpenAPI security scheme format.
     *
     * @return array<string, mixed>
     */
    public function toOpenApiSecurityScheme(): array
    {
        $result = [
            'type' => $this->type->value,
        ];

        // HTTP type (bearer, basic)
        if ($this->type->isHttp()) {
            if ($this->scheme !== null) {
                $result['scheme'] = $this->scheme;
            }

            if ($this->bearerFormat !== null) {
                $result['bearerFormat'] = $this->bearerFormat;
            }
        }

        // API Key type
        if ($this->type->isApiKey()) {
            if ($this->in !== null) {
                $result['in'] = $this->in;
            }

            // Use headerName as the OpenAPI 'name' field
            if ($this->headerName !== null) {
                $result['name'] = $this->headerName;
            }
        }

        // OAuth2 type
        if ($this->type->isOAuth2() && $this->flows !== null) {
            $result['flows'] = $this->flows;
        }

        // Description is common to all types
        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        return $result;
    }

    /**
     * Check if this is a Bearer authentication scheme.
     */
    public function isBearer(): bool
    {
        return $this->type->isHttp() && $this->scheme === 'bearer';
    }

    /**
     * Check if this is a Basic authentication scheme.
     */
    public function isBasic(): bool
    {
        return $this->type->isHttp() && $this->scheme === 'basic';
    }

    /**
     * Check if this is an API Key authentication scheme.
     */
    public function isApiKey(): bool
    {
        return $this->type->isApiKey();
    }

    /**
     * Check if this is an OAuth2 authentication scheme.
     */
    public function isOAuth2(): bool
    {
        return $this->type->isOAuth2();
    }

    /**
     * Check if this is an HTTP authentication scheme.
     */
    public function isHttp(): bool
    {
        return $this->type->isHttp();
    }
}
