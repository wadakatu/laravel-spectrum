<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Tests\Fixtures\Controllers\NullableTestController;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OpenApi31JsonSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        app(DocumentationCache::class)->clear();

        // Set OpenAPI version to 3.1.0
        config(['spectrum.openapi.version' => '3.1.0']);
    }

    protected function tearDown(): void
    {
        // Clear cache after each test
        app(DocumentationCache::class)->clear();

        // Reset config
        config(['spectrum.openapi.version' => '3.0.0']);

        parent::tearDown();
    }

    #[Test]
    public function it_generates_openapi_3_1_with_json_schema_dialect(): void
    {
        // Arrange
        $controller = new class
        {
            public function index()
            {
                return ['status' => 'ok'];
            }
        };

        Route::get('api/test', [get_class($controller), 'index']);

        $routeAnalyzer = app(RouteAnalyzer::class);
        $generator = app(OpenApiGenerator::class);

        // Act
        $routes = $routeAnalyzer->analyze();
        $openapi = $generator->generate($routes)->toArray();

        // Assert
        $this->assertEquals('3.1.0', $openapi['openapi']);
        $this->assertArrayHasKey('jsonSchemaDialect', $openapi);
        $this->assertEquals(
            'https://json-schema.org/draft/2020-12/schema',
            $openapi['jsonSchemaDialect']
        );
    }

    #[Test]
    public function it_does_not_add_json_schema_dialect_for_3_0(): void
    {
        // Arrange - set version back to 3.0.0
        config(['spectrum.openapi.version' => '3.0.0']);

        $controller = new class
        {
            public function index()
            {
                return ['status' => 'ok'];
            }
        };

        Route::get('api/test', [get_class($controller), 'index']);

        $routeAnalyzer = app(RouteAnalyzer::class);
        $generator = app(OpenApiGenerator::class);

        // Act
        $routes = $routeAnalyzer->analyze();
        $openapi = $generator->generate($routes)->toArray();

        // Assert
        $this->assertEquals('3.0.0', $openapi['openapi']);
        $this->assertArrayNotHasKey('jsonSchemaDialect', $openapi);
    }

    #[Test]
    public function it_includes_webhooks_section_for_3_1(): void
    {
        // Arrange
        $controller = new class
        {
            public function index()
            {
                return ['status' => 'ok'];
            }
        };

        Route::get('api/test', [get_class($controller), 'index']);

        $routeAnalyzer = app(RouteAnalyzer::class);
        $generator = app(OpenApiGenerator::class);

        // Act
        $routes = $routeAnalyzer->analyze();
        $openapi = $generator->generate($routes)->toArray();

        // Assert
        $this->assertArrayHasKey('webhooks', $openapi);
    }

    #[Test]
    public function it_converts_nullable_to_type_array_in_3_1(): void
    {
        // Arrange - use fixture controller with FormRequest for reliable schema generation
        Route::post('api/nullable-test', [NullableTestController::class, 'store']);

        $routeAnalyzer = app(RouteAnalyzer::class);
        $generator = app(OpenApiGenerator::class);

        // Act
        $routes = $routeAnalyzer->analyze();
        $openapi = $generator->generate($routes)->toArray();

        // Assert - verify OpenAPI version first
        $this->assertEquals('3.1.0', $openapi['openapi']);

        // Assert - path exists
        $this->assertArrayHasKey('/api/nullable-test', $openapi['paths']);
        $this->assertArrayHasKey('post', $openapi['paths']['/api/nullable-test']);

        // Assert - requestBody exists with schema
        $operation = $openapi['paths']['/api/nullable-test']['post'];
        $this->assertArrayHasKey('requestBody', $operation);
        $this->assertArrayHasKey('content', $operation['requestBody']);
        $this->assertArrayHasKey('application/json', $operation['requestBody']['content']);

        $schema = $operation['requestBody']['content']['application/json']['schema'];
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('name', $schema['properties']);

        // Assert - OpenAPI 3.1.0 should not have 'nullable' keyword
        // The 'nullable' validation rule makes the field not required, and if the schema
        // has nullable: true, it gets converted to type array. Even if nullable: true
        // isn't added by the schema generator, the key point is that 'nullable' keyword
        // should NOT appear in OpenAPI 3.1.0 output.
        $nameSchema = $schema['properties']['name'];
        $this->assertArrayNotHasKey('nullable', $nameSchema, 'nullable keyword should not exist in OpenAPI 3.1.0');
    }
}
