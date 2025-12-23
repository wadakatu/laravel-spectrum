<?php

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Tests\Fixtures\Controllers\ProfileController;
use LaravelSpectrum\Tests\Fixtures\Controllers\UserController;
use LaravelSpectrum\Tests\Fixtures\StoreUserRequest;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class OpenApiGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        app(DocumentationCache::class)->clear();
    }

    protected function tearDown(): void
    {
        // Clear cache after each test
        app(DocumentationCache::class)->clear();

        parent::tearDown();
    }

    #[Test]
    public function it_generates_valid_openapi_specification()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);
        Route::post('api/users', [UserController::class, 'store']);
        Route::get('api/users/{user}', [UserController::class, 'show']);

        $routeAnalyzer = app(RouteAnalyzer::class);
        $generator = app(OpenApiGenerator::class);

        // Act
        $routes = $routeAnalyzer->analyze();
        $openapi = $generator->generate($routes);

        // Assert
        $this->assertEquals('3.0.0', $openapi['openapi']);
        $this->assertArrayHasKey('info', $openapi);
        $this->assertArrayHasKey('paths', $openapi);
        $this->assertArrayHasKey('/api/users', $openapi['paths']);
        $this->assertArrayHasKey('get', $openapi['paths']['/api/users']);
        $this->assertArrayHasKey('post', $openapi['paths']['/api/users']);
    }

    #[Test]
    public function it_includes_request_body_for_post_requests()
    {
        // Arrange
        Route::post('api/users', [UserController::class, 'store']);

        // Mock controller analysis to return FormRequest
        $this->mockControllerAnalysis('store', [
            'formRequest' => StoreUserRequest::class,
        ]);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert
        $operation = $openapi['paths']['/api/users']['post'];
        $this->assertArrayHasKey('requestBody', $operation);
        $this->assertTrue($operation['requestBody']['required']);
        $this->assertArrayHasKey('application/json', $operation['requestBody']['content']);
    }

    #[Test]
    public function it_adds_security_requirements_for_authenticated_routes()
    {
        // Arrange
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('api/profile', [ProfileController::class, 'show']);
        });

        // Act
        $openapi = $this->generateOpenApi();

        // Assert
        $operation = $openapi['paths']['/api/profile']['get'];
        $this->assertArrayHasKey('security', $operation);
        $this->assertArrayHasKey('sanctumAuth', $operation['security'][0]);
    }

    #[Test]
    public function it_generates_proper_path_parameters()
    {
        // Arrange
        Route::get('api/users/{user}', [UserController::class, 'show']);
        Route::put('api/posts/{post}/comments/{comment?}', [UserController::class, 'update']);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert
        $userOperation = $openapi['paths']['/api/users/{user}']['get'];
        $this->assertArrayHasKey('parameters', $userOperation);
        $this->assertCount(1, $userOperation['parameters']);
        $this->assertEquals('user', $userOperation['parameters'][0]['name']);
        $this->assertEquals('path', $userOperation['parameters'][0]['in']);
        $this->assertTrue($userOperation['parameters'][0]['required']);

        $commentOperation = $openapi['paths']['/api/posts/{post}/comments/{comment}']['put'];
        $this->assertCount(2, $commentOperation['parameters']);
        $commentParam = array_filter($commentOperation['parameters'], fn ($p) => $p['name'] === 'comment');
        $this->assertFalse(array_values($commentParam)[0]['required']);
    }

    #[Test]
    public function it_includes_api_info_and_servers()
    {
        // Arrange
        config(['spectrum.title' => 'Test API']);
        config(['spectrum.version' => '2.0.0']);
        config(['spectrum.description' => 'Test API Description']);
        config(['app.url' => 'https://example.com']);

        Route::get('api/test', [UserController::class, 'index']);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert
        $this->assertEquals('Test API', $openapi['info']['title']);
        $this->assertEquals('2.0.0', $openapi['info']['version']);
        $this->assertEquals('Test API Description', $openapi['info']['description']);
        $this->assertEquals('https://example.com/api', $openapi['servers'][0]['url']);
    }

    #[Test]
    public function it_generates_tags_from_route_uri()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);
        Route::get('api/posts', [UserController::class, 'index']);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert
        $userOperation = $openapi['paths']['/api/users']['get'];
        $this->assertContains('User', $userOperation['tags']);

        $postOperation = $openapi['paths']['/api/posts']['get'];
        $this->assertContains('Post', $postOperation['tags']);
    }

    #[Test]
    public function it_generates_valid_openapi_31_spec_when_configured()
    {
        // Arrange
        config(['spectrum.openapi.version' => '3.1.0']);
        Route::get('api/users', [UserController::class, 'index']);
        Route::post('api/users', [UserController::class, 'store']);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - Version should be 3.1.0
        $this->assertEquals('3.1.0', $openapi['openapi']);

        // Assert - webhooks section should exist (3.1.0 feature)
        $this->assertArrayHasKey('webhooks', $openapi);
        $this->assertInstanceOf(\stdClass::class, $openapi['webhooks']);

        // Assert - Paths still work
        $this->assertArrayHasKey('paths', $openapi);
        $this->assertArrayHasKey('/api/users', $openapi['paths']);
    }

    #[Test]
    public function it_generates_30_spec_when_31_not_configured()
    {
        // Arrange - Default or explicit 3.0.0
        config(['spectrum.openapi.version' => '3.0.0']);
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - Version should be 3.0.0
        $this->assertEquals('3.0.0', $openapi['openapi']);

        // Assert - webhooks section should NOT exist in 3.0.0
        $this->assertArrayNotHasKey('webhooks', $openapi);
    }

    protected function mockControllerAnalysis(string $method, array $result): void
    {
        $controllerAnalyzer = Mockery::mock('LaravelSpectrum\Analyzers\ControllerAnalyzer');
        $controllerAnalyzer->shouldReceive('analyze')
            ->andReturn($result);

        $this->app->instance('LaravelSpectrum\Analyzers\ControllerAnalyzer', $controllerAnalyzer);
    }
}
