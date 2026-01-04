<?php

declare(strict_types=1);

namespace LaravelSpectrum\Generators;

use LaravelSpectrum\DTO\OpenApiSchema;

/**
 * Registry for OpenAPI schema references.
 *
 * Manages schema definitions that will be placed in components.schemas,
 * allowing $ref references to be used instead of inline schemas.
 *
 * @phpstan-import-type OpenApiSchemaType from OpenApiSchema
 */
class SchemaRegistry
{
    /**
     * Registered schemas.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $schemas = [];

    /**
     * Pending references (schemas referenced but may not be registered yet).
     *
     * @var array<string, bool>
     */
    private array $pendingReferences = [];

    /**
     * Register a schema with the given name.
     *
     * @param  string  $name  Schema name (e.g., "UserResource")
     * @param  OpenApiSchemaType  $schema  Schema definition
     */
    public function register(string $name, array $schema): void
    {
        $this->schemas[$name] = $schema;
    }

    /**
     * Check if a schema with the given name is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->schemas[$name]);
    }

    /**
     * Get a schema by name.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $name): ?array
    {
        return $this->schemas[$name] ?? null;
    }

    /**
     * Get a $ref reference for a schema.
     *
     * Tracks the reference for later validation.
     *
     * @return array<string, string> Reference array with '$ref' key
     */
    public function getRef(string $name): array
    {
        $this->pendingReferences[$name] = true;

        return ['$ref' => '#/components/schemas/'.$name];
    }

    /**
     * Get all pending references.
     *
     * @return array<int, string>
     */
    public function getPendingReferences(): array
    {
        return array_keys($this->pendingReferences);
    }

    /**
     * Validate that all referenced schemas are registered.
     *
     * @return array<int, string> List of broken (unregistered) references
     */
    public function validateReferences(): array
    {
        $brokenRefs = [];

        foreach ($this->pendingReferences as $name => $referenced) {
            if (! $this->has($name)) {
                $brokenRefs[] = $name;
            }
        }

        return $brokenRefs;
    }

    /**
     * Get all registered schemas.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->schemas;
    }

    /**
     * Clear all registered schemas and pending references.
     */
    public function clear(): void
    {
        $this->schemas = [];
        $this->pendingReferences = [];
    }

    /**
     * Extract a schema name from a fully qualified class name.
     *
     * @param  string  $className  Fully qualified class name (e.g., "App\Http\Resources\UserResource")
     * @return string Schema name (e.g., "UserResource")
     */
    public function extractSchemaName(string $className): string
    {
        $parts = explode('\\', $className);

        return end($parts);
    }

    /**
     * Register a schema and return its $ref reference.
     *
     * @param  string  $className  Fully qualified class name
     * @param  OpenApiSchemaType  $schema  Schema definition
     * @return array<string, string> Reference array with '$ref' key
     */
    public function registerAndGetRef(string $className, array $schema): array
    {
        $schemaName = $this->extractSchemaName($className);
        $this->register($schemaName, $schema);

        return $this->getRef($schemaName);
    }
}
