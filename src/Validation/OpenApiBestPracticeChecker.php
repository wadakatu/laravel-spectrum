<?php

declare(strict_types=1);

namespace LaravelSpectrum\Validation;

final class OpenApiBestPracticeChecker
{
    /**
     * @var array<int, string>
     */
    private const HTTP_METHODS = [
        'get',
        'put',
        'post',
        'delete',
        'options',
        'head',
        'patch',
        'trace',
    ];

    /**
     * @var array<int, string>
     */
    private const STANDARD_SCHEMA_KEYWORDS = [
        '$ref',
        '$id',
        '$defs',
        '$schema',
        'type',
        'format',
        'title',
        'description',
        'default',
        'deprecated',
        'readOnly',
        'writeOnly',
        'nullable',
        'enum',
        'const',
        'example',
        'examples',
        'externalDocs',
        'xml',
        'discriminator',
        'allOf',
        'anyOf',
        'oneOf',
        'not',
        'if',
        'then',
        'else',
        'contains',
        'items',
        'prefixItems',
        'properties',
        'required',
        'additionalProperties',
        'patternProperties',
        'unevaluatedProperties',
        'propertyNames',
        'dependentRequired',
        'dependentSchemas',
        'minProperties',
        'maxProperties',
        'minimum',
        'maximum',
        'exclusiveMinimum',
        'exclusiveMaximum',
        'multipleOf',
        'minLength',
        'maxLength',
        'pattern',
        'contentEncoding',
        'contentMediaType',
        'minItems',
        'maxItems',
        'uniqueItems',
        'minContains',
        'maxContains',
    ];

