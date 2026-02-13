<?php

declare(strict_types=1);

namespace LaravelSpectrum\Converters;

use LaravelSpectrum\DTO\OpenApiSchema;

/**
 * Converts OpenAPI 3.0.x specifications to OpenAPI 3.1.0 format.
 *
 * @phpstan-import-type OpenApiSchemaType from OpenApiSchema
 *
 * Main changes in 3.1.0:
 * - `nullable: true` becomes `type: ['originalType', 'null']`
 * - Version string changes from '3.0.x' to '3.1.0'
 * - Full JSON Schema Draft 2020-12 compatibility
 * - webhooks section support
 * - jsonSchemaDialect declaration (defaults to JSON Schema 2020-12)
 * - contentEncoding: base64 for format: byte strings
 */
class OpenApi31Converter
{
    /**
     * Convert an OpenAPI 3.0.x specification to 3.1.0 format.
     *
     * @param  array<string, mixed>  $spec  The OpenAPI 3.0.x specification
     * @return array<string, mixed> The converted OpenAPI 3.1.0 specification
     */
    public function convert(array $spec): array
    {
        // Skip conversion if already converted (has jsonSchemaDialect) to ensure idempotency.
        // This prevents double-conversion while allowing initial conversion of specs
        // that were generated with version 3.1.0 but haven't been processed yet.
        if (isset($spec['jsonSchemaDialect'])) {
            return $spec;
        }

        // Update version string
        $spec['openapi'] = '3.1.0';

        // Explicitly declare JSON Schema 2020-12 dialect for full compatibility.
        // Without this, OpenAPI 3.1.0 defaults to the OAS dialect (https://spec.openapis.org/oas/3.1/dialect/base)
        // which has some restrictions. Setting JSON Schema 2020-12 enables all standard JSON Schema keywords.
        $spec['jsonSchemaDialect'] = 'https://json-schema.org/draft/2020-12/schema';

        // Convert paths
        if (isset($spec['paths']) && is_array($spec['paths'])) {
            $spec['paths'] = $this->convertPaths($spec['paths']);
        }

        // Convert components
        if (isset($spec['components']) && is_array($spec['components'])) {
            $spec['components'] = $this->convertComponents($spec['components']);
        }

        // Add empty webhooks section if not present (3.1.0 feature)
        if (! isset($spec['webhooks'])) {
            $spec['webhooks'] = new \stdClass;
        }

        return $spec;
    }

    /**
     * Convert paths section.
     *
     * @param  array<string, mixed>  $paths
     * @return array<string, mixed>
     */
    private function convertPaths(array $paths): array
    {
        foreach ($paths as $path => $methods) {
            if (! is_array($methods)) {
                continue;
            }

            foreach ($methods as $method => $operation) {
                if (! is_array($operation)) {
                    continue;
                }

                // Convert requestBody schemas
                if (isset($operation['requestBody']['content'])) {
                    foreach ($operation['requestBody']['content'] as $mediaType => $content) {
                        if (isset($content['schema'])) {
                            $paths[$path][$method]['requestBody']['content'][$mediaType]['schema'] =
                                $this->convertSchema($content['schema']);
                        }
                    }
                }

                // Convert response schemas
                if (isset($operation['responses']) && is_array($operation['responses'])) {
                    foreach ($operation['responses'] as $statusCode => $response) {
                        if (isset($response['content']) && is_array($response['content'])) {
                            foreach ($response['content'] as $mediaType => $content) {
                                if (isset($content['schema'])) {
                                    $paths[$path][$method]['responses'][$statusCode]['content'][$mediaType]['schema'] =
                                        $this->convertSchema($content['schema']);
                                }
                            }
                        }
                    }
                }

                // Convert parameter schemas
                if (isset($operation['parameters']) && is_array($operation['parameters'])) {
                    foreach ($operation['parameters'] as $index => $parameter) {
                        if (isset($parameter['schema'])) {
                            $paths[$path][$method]['parameters'][$index]['schema'] =
                                $this->convertSchema($parameter['schema']);
                        }
                    }
                }

                // Convert callback schemas
                if (isset($operation['callbacks']) && is_array($operation['callbacks'])) {
                    $paths[$path][$method]['callbacks'] = $this->convertCallbacks($operation['callbacks']);
                }
            }
        }

        return $paths;
    }

