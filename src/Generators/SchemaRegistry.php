<?php

declare(strict_types=1);

namespace LaravelSpectrum\Generators;

/**
 * Registry for OpenAPI schema references.
 *
 * Manages schema definitions that will be placed in components.schemas,
 * allowing $ref references to be used instead of inline schemas.
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
     * Register a schema with the given name.
     *
     * @param  string  $name  Schema name (e.g., "UserResource")
     * @param  array<string, mixed>  $schema  Schema definition
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
     * Get a $ref reference for a registered schema.
     *
     * @return array<string, string> Reference array with '$ref' key
     */
    public function getRef(string $name): array
    {
        return ['$ref' => '#/components/schemas/'.$name];
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
     * Clear all registered schemas.
     */
    public function clear(): void
    {
        $this->schemas = [];
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
     * @param  array<string, mixed>  $schema  Schema definition
     * @return array<string, string> Reference array with '$ref' key
     */
    public function registerAndGetRef(string $className, array $schema): array
    {
        $schemaName = $this->extractSchemaName($className);
        $this->register($schemaName, $schema);

        return $this->getRef($schemaName);
    }
}
