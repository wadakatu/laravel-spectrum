<?php

namespace LaravelSpectrum\Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use LaravelSpectrum\DTO\OpenApiSpec;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Tests\Fixtures\Controllers\UserController;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class ExportPostmanCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // テスト用のディレクトリをクリーンアップ
        if (File::exists(storage_path('app/spectrum'))) {
            File::deleteDirectory(storage_path('app/spectrum'));
        }

        // Use fallback server behavior for consistent test output
        config(['spectrum.servers' => []]);
    }

    protected function tearDown(): void
    {
        // テスト後のクリーンアップ
        if (File::exists(storage_path('app/spectrum'))) {
            File::deleteDirectory(storage_path('app/spectrum'));
        }

        parent::tearDown();
    }

    #[Test]
    public function it_exports_postman_collection_with_default_options()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);
        Route::post('api/users', [UserController::class, 'store']);
        Route::get('api/users/{id}', [UserController::class, 'show']);

        // Act
        $this->artisan('spectrum:export:postman')
            ->expectsOutput('🚀 Exporting Postman collection...')
            ->expectsOutputToContain('✅ Collection exported to:')
            ->expectsOutputToContain('postman_collection.json')
            ->expectsOutputToContain("✅ Environment 'local' exported to:")
            ->expectsOutputToContain('postman_environment_local.json')
            ->expectsOutputToContain('📚 Import Instructions:')
            ->expectsOutputToContain('🏃 Run with Newman (CLI):')
            ->assertSuccessful();

        // Assert
        $collectionPath = storage_path('app/spectrum/postman/postman_collection.json');
        $environmentPath = storage_path('app/spectrum/postman/postman_environment_local.json');

        $this->assertFileExists($collectionPath);
        $this->assertFileExists($environmentPath);

        // Verify collection structure
        $collection = json_decode(File::get($collectionPath), true);
        $this->assertArrayHasKey('info', $collection);
        $this->assertArrayHasKey('item', $collection);
        $this->assertNotEmpty($collection['item']);
        $this->assertEquals('https://schema.getpostman.com/json/collection/v2.1.0/collection.json', $collection['info']['schema']);
        $this->assertEquals('laravel-spectrum', $collection['info']['_exporter_id']);

        // Verify environment structure
        $environment = json_decode(File::get($environmentPath), true);
        $this->assertArrayHasKey('name', $environment);
        $this->assertEquals('local Environment', $environment['name']);
        $this->assertArrayHasKey('values', $environment);
        $this->assertNotEmpty($environment['values']);

        // Check base_url variable exists
        $baseUrlVar = collect($environment['values'])->firstWhere('key', 'base_url');
        $this->assertNotNull($baseUrlVar);
        $this->assertEquals('http://localhost/api', $baseUrlVar['value']);
    }

    #[Test]
    public function it_exports_with_custom_output_directory()
    {
        // Arrange
        Route::get('api/test', function () {
            return ['message' => 'test'];
        });

        $customDir = storage_path('exports/postman');

        // Act
        $this->artisan('spectrum:export:postman', [
            '--output' => $customDir,
        ])
            ->assertSuccessful();

        // Assert
        $this->assertFileExists($customDir.'/postman_collection.json');
        $this->assertFileExists($customDir.'/postman_environment_local.json');

        // Cleanup
        File::deleteDirectory(storage_path('exports'));
    }

    #[Test]
    public function it_exports_multiple_environments()
    {
        // Arrange
        Route::get('api/health', function () {
            return ['status' => 'ok'];
        });

        // Act
        $this->artisan('spectrum:export:postman', [
            '--environments' => 'local,staging,production',
        ])
            ->expectsOutputToContain("✅ Environment 'local' exported to:")
            ->expectsOutputToContain("✅ Environment 'staging' exported to:")
            ->expectsOutputToContain("✅ Environment 'production' exported to:")
            ->assertSuccessful();

        // Assert
        $this->assertFileExists(storage_path('app/spectrum/postman/postman_environment_local.json'));
        $this->assertFileExists(storage_path('app/spectrum/postman/postman_environment_staging.json'));
        $this->assertFileExists(storage_path('app/spectrum/postman/postman_environment_production.json'));
    }

    #[Test]
    public function it_embeds_environments_when_single_file_option_is_enabled()
    {
        // Arrange
        Route::get('api/health', function () {
            return ['status' => 'ok'];
        });

        // Act
        $this->artisan('spectrum:export:postman', [
            '--single-file' => true,
            '--environments' => 'local,staging',
        ])
            ->expectsOutputToContain('✅ Environments embedded into collection: local, staging')
            ->assertSuccessful();

        // Assert
        $collectionPath = storage_path('app/spectrum/postman/postman_collection.json');
        $localEnvironmentPath = storage_path('app/spectrum/postman/postman_environment_local.json');
        $stagingEnvironmentPath = storage_path('app/spectrum/postman/postman_environment_staging.json');

        $this->assertFileExists($collectionPath);
        $this->assertFileDoesNotExist($localEnvironmentPath);
        $this->assertFileDoesNotExist($stagingEnvironmentPath);

        $collection = json_decode(File::get($collectionPath), true);

        $this->assertArrayHasKey('x-laravel-spectrum-environments', $collection);
        $this->assertArrayHasKey('local', $collection['x-laravel-spectrum-environments']);
        $this->assertArrayHasKey('staging', $collection['x-laravel-spectrum-environments']);

        $localValues = $collection['x-laravel-spectrum-environments']['local']['values'] ?? [];
        $baseUrlVar = collect($localValues)->firstWhere('key', 'base_url');

        $this->assertNotNull($baseUrlVar);
        $this->assertEquals('http://localhost/api', $baseUrlVar['value']);
    }

    #[Test]
    public function it_groups_routes_by_tag()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);
        Route::post('api/users', [UserController::class, 'store']);
        Route::get('api/posts', function () {
            return ['posts' => []];
        });

        // Mock OpenApiGenerator to return tagged routes
        $openApiGenerator = Mockery::mock(OpenApiGenerator::class);
        $openApiGenerator->shouldReceive('generate')
            ->once()
            ->andReturn(OpenApiSpec::fromArray([
                'openapi' => '3.0.0',
                'info' => [
                    'title' => 'Test API',
                    'version' => '1.0.0',
                ],
                'servers' => [
                    ['url' => 'http://localhost/api'],
                ],
                'paths' => [
                    '/users' => [
                        'get' => [
                            'tags' => ['Users'],
                            'summary' => 'List users',
                            'responses' => [
                                '200' => ['description' => 'Success'],
                            ],
                        ],
                        'post' => [
                            'tags' => ['Users'],
                            'summary' => 'Create user',
                            'responses' => [
                                '201' => ['description' => 'Created'],
                            ],
                        ],
                    ],
                    '/posts' => [
                        'get' => [
                            'tags' => ['Posts'],
                            'summary' => 'List posts',
                            'responses' => [
                                '200' => ['description' => 'Success'],
                            ],
                        ],
                    ],
                ],
            ]));

        $this->app->instance(OpenApiGenerator::class, $openApiGenerator);

        // Act
        $this->artisan('spectrum:export:postman')
            ->assertSuccessful();

        // Assert
        $collection = json_decode(File::get(storage_path('app/spectrum/postman/postman_collection.json')), true);

        // Check that routes are grouped by tag
        $this->assertCount(2, $collection['item']);

        $usersFolder = collect($collection['item'])->firstWhere('name', 'Users');
        $postsFolder = collect($collection['item'])->firstWhere('name', 'Posts');

        $this->assertNotNull($usersFolder);
        $this->assertNotNull($postsFolder);
        $this->assertCount(2, $usersFolder['item']);
        $this->assertCount(1, $postsFolder['item']);
    }

    #[Test]
    public function it_includes_authentication_in_collection()
    {
        // Arrange
        Route::middleware('auth:api')->group(function () {
            Route::get('api/profile', function () {
                return ['user' => 'profile'];
            });
        });

        // Mock OpenApiGenerator to return spec with authentication
        $openApiGenerator = Mockery::mock(OpenApiGenerator::class);
        $openApiGenerator->shouldReceive('generate')
            ->once()
            ->andReturn(OpenApiSpec::fromArray([
                'openapi' => '3.0.0',
                'info' => [
                    'title' => 'Test API',
                    'version' => '1.0.0',
                ],
                'servers' => [
                    ['url' => 'http://localhost/api'],
                ],
                'security' => [
                    ['bearerAuth' => []],
                ],
                'components' => [
                    'securitySchemes' => [
                        'bearerAuth' => [
                            'type' => 'http',
                            'scheme' => 'bearer',
                            'bearerFormat' => 'JWT',
                        ],
                    ],
                ],
                'paths' => [
                    '/profile' => [
                        'get' => [
                            'summary' => 'Get user profile',
                            'security' => [
                                ['bearerAuth' => []],
                            ],
                            'responses' => [
                                '200' => ['description' => 'Success'],
                            ],
                        ],
                    ],
                ],
            ]));

        $this->app->instance(OpenApiGenerator::class, $openApiGenerator);

        // Act
        $this->artisan('spectrum:export:postman')
            ->assertSuccessful();

        // Assert
        $collection = json_decode(File::get(storage_path('app/spectrum/postman/postman_collection.json')), true);
        $environment = json_decode(File::get(storage_path('app/spectrum/postman/postman_environment_local.json')), true);

        // Check collection has auth
        $this->assertArrayHasKey('auth', $collection);

        // Check environment has bearer_token variable
        $bearerTokenVar = collect($environment['values'])->firstWhere('key', 'bearer_token');
        $this->assertNotNull($bearerTokenVar);
        $this->assertEquals('secret', $bearerTokenVar['type']);
    }

    #[Test]
    public function it_generates_request_examples_with_validation_rules()
    {
        // Arrange
        Route::post('api/users', [UserController::class, 'store']);

        // Act
        $this->artisan('spectrum:export:postman')
            ->assertSuccessful();

        // Assert
        $collection = json_decode(File::get(storage_path('app/spectrum/postman/postman_collection.json')), true);

        // Find the POST /users request
        $postUserRequest = null;
        foreach ($collection['item'] as $folder) {
            foreach ($folder['item'] ?? [] as $item) {
                if ($item['request']['method'] === 'POST' && str_contains($item['request']['url']['raw'], '/users')) {
                    $postUserRequest = $item;
                    break 2;
                }
            }
        }

        $this->assertNotNull($postUserRequest);
        $this->assertArrayHasKey('body', $postUserRequest['request']);
        $this->assertEquals('raw', $postUserRequest['request']['body']['mode']);

        // Check that body contains example data
        $bodyData = json_decode($postUserRequest['request']['body']['raw'], true);
        $this->assertIsArray($bodyData);
    }

    #[Test]
    public function it_generates_tests_in_collection()
    {
        // Arrange
        Route::get('api/status', function () {
            return response()->json(['status' => 'ok']);
        });

        // Mock OpenApiGenerator to return the expected structure with test scripts
        $openApiGenerator = Mockery::mock(OpenApiGenerator::class);
        $openApiGenerator->shouldReceive('generate')
            ->once()
            ->andReturn(OpenApiSpec::fromArray([
                'openapi' => '3.0.0',
                'info' => [
                    'title' => 'Test API',
                    'version' => '1.0.0',
                ],
                'servers' => [
                    ['url' => 'http://localhost/api'],
                ],
                'paths' => [
                    '/status' => [
                        'get' => [
                            'summary' => 'Get status',
                            'responses' => [
                                '200' => [
                                    'description' => 'Success',
                                    'content' => [
                                        'application/json' => [
                                            'schema' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'status' => ['type' => 'string'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]));

        $this->app->instance(OpenApiGenerator::class, $openApiGenerator);

        // Act
        $this->artisan('spectrum:export:postman')
            ->assertSuccessful();

        // Assert
        $collectionPath = storage_path('app/spectrum/postman/postman_collection.json');
        $this->assertFileExists($collectionPath);

        $collection = json_decode(File::get($collectionPath), true);

        // Find the request in the collection
        $request = null;
        foreach ($collection['item'] as $folder) {
            if (isset($folder['item']) && is_array($folder['item'])) {
                foreach ($folder['item'] as $item) {
                    if (isset($item['request']['url']['raw']) && str_contains($item['request']['url']['raw'], 'status')) {
                        $request = $item;
                        break 2;
                    }
                }
            }
        }

        $this->assertNotNull($request, 'Status endpoint should be in the collection');

        // Check if test scripts are generated
        $this->assertArrayHasKey('event', $request);

        // Find test event
        $testEvent = null;
        foreach ($request['event'] as $event) {
            if ($event['listen'] === 'test') {
                $testEvent = $event;
                break;
            }
        }

        $this->assertNotNull($testEvent, 'Test event should exist');
        $this->assertArrayHasKey('script', $testEvent);
        $this->assertArrayHasKey('exec', $testEvent['script']);
        $this->assertIsArray($testEvent['script']['exec']);
        $this->assertNotEmpty($testEvent['script']['exec']);

        // Verify test content
        $testScript = implode('
', $testEvent['script']['exec']);
        $this->assertStringContainsString('pm.test', $testScript);
        $this->assertStringContainsString('Status code is successful', $testScript);
        $this->assertStringContainsString('pm.response.to.have.status(200)', $testScript);
        $this->assertStringContainsString('Response time', $testScript);
    }

    #[Test]
    public function it_handles_file_upload_endpoints()
    {
        // Arrange
        Route::post('api/upload', function () {
            return ['url' => 'uploaded'];
        });

        // Mock OpenApiGenerator to return file upload spec
        $openApiGenerator = Mockery::mock(OpenApiGenerator::class);
        $openApiGenerator->shouldReceive('generate')
            ->once()
            ->andReturn(OpenApiSpec::fromArray([
                'openapi' => '3.0.0',
                'info' => [
                    'title' => 'Test API',
                    'version' => '1.0.0',
                ],
                'servers' => [
                    ['url' => 'http://localhost/api'],
                ],
                'paths' => [
                    '/upload' => [
                        'post' => [
                            'summary' => 'Upload file',
                            'requestBody' => [
                                'content' => [
                                    'multipart/form-data' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'file' => [
                                                    'type' => 'string',
                                                    'format' => 'binary',
                                                    'description' => 'File to upload',
                                                ],
                                                'description' => [
                                                    'type' => 'string',
                                                    'description' => 'File description',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'responses' => [
                                '200' => ['description' => 'Success'],
                            ],
                        ],
                    ],
                ],
            ]));

        $this->app->instance(OpenApiGenerator::class, $openApiGenerator);

        // Act
        $this->artisan('spectrum:export:postman')
            ->assertSuccessful();

        // Assert
        $collection = json_decode(File::get(storage_path('app/spectrum/postman/postman_collection.json')), true);

        // Find upload request
        $uploadRequest = null;
        foreach ($collection['item'] as $folder) {
            foreach ($folder['item'] ?? [] as $item) {
                if (str_contains($item['request']['url']['raw'], '/upload')) {
                    $uploadRequest = $item;
                    break 2;
                }
            }
        }

        $this->assertNotNull($uploadRequest);
        $this->assertEquals('formdata', $uploadRequest['request']['body']['mode']);

        // Check form data fields
        $formData = $uploadRequest['request']['body']['formdata'];
        $fileField = collect($formData)->firstWhere('key', 'file');
        $descField = collect($formData)->firstWhere('key', 'description');

        $this->assertNotNull($fileField);
        $this->assertEquals('file', $fileField['type']);
        $this->assertNotNull($descField);
        $this->assertEquals('text', $descField['type']);
    }

    #[Test]
    public function it_handles_path_parameters_correctly()
    {
        // Arrange
        Route::get('api/users/{id}', [UserController::class, 'show']);
        Route::put('api/users/{user}/posts/{post}', function ($user, $post) {
            return ['user' => $user, 'post' => $post];
        });

        // Act
        $this->artisan('spectrum:export:postman')
            ->assertSuccessful();

        // Assert
        $collection = json_decode(File::get(storage_path('app/spectrum/postman/postman_collection.json')), true);

        // Find requests with path parameters
        $requests = [];
        foreach ($collection['item'] as $folder) {
            foreach ($folder['item'] ?? [] as $item) {
                if (str_contains($item['request']['url']['raw'], ':')) {
                    $requests[] = $item;
                }
            }
        }

        $this->assertNotEmpty($requests);

        // Check path parameter conversion
        foreach ($requests as $request) {
            $url = $request['request']['url'];
            $this->assertArrayHasKey('variable', $url);

            // Check that path parameters are converted to Postman format
            if (str_contains($url['raw'], 'users/:id')) {
                $idVar = collect($url['variable'])->firstWhere('key', 'id');
                $this->assertNotNull($idVar);
            }

            if (str_contains($url['raw'], 'users/:user/posts/:post')) {
                $userVar = collect($url['variable'])->firstWhere('key', 'user');
                $postVar = collect($url['variable'])->firstWhere('key', 'post');
                $this->assertNotNull($userVar);
                $this->assertNotNull($postVar);
            }
        }
    }

    #[Test]
    public function it_includes_query_parameters()
    {
        // Arrange
        Route::get('api/search', function () {
            return ['results' => []];
        });

        // Mock OpenApiGenerator with query parameters
        $openApiGenerator = Mockery::mock(OpenApiGenerator::class);
        $openApiGenerator->shouldReceive('generate')
            ->once()
            ->andReturn(OpenApiSpec::fromArray([
                'openapi' => '3.0.0',
                'info' => [
                    'title' => 'Test API',
                    'version' => '1.0.0',
                ],
                'servers' => [
                    ['url' => 'http://localhost/api'],
                ],
                'paths' => [
                    '/search' => [
                        'get' => [
                            'summary' => 'Search',
                            'parameters' => [
                                [
                                    'name' => 'q',
                                    'in' => 'query',
                                    'description' => 'Search query',
                                    'required' => true,
                                    'schema' => ['type' => 'string'],
                                ],
                                [
                                    'name' => 'page',
                                    'in' => 'query',
                                    'description' => 'Page number',
                                    'required' => false,
                                    'schema' => ['type' => 'integer'],
                                ],
                            ],
                            'responses' => [
                                '200' => ['description' => 'Success'],
                            ],
                        ],
                    ],
                ],
            ]));

        $this->app->instance(OpenApiGenerator::class, $openApiGenerator);

        // Act
        $this->artisan('spectrum:export:postman')
            ->assertSuccessful();

        // Assert
        $collection = json_decode(File::get(storage_path('app/spectrum/postman/postman_collection.json')), true);

        // Find search request
        $searchRequest = null;
        foreach ($collection['item'] as $folder) {
            foreach ($folder['item'] ?? [] as $item) {
                if (str_contains($item['request']['url']['raw'], '/search')) {
                    $searchRequest = $item;
                    break 2;
                }
            }
        }

        $this->assertNotNull($searchRequest);
        $this->assertArrayHasKey('query', $searchRequest['request']['url']);

        // Check query parameters
        $queryParams = $searchRequest['request']['url']['query'];
        $qParam = collect($queryParams)->firstWhere('key', 'q');
        $pageParam = collect($queryParams)->firstWhere('key', 'page');

        $this->assertNotNull($qParam);
        $this->assertNotNull($pageParam);
    }
}
