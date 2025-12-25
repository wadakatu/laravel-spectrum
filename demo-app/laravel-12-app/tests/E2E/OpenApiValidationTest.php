<?php

namespace Tests\E2E;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * E2E tests validating the generated OpenAPI spec against the OpenAPI schema.
 *
 * Uses cebe-php-openapi (devizzent fork) for validation, which supports both
 * OpenAPI 3.0.x and 3.1.x specifications. The fork maintains the original
 * cebe\openapi namespace for compatibility.
 */
class OpenApiValidationTest extends TestCase
{
    private string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputPath = storage_path('app/spectrum');

        if (File::isDirectory($this->outputPath)) {
            File::cleanDirectory($this->outputPath);
        }

        // Generate the spec before each validation test
        Artisan::call('spectrum:generate');
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->outputPath)) {
            File::cleanDirectory($this->outputPath);
        }

        parent::tearDown();
    }

    public function test_generated_spec_is_valid_openapi(): void
    {
        $spec = $this->parseOpenApiSpec();

        $this->assertInstanceOf(OpenApi::class, $spec);

        $isValid = $spec->validate();
        $errors = $spec->getErrors();

        $this->assertTrue(
            $isValid,
            'OpenAPI specification validation failed: '.implode(', ', $errors)
        );
    }

    public function test_all_operations_have_operation_id(): void
    {
        $spec = $this->parseOpenApiSpec();

        foreach ($spec->paths as $path => $pathItem) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (isset($pathItem->$method)) {
                    $operation = $pathItem->$method;
                    $this->assertNotEmpty(
                        $operation->operationId,
                        "Operation {$method} on {$path} should have operationId"
                    );
                }
            }
        }
    }

    public function test_all_operations_have_tags(): void
    {
        $spec = $this->parseOpenApiSpec();

        foreach ($spec->paths as $path => $pathItem) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (isset($pathItem->$method)) {
                    $operation = $pathItem->$method;
                    $this->assertNotEmpty(
                        $operation->tags,
                        "Operation {$method} on {$path} should have tags"
                    );
                }
            }
        }
    }

    public function test_request_body_schemas_are_valid(): void
    {
        $spec = $this->parseOpenApiSpec();
        $validContentTypes = ['application/json', 'multipart/form-data'];

        foreach ($spec->paths as $path => $pathItem) {
            foreach (['post', 'put', 'patch'] as $method) {
                if (isset($pathItem->$method) && isset($pathItem->$method->requestBody)) {
                    $requestBody = $pathItem->$method->requestBody;

                    $this->assertNotNull(
                        $requestBody->content,
                        "Request body for {$method} on {$path} should have content"
                    );

                    // Check that at least one valid content type exists
                    $contentTypes = array_keys((array) $requestBody->content);
                    $hasValidContentType = ! empty(array_intersect($contentTypes, $validContentTypes));

                    $this->assertTrue(
                        $hasValidContentType,
                        "Request body for {$method} on {$path} should have application/json or multipart/form-data content"
                    );
                }
            }
        }
    }

    public function test_response_schemas_have_proper_status_codes(): void
    {
        $spec = $this->parseOpenApiSpec();

        foreach ($spec->paths as $path => $pathItem) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (isset($pathItem->$method)) {
                    $operation = $pathItem->$method;
                    $responses = $operation->responses;

                    $this->assertNotEmpty(
                        $responses,
                        "Operation {$method} on {$path} should have responses"
                    );

                    // Check for valid HTTP status codes
                    foreach ($responses as $statusCode => $response) {
                        $this->assertTrue(
                            is_numeric($statusCode) || $statusCode === 'default',
                            "Response status code '{$statusCode}' should be numeric or 'default'"
                        );
                    }
                }
            }
        }
    }

    public function test_path_parameters_have_required_fields(): void
    {
        $spec = $this->parseOpenApiSpec();

        foreach ($spec->paths as $path => $pathItem) {
            // Check for path parameters in the URL
            preg_match_all('/\{([^}]+)\}/', $path, $matches);
            $expectedParams = $matches[1] ?? [];

            if (empty($expectedParams)) {
                continue;
            }

            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (isset($pathItem->$method)) {
                    $operation = $pathItem->$method;

                    $this->assertNotEmpty(
                        $operation->parameters,
                        "Operation {$method} on {$path} should have parameters for path variables"
                    );

                    // Check each path parameter is defined
                    $definedParams = [];
                    foreach ($operation->parameters as $param) {
                        if ($param->in === 'path') {
                            $definedParams[] = $param->name;

                            // Path parameters must be required
                            $this->assertTrue(
                                $param->required,
                                "Path parameter '{$param->name}' on {$path} should be required"
                            );

                            // Must have schema
                            $this->assertNotNull(
                                $param->schema,
                                "Path parameter '{$param->name}' on {$path} should have schema"
                            );
                        }
                    }

                    foreach ($expectedParams as $expectedParam) {
                        // Remove optional marker
                        $paramName = rtrim($expectedParam, '?');
                        $this->assertContains(
                            $paramName,
                            $definedParams,
                            "Path parameter '{$paramName}' should be defined for {$method} on {$path}"
                        );
                    }
                }
            }
        }
    }

    public function test_servers_are_properly_configured(): void
    {
        $spec = $this->parseOpenApiSpec();

        $this->assertNotEmpty($spec->servers, 'OpenAPI spec should have servers defined');

        foreach ($spec->servers as $server) {
            $this->assertNotEmpty($server->url, 'Server should have URL');
        }
    }

    public function test_security_schemes_are_valid(): void
    {
        $spec = $this->parseOpenApiSpec();

        // The demo app uses sanctum authentication
        if (isset($spec->components) && isset($spec->components->securitySchemes)) {
            foreach ($spec->components->securitySchemes as $name => $scheme) {
                $this->assertNotEmpty(
                    $scheme->type,
                    "Security scheme '{$name}' should have type"
                );
            }
        }
    }

    /**
     * Parse the generated OpenAPI spec.
     */
    private function parseOpenApiSpec(): OpenApi
    {
        $content = File::get($this->outputPath.'/openapi.json');

        return Reader::readFromJson($content);
    }
}