    /**
     * Convert components section.
     *
     * @param  array<string, mixed>  $components
     * @return array<string, mixed>
     */
    private function convertComponents(array $components): array
    {
        // Convert schemas
        if (isset($components['schemas']) && is_array($components['schemas'])) {
            foreach ($components['schemas'] as $name => $schema) {
                $components['schemas'][$name] = $this->convertSchema($schema);
            }
        }

        // Convert request bodies
        if (isset($components['requestBodies']) && is_array($components['requestBodies'])) {
            foreach ($components['requestBodies'] as $name => $requestBody) {
                if (isset($requestBody['content']) && is_array($requestBody['content'])) {
                    foreach ($requestBody['content'] as $mediaType => $content) {
                        if (isset($content['schema'])) {
                            $components['requestBodies'][$name]['content'][$mediaType]['schema'] =
                                $this->convertSchema($content['schema']);
                        }
                    }
                }
            }
        }

        // Convert responses
        if (isset($components['responses']) && is_array($components['responses'])) {
            foreach ($components['responses'] as $name => $response) {
                if (isset($response['content']) && is_array($response['content'])) {
                    foreach ($response['content'] as $mediaType => $content) {
                        if (isset($content['schema'])) {
                            $components['responses'][$name]['content'][$mediaType]['schema'] =
                                $this->convertSchema($content['schema']);
                        }
                    }
                }
            }
        }

        // Convert callbacks
        if (isset($components['callbacks']) && is_array($components['callbacks'])) {
            $components['callbacks'] = $this->convertCallbacks($components['callbacks']);
        }

        // Convert parameters
        if (isset($components['parameters']) && is_array($components['parameters'])) {
            foreach ($components['parameters'] as $name => $parameter) {
                if (isset($parameter['schema'])) {
                    $components['parameters'][$name]['schema'] =
                        $this->convertSchema($parameter['schema']);
                }
            }
        }

        return $components;
    }

