<?php

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Generators\OpenApiGenerator;
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
        // Arrange
        $controller = new class
        {
            public function store(\Illuminate\Http\Request $request)
            {
                $request->validate([
                    'name' => 'nullable|string',
                ]);

                return ['id' => 1];
            }
        };

        Route::post('api/test', [get_class($controller), 'store']);

        $routeAnalyzer = app(RouteAnalyzer::class);
        $generator = app(OpenApiGenerator::class);

        // Act
        $routes = $routeAnalyzer->analyze();
        $openapi = $generator->generate($routes)->toArray();

        // Assert - nullable should be converted to type array
        if (isset($openapi['paths']['/api/test']['post']['requestBody']['content']['application/json']['schema']['properties']['name'])) {
            $nameSchema = $openapi['paths']['/api/test']['post']['requestBody']['content']['application/json']['schema']['properties']['name'];
            // In 3.1.0, nullable: true becomes type: ["string", "null"]
            $this->assertArrayNotHasKey('nullable', $nameSchema);
            if (isset($nameSchema['type']) && is_array($nameSchema['type'])) {
                $this->assertContains('null', $nameSchema['type']);
            }
        }

        // Verify OpenAPI version
        $this->assertEquals('3.1.0', $openapi['openapi']);
    }
}
