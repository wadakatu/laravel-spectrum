<?php

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Tests\Fixtures\Controllers\UserController;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests to ensure generated OpenAPI specifications are valid.
 *
 * These tests validate that the OpenAPI generator produces specs that:
 * - Conform to the OpenAPI 3.0.x/3.1.x specification (via devizzent/cebe-php-openapi)
 * - Have all required elements
 * - Have valid structure for paths, operations, and schemas
 */
class OpenApiSpecValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(DocumentationCache::class)->clear();
        // Use a unique route pattern prefix for these tests to avoid conflicts
        config(['spectrum.route_patterns' => ['api/spec-test/*']]);
    }

    protected function tearDown(): void
    {
        app(DocumentationCache::class)->clear();
        parent::tearDown();
    }

    #[Test]
    public function generated_spec_is_valid_openapi(): void
    {
        Route::prefix('api/spec-test')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::get('users/{user}', [UserController::class, 'show']);
            Route::put('users/{user}', [UserController::class, 'update']);
            Route::delete('users/{user}', [UserController::class, 'destroy']);
        });

        $openapi = $this->generateOpenApi();

        $this->assertValidOpenApiSpec($openapi);
    }

    #[Test]
    public function generated_spec_has_required_elements(): void
    {
        Route::get('api/spec-test/health', fn () => ['status' => 'ok']);

        $openapi = $this->generateOpenApi();

        $this->assertOpenApiHasRequiredElements($openapi);
    }

    #[Test]
    public function generated_spec_has_valid_paths(): void
    {
        Route::prefix('api/spec-test')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
        });

        $openapi = $this->generateOpenApi();

        $this->assertValidPath($openapi, '/api/spec-test/users', ['get', 'post']);
    }

    #[Test]
    public function generated_spec_with_path_parameters_is_valid(): void
    {
        Route::prefix('api/spec-test')->group(function () {
            Route::get('users/{user}', [UserController::class, 'show']);
            Route::get('posts/{post}', [UserController::class, 'show']);
            Route::get('posts/{post}/comments/{comment}', [UserController::class, 'show']);
        });

        $openapi = $this->generateOpenApi();

        $this->assertValidOpenApiSpec($openapi);
        $this->assertValidPath($openapi, '/api/spec-test/users/{user}', ['get']);
        $this->assertValidPath($openapi, '/api/spec-test/posts/{post}', ['get']);
        // Nested path parameters should also be valid
        $this->assertArrayHasKey('paths', $openapi);
    }

    #[Test]
    public function generated_spec_with_request_body_is_valid(): void
    {
        Route::post('api/spec-test/users', [UserController::class, 'store']);

        $openapi = $this->generateOpenApi();

        $this->assertValidOpenApiSpec($openapi);

        if (isset($openapi['paths']['/api/spec-test/users']['post'])) {
            $postOperation = $openapi['paths']['/api/spec-test/users']['post'];
            if (isset($postOperation['requestBody'])) {
                $this->assertValidRequestBody($postOperation);
            }
        }
    }

    #[Test]
    public function generated_spec_with_multiple_http_methods_is_valid(): void
    {
        Route::prefix('api/spec-test/resources')->group(function () {
            Route::get('/', fn () => []);
            Route::post('/', fn () => []);
            Route::get('{id}', fn () => []);
            Route::put('{id}', fn () => []);
            Route::patch('{id}', fn () => []);
            Route::delete('{id}', fn () => []);
        });

        $openapi = $this->generateOpenApi();

        $this->assertValidOpenApiSpec($openapi);
    }

    #[Test]
    public function generated_spec_with_nested_routes_is_valid(): void
    {
        Route::prefix('api/spec-test')->group(function () {
            Route::get('users/{user}/posts', fn () => []);
            Route::get('users/{user}/posts/{post}/comments', fn () => []);
            Route::get('users/{user}/posts/{post}/comments/{comment}', fn () => []);
        });

        $openapi = $this->generateOpenApi();

        $this->assertValidOpenApiSpec($openapi);
    }

    #[Test]
    public function generated_spec_has_valid_responses(): void
    {
        Route::get('api/spec-test/users', [UserController::class, 'index']);

        $openapi = $this->generateOpenApi();

        $getOperation = $openapi['paths']['/api/spec-test/users']['get'];
        $this->assertValidResponses($getOperation, [200]);
    }

    #[Test]
    public function generated_openapi_31_spec_is_valid(): void
    {
        config(['spectrum.openapi.version' => '3.1.0']);

        Route::get('api/spec-test/users', [UserController::class, 'index']);

        $openapi = $this->generateOpenApi();

        // devizzent/cebe-php-openapi supports OpenAPI 3.1.x validation
        $this->assertValidOpenApiSpec($openapi);
        $this->assertEquals('3.1.0', $openapi['openapi']);
    }

    #[Test]
    public function empty_routes_produce_valid_spec(): void
    {
        // No routes registered for api/spec-test/* pattern

        $openapi = $this->generateOpenApi();

        $this->assertValidOpenApiSpec($openapi);
        $this->assertOpenApiHasRequiredElements($openapi);
        $this->assertEmpty($openapi['paths']);
    }

    #[Test]
    public function spec_with_security_schemes_is_valid(): void
    {
        Route::middleware('auth:sanctum')
            ->get('api/spec-test/protected', fn () => ['secret' => 'data']);

        $openapi = $this->generateOpenApi();

        $this->assertValidOpenApiSpec($openapi);
    }
}