    /**
     * Convert callbacks by traversing callback path items and converting their schemas to 3.1.0 format.
     *
     * @param  array<string, mixed>  $callbacks
     * @return array<string, mixed>
     */
    private function convertCallbacks(array $callbacks): array
    {
        foreach ($callbacks as $name => $callbackDef) {
            if (! is_array($callbackDef)) {
                continue;
            }

            // Skip $ref callbacks
            if (isset($callbackDef['$ref'])) {
                continue;
            }

            foreach ($callbackDef as $expression => $pathItem) {
                if (! is_array($pathItem)) {
                    continue;
                }

                foreach ($pathItem as $method => $operation) {
                    if (! is_array($operation)) {
                        continue;
                    }

                    // Convert requestBody schemas
                    if (isset($operation['requestBody']['content'])) {
                        foreach ($operation['requestBody']['content'] as $mediaType => $content) {
                            if (isset($content['schema'])) {
                                $callbacks[$name][$expression][$method]['requestBody']['content'][$mediaType]['schema'] =
                                    $this->convertSchema($content['schema']);
                            }
                        }
                    }

                    // Convert response schemas
                    if (isset($operation['responses']) && is_array($operation['responses'])) {
                        foreach ($operation['responses'] as $statusCode => $response) {
                            if (isset($response['content']) && is_array($response['content'])) {
                                foreach ($response['content'] as $mediaType => $content) {
                                    if (isset($content['schema'])) {
                                        $callbacks[$name][$expression][$method]['responses'][$statusCode]['content'][$mediaType]['schema'] =
                                            $this->convertSchema($content['schema']);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $callbacks;
    }

    /**
     * Convert a schema from OpenAPI 3.0.x to 3.1.0 format.
     *
     * @param  OpenApiSchemaType  $schema
     * @return array<string, mixed>
     */
    private function convertSchema(array $schema): array
    {
        // Convert nullable to type array
        $schema = $this->convertNullable($schema);

        // Add contentEncoding for base64 data (JSON Schema 2020-12 feature)
        $schema = $this->convertContentEncoding($schema);

        // Recursively convert nested properties
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $propName => $propSchema) {
                if (is_array($propSchema)) {
                    $schema['properties'][$propName] = $this->convertSchema($propSchema);
                }
            }
        }

        // Recursively convert items (for array types)
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->convertSchema($schema['items']);
        }

        // Recursively convert allOf
        if (isset($schema['allOf']) && is_array($schema['allOf'])) {
            foreach ($schema['allOf'] as $index => $subSchema) {
                if (is_array($subSchema)) {
                    $schema['allOf'][$index] = $this->convertSchema($subSchema);
                }
            }
        }

        // Recursively convert anyOf
        if (isset($schema['anyOf']) && is_array($schema['anyOf'])) {
            foreach ($schema['anyOf'] as $index => $subSchema) {
                if (is_array($subSchema)) {
                    $schema['anyOf'][$index] = $this->convertSchema($subSchema);
                }
            }
        }

        // Recursively convert oneOf
        if (isset($schema['oneOf']) && is_array($schema['oneOf'])) {
            foreach ($schema['oneOf'] as $index => $subSchema) {
                if (is_array($subSchema)) {
                    $schema['oneOf'][$index] = $this->convertSchema($subSchema);
                }
            }
        }

        // Recursively convert additionalProperties if it's a schema
        if (isset($schema['additionalProperties']) && is_array($schema['additionalProperties'])) {
            $schema['additionalProperties'] = $this->convertSchema($schema['additionalProperties']);
        }

        return $schema;
    }

    /**
     * Convert nullable: true to type array format.
     *
     * In OpenAPI 3.0.x: { "type": "string", "nullable": true }
     * In OpenAPI 3.1.0: { "type": ["string", "null"] }
     *
     * Note: nullable: false is also removed as the nullable keyword
     * does not exist in OpenAPI 3.1.0.
     *
     * @param  OpenApiSchemaType  $schema
     * @return array<string, mixed>
     */
    private function convertNullable(array $schema): array
    {
        // If nullable is not set, nothing to do
        if (! isset($schema['nullable'])) {
            return $schema;
        }

        // If nullable is false, just remove the key (3.1.0 doesn't use nullable)
        if ($schema['nullable'] !== true) {
            unset($schema['nullable']);

            return $schema;
        }

        // Handle nullable: true
        if (! isset($schema['type'])) {
            // Remove nullable without type conversion
            unset($schema['nullable']);

            return $schema;
        }

        $originalType = $schema['type'];

        // Handle case where type is already an array
        if (is_array($originalType)) {
            if (! in_array('null', $originalType, true)) {
                $originalType[] = 'null';
                $schema['type'] = $originalType;
            }
        } else {
            // Convert single type to array with null
            $schema['type'] = [$originalType, 'null'];
        }

        // Remove the nullable key as it's not used in 3.1.0
        unset($schema['nullable']);

        return $schema;
    }

    /**
     * Add contentEncoding for base64 encoded data.
     *
     * In JSON Schema 2020-12, contentEncoding specifies the encoding used
     * for string data. For format: byte (base64), we add contentEncoding: base64.
     *
     * @param  OpenApiSchemaType  $schema
     * @return array<string, mixed>
     */
    private function convertContentEncoding(array $schema): array
    {
        // Only process string type with byte format
        $type = $schema['type'] ?? null;
        $format = $schema['format'] ?? null;

        // Handle type arrays (e.g., ["string", "null"])
        if (is_array($type)) {
            $isString = in_array('string', $type, true);
        } else {
            $isString = $type === 'string';
        }

        // Add contentEncoding: base64 for format: byte (unless already set)
        if ($isString && $format === 'byte' && ! isset($schema['contentEncoding'])) {
            $schema['contentEncoding'] = 'base64';
        }

        return $schema;
    }
}
