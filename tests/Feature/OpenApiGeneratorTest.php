<?php

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\DTO\ControllerInfo;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Tests\Fixtures\Controllers\PostController;
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
        $openapi = $generator->generate($routes)->toArray();

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
        config(['spectrum.servers' => []]);

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
    public function it_generates_tags_from_controller_names()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);
        Route::get('api/posts', [PostController::class, 'index']);

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

    #[Test]
    public function it_generates_tag_groups_when_configured()
    {
        // Arrange
        config([
            'spectrum.tag_groups' => [
                'User Management' => ['User'],
                'Content' => ['Post'],
            ],
        ]);
        Route::get('api/users', [UserController::class, 'index']);
        Route::get('api/posts', [PostController::class, 'index']);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - x-tagGroups should be present
        $this->assertArrayHasKey('x-tagGroups', $openapi);
        $this->assertCount(2, $openapi['x-tagGroups']);
        $this->assertEquals('User Management', $openapi['x-tagGroups'][0]['name']);
        $this->assertEquals(['User'], $openapi['x-tagGroups'][0]['tags']);
        $this->assertEquals('Content', $openapi['x-tagGroups'][1]['name']);
        $this->assertEquals(['Post'], $openapi['x-tagGroups'][1]['tags']);
    }

    #[Test]
    public function it_generates_tags_section_with_descriptions()
    {
        // Arrange
        config([
            'spectrum.tag_descriptions' => [
                'User' => 'User management endpoints',
            ],
        ]);
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - tags section should be present
        $this->assertArrayHasKey('tags', $openapi);
        $this->assertNotEmpty($openapi['tags']);

        $userTag = collect($openapi['tags'])->firstWhere('name', 'User');
        $this->assertNotNull($userTag);
        $this->assertEquals('User management endpoints', $userTag['description']);
    }

    #[Test]
    public function it_adds_ungrouped_tags_to_other_group()
    {
        // Arrange
        config([
            'spectrum.tag_groups' => [
                'User Management' => ['User'],
            ],
            'spectrum.ungrouped_tags_group' => 'Other',
        ]);
        Route::get('api/users', [UserController::class, 'index']);
        Route::get('api/posts', [PostController::class, 'index']);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - x-tagGroups should include "Other" group for Post
        $this->assertArrayHasKey('x-tagGroups', $openapi);
        $this->assertCount(2, $openapi['x-tagGroups']);

        $otherGroup = collect($openapi['x-tagGroups'])->firstWhere('name', 'Other');
        $this->assertNotNull($otherGroup);
        $this->assertContains('Post', $otherGroup['tags']);
    }

    #[Test]
    public function it_does_not_generate_tag_groups_when_not_configured()
    {
        // Arrange
        config(['spectrum.tag_groups' => []]);
        config(['spectrum.ungrouped_tags_group' => null]);
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - x-tagGroups should not be present when no groups configured
        $this->assertArrayNotHasKey('x-tagGroups', $openapi);
    }

    #[Test]
    public function it_generates_tag_groups_with_openapi_31()
    {
        // Arrange
        config(['spectrum.openapi.version' => '3.1.0']);
        config([
            'spectrum.tag_groups' => [
                'User Management' => ['User'],
            ],
            'spectrum.tag_descriptions' => [
                'User' => 'User endpoints',
            ],
        ]);
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - OpenAPI 3.1.0 features
        $this->assertEquals('3.1.0', $openapi['openapi']);
        $this->assertArrayHasKey('webhooks', $openapi);

        // Assert - Tag groups still work with 3.1.0
        $this->assertArrayHasKey('x-tagGroups', $openapi);
        $this->assertCount(1, $openapi['x-tagGroups']);
        $this->assertEquals('User Management', $openapi['x-tagGroups'][0]['name']);

        // Assert - Tags section with descriptions
        $this->assertArrayHasKey('tags', $openapi);
        $userTag = collect($openapi['tags'])->firstWhere('name', 'User');
        $this->assertNotNull($userTag);
        $this->assertEquals('User endpoints', $userTag['description']);
    }

    #[Test]
    public function it_handles_invalid_tag_groups_config_gracefully()
    {
        // Arrange - Simulate misconfiguration
        config(['spectrum.tag_groups' => 'invalid_string']);
        Route::get('api/users', [UserController::class, 'index']);

        // Act - Should not throw exception
        $openapi = $this->generateOpenApi();

        // Assert - x-tagGroups should not be present
        $this->assertArrayNotHasKey('x-tagGroups', $openapi);
    }

    #[Test]
    public function it_uses_config_servers_when_configured()
    {
        // Arrange
        config(['spectrum.servers' => [
            [
                'url' => 'https://api.example.com',
                'description' => 'Production Server',
            ],
            [
                'url' => 'https://staging.example.com',
                'description' => 'Staging Server',
            ],
        ]]);
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert
        $this->assertCount(2, $openapi['servers']);
        $this->assertEquals('https://api.example.com', $openapi['servers'][0]['url']);
        $this->assertEquals('Production Server', $openapi['servers'][0]['description']);
        $this->assertEquals('https://staging.example.com', $openapi['servers'][1]['url']);
        $this->assertEquals('Staging Server', $openapi['servers'][1]['description']);
    }

    #[Test]
    public function it_supports_server_variables_in_config()
    {
        // Arrange
        config(['spectrum.servers' => [
            [
                'url' => 'https://{environment}.example.com/api/{version}',
                'description' => 'API Server with variables',
                'variables' => [
                    'environment' => [
                        'default' => 'production',
                        'description' => 'Server environment',
                        'enum' => ['production', 'staging', 'development'],
                    ],
                    'version' => [
                        'default' => 'v1',
                        'description' => 'API version',
                    ],
                ],
            ],
        ]]);
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert
        $this->assertCount(1, $openapi['servers']);
        $server = $openapi['servers'][0];
        $this->assertEquals('https://{environment}.example.com/api/{version}', $server['url']);
        $this->assertEquals('API Server with variables', $server['description']);
        $this->assertArrayHasKey('variables', $server);
        $this->assertArrayHasKey('environment', $server['variables']);
        $this->assertEquals('production', $server['variables']['environment']['default']);
        $this->assertEquals(['production', 'staging', 'development'], $server['variables']['environment']['enum']);
        $this->assertArrayHasKey('version', $server['variables']);
        $this->assertEquals('v1', $server['variables']['version']['default']);
    }

    #[Test]
    public function it_falls_back_to_default_when_servers_config_is_empty()
    {
        // Arrange
        config(['spectrum.servers' => []]);
        config(['app.url' => 'https://example.com']);
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - Should use the default app.url/api fallback
        $this->assertCount(1, $openapi['servers']);
        $this->assertEquals('https://example.com/api', $openapi['servers'][0]['url']);
        $this->assertEquals('API Server', $openapi['servers'][0]['description']);
    }

    #[Test]
    public function it_falls_back_to_default_when_servers_config_is_null()
    {
        // Arrange - Simulate older config that does not have 'servers' key
        config(['spectrum.servers' => null]);
        config(['app.url' => 'https://example.com']);
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - Should use the default app.url/api fallback
        $this->assertCount(1, $openapi['servers']);
        $this->assertEquals('https://example.com/api', $openapi['servers'][0]['url']);
    }

    #[Test]
    public function it_supports_server_with_url_only()
    {
        // Arrange
        config(['spectrum.servers' => [
            ['url' => 'https://api.example.com'],
        ]]);
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert
        $this->assertCount(1, $openapi['servers']);
        $this->assertEquals('https://api.example.com', $openapi['servers'][0]['url']);
        $this->assertArrayNotHasKey('description', $openapi['servers'][0]);
        $this->assertArrayNotHasKey('variables', $openapi['servers'][0]);
    }

    #[Test]
    public function it_skips_invalid_server_entries_gracefully()
    {
        // Arrange - Mix of valid and invalid entries
        config(['spectrum.servers' => [
            ['url' => 'https://api.example.com', 'description' => 'Valid Server'],
            'https://invalid.example.com',
            ['url' => 'https://also-valid.example.com'],
        ]]);
        Route::get('api/users', [UserController::class, 'index']);

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once()
            ->with(Mockery::on(function ($message) {
                return str_contains($message, 'expected array, got string');
            }));

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - Only valid array entries should be included
        $this->assertCount(2, $openapi['servers']);
        $this->assertEquals('https://api.example.com', $openapi['servers'][0]['url']);
        $this->assertEquals('https://also-valid.example.com', $openapi['servers'][1]['url']);
    }

    #[Test]
    public function it_falls_back_to_default_when_all_server_entries_are_invalid()
    {
        // Arrange - All entries are non-array
        config(['spectrum.servers' => [
            'https://string1.example.com',
            'https://string2.example.com',
        ]]);
        config(['app.url' => 'https://example.com']);
        Route::get('api/users', [UserController::class, 'index']);

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->times(3);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - Should fall back to default since all entries were invalid
        $this->assertCount(1, $openapi['servers']);
        $this->assertEquals('https://example.com/api', $openapi['servers'][0]['url']);
        $this->assertEquals('API Server', $openapi['servers'][0]['description']);
    }

    #[Test]
    public function it_skips_server_entries_with_missing_url()
    {
        // Arrange - Entry without url key
        config(['spectrum.servers' => [
            ['description' => 'No URL server'],
            ['url' => 'https://valid.example.com', 'description' => 'Valid'],
        ]]);
        Route::get('api/users', [UserController::class, 'index']);

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once()
            ->with(Mockery::on(function ($message) {
                return str_contains($message, 'missing a valid "url" field');
            }));

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - Only entry with valid url should be included
        $this->assertCount(1, $openapi['servers']);
        $this->assertEquals('https://valid.example.com', $openapi['servers'][0]['url']);
    }

    #[Test]
    public function it_skips_server_entries_with_empty_url()
    {
        // Arrange - Entry with empty url
        config(['spectrum.servers' => [
            ['url' => '', 'description' => 'Empty URL'],
            ['url' => 'https://valid.example.com'],
        ]]);
        Route::get('api/users', [UserController::class, 'index']);

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once()
            ->with(Mockery::on(function ($message) {
                return str_contains($message, 'missing a valid "url" field');
            }));

        // Act
        $openapi = $this->generateOpenApi();

        // Assert
        $this->assertCount(1, $openapi['servers']);
        $this->assertEquals('https://valid.example.com', $openapi['servers'][0]['url']);
    }

    #[Test]
    public function it_handles_trailing_slash_in_app_url_for_default_server()
    {
        // Arrange - app.url with trailing slash
        config(['spectrum.servers' => []]);
        config(['app.url' => 'https://example.com/']);
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - Should not produce double slash
        $this->assertEquals('https://example.com/api', $openapi['servers'][0]['url']);
    }

    #[Test]
    public function it_falls_back_to_localhost_when_app_url_is_null()
    {
        // Arrange
        config(['spectrum.servers' => []]);
        config(['app.url' => null]);
        Route::get('api/users', [UserController::class, 'index']);

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once()
            ->with(Mockery::on(function ($message) {
                return str_contains($message, 'app.url config is not a valid string');
            }));

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - Should use http://localhost fallback
        $this->assertCount(1, $openapi['servers']);
        $this->assertEquals('http://localhost/api', $openapi['servers'][0]['url']);
    }

    #[Test]
    public function it_handles_non_string_url_in_server_entry()
    {
        // Arrange - url is an array instead of string
        config(['spectrum.servers' => [
            ['url' => ['nested', 'array'], 'description' => 'Bad config'],
            ['url' => 'https://valid.example.com'],
        ]]);
        Route::get('api/users', [UserController::class, 'index']);

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once()
            ->with(Mockery::on(function ($message) {
                return str_contains($message, 'missing a valid "url" field');
            }));

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - Invalid entry should be skipped
        $this->assertCount(1, $openapi['servers']);
        $this->assertEquals('https://valid.example.com', $openapi['servers'][0]['url']);
    }

    #[Test]
    public function it_strips_server_variables_missing_default_field()
    {
        // Arrange - Variable missing required 'default' field
        config(['spectrum.servers' => [
            [
                'url' => 'https://{env}.example.com/api',
                'description' => 'API Server',
                'variables' => [
                    'env' => [
                        'default' => 'production',
                        'enum' => ['production', 'staging'],
                    ],
                    'bad_var' => [
                        'enum' => ['a', 'b'],
                    ],
                ],
            ],
        ]]);
        Route::get('api/users', [UserController::class, 'index']);

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once()
            ->with(Mockery::on(function ($message) {
                return str_contains($message, 'Server variable "bad_var"') && str_contains($message, 'missing a valid "default" field');
            }));

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - Server should be included but invalid variable stripped
        $this->assertCount(1, $openapi['servers']);
        $this->assertEquals('https://{env}.example.com/api', $openapi['servers'][0]['url']);
        $this->assertArrayHasKey('variables', $openapi['servers'][0]);
        $this->assertArrayHasKey('env', $openapi['servers'][0]['variables']);
        $this->assertArrayNotHasKey('bad_var', $openapi['servers'][0]['variables']);
    }

    #[Test]
    public function it_removes_variables_key_when_all_variables_are_invalid()
    {
        // Arrange - All variables missing 'default'
        config(['spectrum.servers' => [
            [
                'url' => 'https://example.com/api',
                'variables' => [
                    'bad1' => ['enum' => ['a']],
                    'bad2' => ['description' => 'no default'],
                ],
            ],
        ]]);
        Route::get('api/users', [UserController::class, 'index']);

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->times(2);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - Server included but variables key removed entirely
        $this->assertCount(1, $openapi['servers']);
        $this->assertEquals('https://example.com/api', $openapi['servers'][0]['url']);
        $this->assertArrayNotHasKey('variables', $openapi['servers'][0]);
    }

    #[Test]
    public function it_falls_back_to_default_when_all_entries_have_invalid_urls()
    {
        // Arrange - All entries are arrays but with missing/empty URLs
        config(['spectrum.servers' => [
            ['description' => 'No URL'],
            ['url' => '', 'description' => 'Empty URL'],
            ['url' => ['not', 'a', 'string']],
        ]]);
        config(['app.url' => 'https://example.com']);
        Route::get('api/users', [UserController::class, 'index']);

        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->times(4);

        // Act
        $openapi = $this->generateOpenApi();

        // Assert - All invalid, should fall back to default
        $this->assertCount(1, $openapi['servers']);
        $this->assertEquals('https://example.com/api', $openapi['servers'][0]['url']);
        $this->assertEquals('API Server', $openapi['servers'][0]['description']);
    }

    protected function mockControllerAnalysis(string $method, array $result): void
    {
        $controllerAnalyzer = Mockery::mock('LaravelSpectrum\Analyzers\ControllerAnalyzer');
        $controllerAnalyzer->shouldReceive('analyzeToResult')
            ->andReturn(ControllerInfo::fromArray($result));

        $this->app->instance('LaravelSpectrum\Analyzers\ControllerAnalyzer', $controllerAnalyzer);
    }
}