    /**
     * @param  array<string, mixed>  $spec
     * @return array<int, string>
     */
    public function check(array $spec): array
    {
        $warnings = [];

        $this->checkOperationDescriptions($spec, $warnings);
        $this->checkMediaTypeExamples($spec, $warnings);
        $this->checkResponseContentTypes($spec, $warnings);
        $this->checkSchemas($spec, $warnings);

        return array_keys($warnings);
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, bool>  $warnings
     */
    private function checkOperationDescriptions(array $spec, array &$warnings): void
    {
        foreach ($this->collectOperationContexts($spec) as $operationContext) {
            $description = $operationContext['operation']['description'] ?? null;
            if (is_string($description) && trim($description) !== '') {
                continue;
            }

            $this->warn(
                $warnings,
                sprintf('%s %s is missing operation description', strtoupper($operationContext['method']), $operationContext['path'])
            );
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, bool>  $warnings
     */
    private function checkMediaTypeExamples(array $spec, array &$warnings): void
    {
        foreach ($this->collectOperationContexts($spec) as $operationContext) {
            $operation = $operationContext['operation'];
            $label = strtoupper($operationContext['method']).' '.$operationContext['path'];

            if (isset($operation['requestBody']) && is_array($operation['requestBody'])) {
                $requestBody = $operation['requestBody'];
                $content = $requestBody['content'] ?? null;
                if (is_array($content)) {
                    foreach ($content as $mediaType => $mediaTypeObject) {
                        if (! is_array($mediaTypeObject)) {
                            continue;
                        }

                        if ($this->mediaTypeHasExample($mediaTypeObject)) {
                            continue;
                        }

                        $this->warn($warnings, sprintf('%s requestBody %s is missing example data', $label, $mediaType));
                    }
                }
            }

            $responses = $operation['responses'] ?? null;
            if (! is_array($responses)) {
                continue;
            }

            foreach ($responses as $status => $response) {
                if (! is_array($response)) {
                    continue;
                }

                $content = $response['content'] ?? null;
                if (! is_array($content)) {
                    continue;
                }

                foreach ($content as $mediaType => $mediaTypeObject) {
                    if (! is_array($mediaTypeObject)) {
                        continue;
                    }

                    if ($this->mediaTypeHasExample($mediaTypeObject)) {
                        continue;
                    }

                    $this->warn(
                        $warnings,
                        sprintf('%s response %s (%s) is missing example data', $label, (string) $status, $mediaType)
                    );
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, bool>  $warnings
     */
    private function checkResponseContentTypes(array $spec, array &$warnings): void
    {
        foreach ($this->collectOperationContexts($spec) as $operationContext) {
            $operation = $operationContext['operation'];
            $label = strtoupper($operationContext['method']).' '.$operationContext['path'];

            $responses = $operation['responses'] ?? null;
            if (! is_array($responses)) {
                continue;
            }

            foreach ($responses as $status => $response) {
                if (! is_array($response)) {
                    continue;
                }

                $content = $response['content'] ?? null;
                if (! is_array($content)) {
                    continue;
                }

                foreach (array_keys($content) as $mediaType) {
                    if (! is_string($mediaType)) {
                        continue;
                    }

                    if (preg_match('/^[a-z0-9!#$&^_.+-]+\/[a-z0-9!#$&^_.+-]+$/i', $mediaType) === 1) {
                        continue;
                    }

                    $this->warn(
                        $warnings,
                        sprintf('%s response %s uses non-standard content type "%s"', $label, (string) $status, $mediaType)
                    );
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, bool>  $warnings
     */
    private function checkSchemas(array $spec, array &$warnings): void
    {
        foreach ($this->collectSchemaContexts($spec) as $schemaContext) {
            $this->inspectSchema($schemaContext['schema'], $schemaContext['pointer'], $warnings);
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, bool>  $warnings
     */
    private function inspectSchema(array $schema, string $pointer, array &$warnings): void
    {
        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            return;
        }

        $type = $schema['type'] ?? null;
        if ($type === 'object') {
            $properties = $schema['properties'] ?? null;
            $hasProperties = is_array($properties) && $properties !== [];
            $hasComposition =
                (isset($schema['allOf']) && is_array($schema['allOf']) && $schema['allOf'] !== []) ||
                (isset($schema['oneOf']) && is_array($schema['oneOf']) && $schema['oneOf'] !== []) ||
                (isset($schema['anyOf']) && is_array($schema['anyOf']) && $schema['anyOf'] !== []);
            $hasAdditionalProperties = array_key_exists('additionalProperties', $schema);

            if (! $hasProperties && ! $hasComposition && ! $hasAdditionalProperties) {
                $this->warn($warnings, sprintf('%s defines an empty object schema without properties', $pointer));
            }

            if ($hasProperties && count($properties) >= 10 && ! str_starts_with($pointer, '/components/schemas/')) {
                $this->warn($warnings, sprintf('%s uses a large inline schema; consider extracting it to components/schemas', $pointer));
            }
        }

        if (isset($schema['enum']) && is_array($schema['enum'])) {
            foreach ($schema['enum'] as $index => $value) {
                if (! is_string($value)) {
                    continue;
                }

                if (preg_match('/(<\?php|::|->|\$[A-Za-z_]|=>|\bfunction\s*\(|\bnew\s+[A-Za-z_])/', $value) === 1) {
                    $this->warn(
                        $warnings,
                        sprintf('%s enum[%d] contains a potential code fragment', $pointer, $index)
                    );
                }
            }
        }

        foreach (array_keys($schema) as $key) {
            if (! is_string($key)) {
                continue;
            }

            if (in_array($key, self::STANDARD_SCHEMA_KEYWORDS, true) || $this->isExtensionKey($key)) {
                continue;
            }

            $this->warn($warnings, sprintf('%s uses non-standard schema keyword "%s" (use x- prefix for extensions)', $pointer, $key));
        }

        $this->inspectSchemaChildren($schema, $pointer, $warnings);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, bool>  $warnings
     */
    private function inspectSchemaChildren(array $schema, string $pointer, array &$warnings): void
    {
        foreach (['allOf', 'anyOf', 'oneOf', 'prefixItems'] as $keyword) {
            if (! isset($schema[$keyword]) || ! is_array($schema[$keyword])) {
                continue;
            }

            foreach ($schema[$keyword] as $index => $childSchema) {
                if (! is_array($childSchema)) {
                    continue;
                }

                $this->inspectSchema($childSchema, $pointer.'/'.$keyword.'/'.$index, $warnings);
            }
        }

        foreach (['not', 'if', 'then', 'else', 'contains', 'items'] as $keyword) {
            if (! isset($schema[$keyword]) || ! is_array($schema[$keyword])) {
                continue;
            }

            $this->inspectSchema($schema[$keyword], $pointer.'/'.$keyword, $warnings);
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $propertyName => $propertySchema) {
                if (! is_array($propertySchema)) {
                    continue;
                }

                $this->inspectSchema($propertySchema, $pointer.'/properties/'.(string) $propertyName, $warnings);
            }
        }

        if (isset($schema['dependentSchemas']) && is_array($schema['dependentSchemas'])) {
            foreach ($schema['dependentSchemas'] as $propertyName => $dependentSchema) {
                if (! is_array($dependentSchema)) {
                    continue;
                }

                $this->inspectSchema($dependentSchema, $pointer.'/dependentSchemas/'.(string) $propertyName, $warnings);
            }
        }

        if (isset($schema['additionalProperties']) && is_array($schema['additionalProperties'])) {
            $this->inspectSchema($schema['additionalProperties'], $pointer.'/additionalProperties', $warnings);
        }
    }

    /**
     * @param  array<string, mixed>  $mediaTypeObject
     */
    private function mediaTypeHasExample(array $mediaTypeObject): bool
    {
        if (array_key_exists('example', $mediaTypeObject) || array_key_exists('examples', $mediaTypeObject)) {
            return true;
        }

        $schema = $mediaTypeObject['schema'] ?? null;
        if (! is_array($schema)) {
            return false;
        }

        return $this->schemaHasExample($schema);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function schemaHasExample(array $schema): bool
    {
        return array_key_exists('example', $schema)
            || array_key_exists('examples', $schema)
            || array_key_exists('default', $schema);
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<int, array{path: string, method: string, operation: array<string, mixed>}>
     */
    private function collectOperationContexts(array $spec): array
    {
        $contexts = [];

        $paths = $spec['paths'] ?? null;
        if (! is_array($paths)) {
            return $contexts;
        }

        foreach ($paths as $path => $pathItem) {
            if (! is_string($path) || ! is_array($pathItem)) {
                continue;
            }

            foreach (self::HTTP_METHODS as $method) {
                if (! isset($pathItem[$method]) || ! is_array($pathItem[$method])) {
                    continue;
                }

                $contexts[] = [
                    'path' => $path,
                    'method' => $method,
                    'operation' => $pathItem[$method],
                ];
            }
        }

        return $contexts;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<int, array{pointer: string, schema: array<string, mixed>}>
     */
    private function collectSchemaContexts(array $spec): array
    {
        $contexts = [];

        $componentSchemas = $spec['components']['schemas'] ?? null;
        if (is_array($componentSchemas)) {
            foreach ($componentSchemas as $schemaName => $schema) {
                if (! is_array($schema)) {
                    continue;
                }

                $contexts[] = [
                    'pointer' => '/components/schemas/'.(string) $schemaName,
                    'schema' => $schema,
                ];
            }
        }

        $paths = $spec['paths'] ?? null;
        if (! is_array($paths)) {
            return $contexts;
        }

        foreach ($paths as $path => $pathItem) {
            if (! is_string($path) || ! is_array($pathItem)) {
                continue;
            }

            foreach (self::HTTP_METHODS as $method) {
                $operation = $pathItem[$method] ?? null;
                if (! is_array($operation)) {
                    continue;
                }

                $operationPointer = '/paths/'.$path.'/'.$method;

                if (isset($operation['parameters']) && is_array($operation['parameters'])) {
                    foreach ($operation['parameters'] as $index => $parameter) {
                        if (! is_array($parameter) || ! isset($parameter['schema']) || ! is_array($parameter['schema'])) {
                            continue;
                        }

                        $contexts[] = [
                            'pointer' => $operationPointer.'/parameters/'.$index.'/schema',
                            'schema' => $parameter['schema'],
                        ];
                    }
                }

                if (isset($operation['requestBody']) && is_array($operation['requestBody'])) {
                    $requestContent = $operation['requestBody']['content'] ?? null;
                    if (is_array($requestContent)) {
                        foreach ($requestContent as $mediaType => $mediaTypeObject) {
                            if (! is_array($mediaTypeObject) || ! isset($mediaTypeObject['schema']) || ! is_array($mediaTypeObject['schema'])) {
                                continue;
                            }

                            $contexts[] = [
                                'pointer' => $operationPointer.'/requestBody/content/'.(string) $mediaType.'/schema',
                                'schema' => $mediaTypeObject['schema'],
                            ];
                        }
                    }
                }

                if (isset($operation['responses']) && is_array($operation['responses'])) {
                    foreach ($operation['responses'] as $status => $response) {
                        if (! is_array($response)) {
                            continue;
                        }

                        $responseContent = $response['content'] ?? null;
                        if (! is_array($responseContent)) {
                            continue;
                        }

                        foreach ($responseContent as $mediaType => $mediaTypeObject) {
                            if (! is_array($mediaTypeObject) || ! isset($mediaTypeObject['schema']) || ! is_array($mediaTypeObject['schema'])) {
                                continue;
                            }

                            $contexts[] = [
                                'pointer' => $operationPointer.'/responses/'.(string) $status.'/content/'.(string) $mediaType.'/schema',
                                'schema' => $mediaTypeObject['schema'],
                            ];
                        }
                    }
                }
            }
        }

        return $contexts;
    }

    /**
     * @param  array<string, bool>  $warnings
     */
    private function warn(array &$warnings, string $message): void
    {
        $warnings[$message] = true;
    }

    private function isExtensionKey(string $key): bool
    {
        return str_starts_with($key, 'x-');
    }
}
