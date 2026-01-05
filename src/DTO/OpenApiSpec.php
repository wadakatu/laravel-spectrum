<?php

declare(strict_types=1);

namespace LaravelSpectrum\DTO;

/**
 * Represents a complete OpenAPI specification document.
 *
 * @see https://spec.openapis.org/oas/v3.1.0#openapi-object
 */
final readonly class OpenApiSpec
{
    /**
     * @param  string  $openapi  OpenAPI version (e.g., "3.0.0", "3.1.0")
     * @param  OpenApiInfo  $info  API metadata
     * @param  array<int, OpenApiServer>  $servers  Server definitions
     * @param  array<string, array<string, array<string, mixed>>>  $paths  Path definitions
     * @param  array<string, array<string, mixed>>  $components  Reusable components (schemas, securitySchemes, etc.)
     * @param  array<int, array<string, array<int, string>>>  $security  Global security requirements
     * @param  array<int, array{name: string, description?: string}>  $tags  Tag definitions
     * @param  array<int, array{name: string, tags: array<int, string>}>|null  $tagGroups  Swagger UI extension for tag groups
     * @param  \stdClass|array<string, mixed>|null  $webhooks  Webhooks definitions (OpenAPI 3.1.0+)
     * @param  string|null  $jsonSchemaDialect  JSON Schema dialect URI (OpenAPI 3.1.0+)
     */
    public function __construct(
        public string $openapi,
        public OpenApiInfo $info,
        public array $servers = [],
        public array $paths = [],
        public array $components = [],
        public array $security = [],
        public array $tags = [],
        public ?array $tagGroups = null,
        public \stdClass|array|null $webhooks = null,
        public ?string $jsonSchemaDialect = null,
    ) {}

    /**
     * Create from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $info = isset($data['info']) && is_array($data['info'])
            ? OpenApiInfo::fromArray($data['info'])
            : new OpenApiInfo(title: '', version: '');

        $servers = [];
        foreach ($data['servers'] ?? [] as $serverData) {
            $servers[] = $serverData instanceof OpenApiServer
                ? $serverData
                : OpenApiServer::fromArray($serverData);
        }

        return new self(
            openapi: $data['openapi'] ?? '3.0.0',
            info: $info,
            servers: $servers,
            paths: $data['paths'] ?? [],
            components: $data['components'] ?? [],
            security: $data['security'] ?? [],
            tags: $data['tags'] ?? [],
            tagGroups: $data['x-tagGroups'] ?? null,
            webhooks: $data['webhooks'] ?? null,
            jsonSchemaDialect: $data['jsonSchemaDialect'] ?? null,
        );
    }

    /**
     * Convert to OpenAPI specification array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'openapi' => $this->openapi,
        ];

        // Add jsonSchemaDialect right after openapi (OpenAPI 3.1.0+)
        if ($this->jsonSchemaDialect !== null) {
            $result['jsonSchemaDialect'] = $this->jsonSchemaDialect;
        }

        $result['info'] = $this->info->toArray();
        $result['servers'] = array_map(
            fn (OpenApiServer $server) => $server->toArray(),
            $this->servers
        );
        $result['paths'] = $this->paths;
        $result['components'] = $this->components;

        if (count($this->security) > 0) {
            $result['security'] = $this->security;
        }

        if (count($this->tags) > 0) {
            $result['tags'] = $this->tags;
        }

        if ($this->tagGroups !== null) {
            $result['x-tagGroups'] = $this->tagGroups;
        }

        if ($this->webhooks !== null) {
            $result['webhooks'] = $this->webhooks;
        }

        return $result;
    }

    /**
     * Get component schemas.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSchemas(): array
    {
        return $this->components['schemas'] ?? [];
    }

    /**
     * Get security schemes.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSecuritySchemes(): array
    {
        return $this->components['securitySchemes'] ?? [];
    }

    /**
     * Check if this specification has global security requirements.
     */
    public function hasGlobalSecurity(): bool
    {
        return count($this->security) > 0;
    }

    /**
     * Check if this specification has servers defined.
     */
    public function hasServers(): bool
    {
        return count($this->servers) > 0;
    }

    /**
     * Check if this specification has paths defined.
     */
    public function hasPaths(): bool
    {
        return count($this->paths) > 0;
    }

    /**
     * Check if this specification has components.
     */
    public function hasComponents(): bool
    {
        return count($this->components) > 0;
    }

    /**
     * Check if this specification has tags.
     */
    public function hasTags(): bool
    {
        return count($this->tags) > 0;
    }

    /**
     * Check if this specification has tag groups (Swagger UI extension).
     */
    public function hasTagGroups(): bool
    {
        return $this->tagGroups !== null;
    }

    /**
     * Check if this specification has webhooks (OpenAPI 3.1.0+).
     */
    public function hasWebhooks(): bool
    {
        return $this->webhooks !== null;
    }

    /**
     * Get a path definition by path string.
     *
     * @return array<string, array<string, mixed>>|null
     */
    public function getPath(string $path): ?array
    {
        return $this->paths[$path] ?? null;
    }

    /**
     * Get the OpenAPI version as a semantic version array.
     *
     * @return array{major: int, minor: int, patch: int}
     */
    public function getVersionParts(): array
    {
        $parts = explode('.', $this->openapi);

        return [
            'major' => (int) ($parts[0] ?? 0),
            'minor' => (int) ($parts[1] ?? 0),
            'patch' => (int) ($parts[2] ?? 0),
        ];
    }

    /**
     * Check if this is OpenAPI 3.1.x (which uses JSON Schema 2020-12).
     */
    public function isVersion31(): bool
    {
        $parts = $this->getVersionParts();

        return $parts['major'] === 3 && $parts['minor'] === 1;
    }
}
