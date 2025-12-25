<?php

namespace LaravelSpectrum\Tests\Support;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use PHPUnit\Framework\Assert;

/**
 * Trait for validating OpenAPI specifications in tests.
 *
 * This trait provides methods to validate that generated OpenAPI specs
 * are structurally correct and conform to the OpenAPI 3.0.x/3.1.x specification.
 * Uses devizzent/cebe-php-openapi for validation.
 */
trait ValidatesOpenApi
{
    /**
     * Assert that the given array is a valid OpenAPI specification.
     *
     * @param  array  $spec  The OpenAPI specification as an array
     * @param  string  $message  Optional failure message
     */
    protected function assertValidOpenApiSpec(array $spec, string $message = ''): void
    {
        $json = json_encode($spec, JSON_THROW_ON_ERROR);
        $openapi = Reader::readFromJson($json);

        Assert::assertInstanceOf(
            OpenApi::class,
            $openapi,
            $message ?: 'Failed to parse OpenAPI specification'
        );

        // Perform basic validation
        $isValid = $openapi->validate();
        $errors = $openapi->getErrors();

        Assert::assertTrue(
            $isValid,
            $message ?: 'OpenAPI specification validation failed: '.implode(', ', $errors)
        );
    }

    /**
     * Assert that the OpenAPI spec has the required root elements.
     *
     * @param  array  $spec  The OpenAPI specification as an array
     */
    protected function assertOpenApiHasRequiredElements(array $spec): void
    {
        Assert::assertArrayHasKey('openapi', $spec, 'OpenAPI spec must have "openapi" version field');
        Assert::assertArrayHasKey('info', $spec, 'OpenAPI spec must have "info" section');
        Assert::assertArrayHasKey('paths', $spec, 'OpenAPI spec must have "paths" section');

        // Validate info section
        Assert::assertArrayHasKey('title', $spec['info'], 'OpenAPI info must have "title"');
        Assert::assertArrayHasKey('version', $spec['info'], 'OpenAPI info must have "version"');

        // Validate OpenAPI version format
        Assert::assertMatchesRegularExpression(
            '/^3\.(0|1)\.\d+$/',
            $spec['openapi'],
            'OpenAPI version must be 3.0.x or 3.1.x'
        );
    }

    /**
     * Assert that a specific path exists and has valid operations.
     *
     * @param  array  $spec  The OpenAPI specification as an array
     * @param  string  $path  The path to validate (e.g., '/api/users')
     * @param  array  $methods  Expected HTTP methods (e.g., ['get', 'post'])
     */
    protected function assertValidPath(array $spec, string $path, array $methods = []): void
    {
        Assert::assertArrayHasKey('paths', $spec);
        Assert::assertArrayHasKey(
            $path,
            $spec['paths'],
            "Path '{$path}' not found. Available paths: ".implode(', ', array_keys($spec['paths']))
        );

        foreach ($methods as $method) {
            $method = strtolower($method);
            Assert::assertArrayHasKey(
                $method,
                $spec['paths'][$path],
                "Method '{$method}' not found for path '{$path}'"
            );

            // Each operation should have responses
            Assert::assertArrayHasKey(
                'responses',
                $spec['paths'][$path][$method],
                "Operation '{$method}' on '{$path}' must have 'responses'"
            );
        }
    }

    /**
     * Assert that the OpenAPI spec has valid component schemas.
     *
     * @param  array  $spec  The OpenAPI specification as an array
     * @param  array  $expectedSchemas  List of expected schema names
     */
    protected function assertHasComponentSchemas(array $spec, array $expectedSchemas): void
    {
        if (empty($expectedSchemas)) {
            return;
        }

        Assert::assertArrayHasKey('components', $spec, 'OpenAPI spec must have "components" for schemas');
        Assert::assertArrayHasKey('schemas', $spec['components'], 'Components must have "schemas"');

        foreach ($expectedSchemas as $schemaName) {
            Assert::assertArrayHasKey(
                $schemaName,
                $spec['components']['schemas'],
                "Schema '{$schemaName}' not found in components"
            );
        }
    }

    /**
     * Assert that a schema has the expected properties with correct types.
     *
     * @param  array  $schema  The schema to validate
     * @param  array  $expectedProperties  Map of property name => expected type
     */
    protected function assertSchemaHasProperties(array $schema, array $expectedProperties): void
    {
        Assert::assertArrayHasKey('properties', $schema, 'Schema must have properties');

        foreach ($expectedProperties as $property => $expectedType) {
            Assert::assertArrayHasKey(
                $property,
                $schema['properties'],
                "Property '{$property}' not found in schema"
            );

            if ($expectedType !== null) {
                Assert::assertEquals(
                    $expectedType,
                    $schema['properties'][$property]['type'] ?? null,
                    "Property '{$property}' should have type '{$expectedType}'"
                );
            }
        }
    }

    /**
     * Assert that a request body is valid and has the expected content type.
     *
     * @param  array  $operation  The operation containing the request body
     * @param  string  $contentType  Expected content type (default: application/json)
     */
    protected function assertValidRequestBody(array $operation, string $contentType = 'application/json'): void
    {
        Assert::assertArrayHasKey('requestBody', $operation, 'Operation must have requestBody');
        Assert::assertArrayHasKey('content', $operation['requestBody'], 'Request body must have content');
        Assert::assertArrayHasKey(
            $contentType,
            $operation['requestBody']['content'],
            "Request body must support content type '{$contentType}'"
        );
        Assert::assertArrayHasKey(
            'schema',
            $operation['requestBody']['content'][$contentType],
            'Request body content must have schema'
        );
    }

    /**
     * Assert that an operation has valid responses.
     *
     * @param  array  $operation  The operation to validate
     * @param  array  $expectedStatusCodes  List of expected status codes
     */
    protected function assertValidResponses(array $operation, array $expectedStatusCodes = [200]): void
    {
        Assert::assertArrayHasKey('responses', $operation, 'Operation must have responses');

        foreach ($expectedStatusCodes as $statusCode) {
            Assert::assertArrayHasKey(
                (string) $statusCode,
                $operation['responses'],
                "Response for status code {$statusCode} not found"
            );
        }
    }

    /**
     * Get a parsed OpenAPI object from an array spec.
     *
     * @param  array  $spec  The OpenAPI specification as an array
     * @return OpenApi The parsed OpenAPI object
     */
    protected function parseOpenApiSpec(array $spec): OpenApi
    {
        $json = json_encode($spec, JSON_THROW_ON_ERROR);

        return Reader::readFromJson($json);
    }
}
