<?php

namespace LaravelSpectrum\Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Tests\Fixtures\Controllers\UserController;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class ExportInsomniaCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // テスト用のディレクトリをクリーンアップ
        if (File::exists(storage_path('app/spectrum'))) {
            File::deleteDirectory(storage_path('app/spectrum'));
        }
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
    public function it_exports_insomnia_collection_with_default_options()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);
        Route::post('api/users', [UserController::class, 'store']);
        Route::get('api/users/{id}', [UserController::class, 'show']);

        // Act
        $this->artisan('spectrum:export:insomnia')
            ->expectsOutput('🚀 Exporting Insomnia collection...')
            ->expectsOutputToContain('✅ Collection exported to:')
            ->expectsOutputToContain('insomnia_collection.json')
            ->expectsOutputToContain('📚 Import Instructions:')
            ->expectsOutputToContain('🔄 Git Sync (Team Collaboration):')
            ->assertSuccessful();

        // Assert
        $collectionPath = storage_path('app/spectrum/insomnia/insomnia_collection.json');
        $this->assertFileExists($collectionPath);

        // Verify collection structure
        $collection = json_decode(File::get($collectionPath), true);
        $this->assertIsArray($collection);
        $this->assertArrayHasKey('_type', $collection);
        $this->assertEquals('export', $collection['_type']);
        $this->assertArrayHasKey('__export_format', $collection);
        $this->assertEquals(4, $collection['__export_format']);
        $this->assertArrayHasKey('resources', $collection);
        $this->assertNotEmpty($collection['resources']);
    }

    #[Test]
    public function it_exports_with_custom_output_directory()
    {
        // Arrange
        Route::get('api/test', function () {
            return ['message' => 'test'];
        });

        $customDir = storage_path('exports/insomnia');

        // Act
        $this->artisan('spectrum:export:insomnia', [
            '--output' => $customDir,
        ])
            ->assertSuccessful();

        // Assert
        $this->assertFileExists($customDir.'/insomnia_collection.json');

        // Cleanup
        File::deleteDirectory(storage_path('exports'));
    }

    #[Test]
    public function it_exports_with_custom_output_file()
    {
        // Arrange
        Route::get('api/test', function () {
            return ['message' => 'test'];
        });

        $customPath = storage_path('exports/my_collection.json');

        // Act
        $this->artisan('spectrum:export:insomnia', [
            '--output' => $customPath,
        ])
            ->assertSuccessful();

        // Assert
        $this->assertFileExists($customPath);

        // Cleanup
        File::deleteDirectory(storage_path('exports'));
    }

    #[Test]
    public function it_creates_workspace_structure()
    {
        // Arrange
        Route::get('api/health', function () {
            return ['status' => 'ok'];
        });

        // Act
        $this->artisan('spectrum:export:insomnia')
            ->assertSuccessful();

        // Assert
        $collection = json_decode(File::get(storage_path('app/spectrum/insomnia/insomnia_collection.json')), true);

        // Find workspace
        $workspace = collect($collection['resources'])->firstWhere('_type', 'workspace');
        $this->assertNotNull($workspace);
        $this->assertArrayHasKey('name', $workspace);
        $this->assertArrayHasKey('description', $workspace);

        // Find base environment
        $baseEnv = collect($collection['resources'])->firstWhere('_type', 'environment');
        $this->assertNotNull($baseEnv);
        $this->assertArrayHasKey('data', $baseEnv);
        $this->assertArrayHasKey('base_url', $baseEnv['data']);
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
            ->andReturn([
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
            ]);

        $this->app->instance(OpenApiGenerator::class, $openApiGenerator);

        // Act
        $this->artisan('spectrum:export:insomnia')
            ->assertSuccessful();

        // Assert
        $collection = json_decode(File::get(storage_path('app/spectrum/insomnia/insomnia_collection.json')), true);

        // Check that routes are grouped by tag (folders)
        $folders = collect($collection['resources'])->where('_type', 'request_group');
        $this->assertGreaterThanOrEqual(2, $folders->count());

        $usersFolder = $folders->firstWhere('name', 'Users');
        $postsFolder = $folders->firstWhere('name', 'Posts');

        $this->assertNotNull($usersFolder);
        $this->assertNotNull($postsFolder);
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
            ->andReturn([
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
            ]);

        $this->app->instance(OpenApiGenerator::class, $openApiGenerator);

        // Act
        $this->artisan('spectrum:export:insomnia')
            ->assertSuccessful();

        // Assert
        $collection = json_decode(File::get(storage_path('app/spectrum/insomnia/insomnia_collection.json')), true);

        // Find request with authentication
        $requests = collect($collection['resources'])->where('_type', 'request');
        $profileRequest = $requests->first(function ($request) {
            return str_contains($request['url'] ?? '', '/profile');
        });

        $this->assertNotNull($profileRequest);
        $this->assertArrayHasKey('authentication', $profileRequest);
        $this->assertEquals('bearer', $profileRequest['authentication']['type']);
    }

    #[Test]
    public function it_generates_request_examples_with_validation_rules()
    {
        // Arrange
        Route::post('api/users', [UserController::class, 'store']);

        // Act
        $this->artisan('spectrum:export:insomnia')
            ->assertSuccessful();

        // Assert
        $collection = json_decode(File::get(storage_path('app/spectrum/insomnia/insomnia_collection.json')), true);

        // Find the POST /users request
        $requests = collect($collection['resources'])->where('_type', 'request');
        $postUserRequest = $requests->first(function ($request) {
            return $request['method'] === 'POST' && str_contains($request['url'] ?? '', '/users');
        });

        $this->assertNotNull($postUserRequest);
        $this->assertArrayHasKey('body', $postUserRequest);
        $this->assertArrayHasKey('mimeType', $postUserRequest['body']);
        $this->assertEquals('application/json', $postUserRequest['body']['mimeType']);

        // Check that body contains example data
        $bodyData = json_decode($postUserRequest['body']['text'] ?? '{}', true);
        $this->assertIsArray($bodyData);
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
            ->andReturn([
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
            ]);

        $this->app->instance(OpenApiGenerator::class, $openApiGenerator);

        // Act
        $this->artisan('spectrum:export:insomnia')
            ->assertSuccessful();

        // Assert
        $collection = json_decode(File::get(storage_path('app/spectrum/insomnia/insomnia_collection.json')), true);

        // Find upload request
        $requests = collect($collection['resources'])->where('_type', 'request');
        $uploadRequest = $requests->first(function ($request) {
            return str_contains($request['url'] ?? '', '/upload');
        });

        $this->assertNotNull($uploadRequest);
        $this->assertEquals('multipart/form-data', $uploadRequest['body']['mimeType']);

        // Check form data fields
        $params = $uploadRequest['body']['params'] ?? [];
        $fileField = collect($params)->firstWhere('name', 'file');
        $descField = collect($params)->firstWhere('name', 'description');

        $this->assertNotNull($fileField);
        $this->assertEquals('file', $fileField['type']);
        $this->assertNotNull($descField);
        $this->assertArrayNotHasKey('type', $descField); // text fields don't have type property
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
        $this->artisan('spectrum:export:insomnia')
            ->assertSuccessful();

        // Assert
        $collection = json_decode(File::get(storage_path('app/spectrum/insomnia/insomnia_collection.json')), true);

        // Find requests with path parameters
        $requests = collect($collection['resources'])->where('_type', 'request');
        $parametrizedRequests = $requests->filter(function ($request) {
            return str_contains($request['url'] ?? '', '{{');
        });

        $this->assertNotEmpty($parametrizedRequests);

        // Check path parameter conversion
        foreach ($parametrizedRequests as $request) {
            $url = $request['url'];

            // Check that path parameters are converted to Insomnia format
            if (str_contains($url, 'users/{{ _.id }}')) {
                $this->assertStringContainsString('{{ _.id }}', $url);
            }

            if (str_contains($url, 'users/{{ _.user }}/posts/{{ _.post }}')) {
                $this->assertStringContainsString('{{ _.user }}', $url);
                $this->assertStringContainsString('{{ _.post }}', $url);
            }

            // Check that parameters are defined
            $this->assertArrayHasKey('parameters', $request);
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
            ->andReturn([
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
            ]);

        $this->app->instance(OpenApiGenerator::class, $openApiGenerator);

        // Act
        $this->artisan('spectrum:export:insomnia')
            ->assertSuccessful();

        // Assert
        $collection = json_decode(File::get(storage_path('app/spectrum/insomnia/insomnia_collection.json')), true);

        // Find search request
        $requests = collect($collection['resources'])->where('_type', 'request');
        $searchRequest = $requests->first(function ($request) {
            return str_contains($request['url'] ?? '', '/search');
        });

        $this->assertNotNull($searchRequest);

        // Check that request has query parameters
        $this->assertArrayHasKey('parameters', $searchRequest);
        $this->assertNotEmpty($searchRequest['parameters']);

        // Check query parameters
        $qParam = collect($searchRequest['parameters'])->firstWhere('name', 'q');
        $pageParam = collect($searchRequest['parameters'])->firstWhere('name', 'page');

        $this->assertNotNull($qParam);
        $this->assertNotNull($pageParam);
        $this->assertFalse($qParam['disabled']); // q is required
        $this->assertTrue($pageParam['disabled']); // page is optional
    }

    #[Test]
    public function it_creates_environments_with_variables()
    {
        // Arrange
        Route::get('api/status', function () {
            return ['status' => 'ok'];
        });

        // Mock OpenApiGenerator to return spec with variables
        $openApiGenerator = Mockery::mock(OpenApiGenerator::class);
        $openApiGenerator->shouldReceive('generate')
            ->once()
            ->andReturn([
                'openapi' => '3.0.0',
                'info' => [
                    'title' => 'Test API',
                    'version' => '1.0.0',
                ],
                'servers' => [
                    ['url' => 'http://localhost/api'],
                ],
                'components' => [
                    'securitySchemes' => [
                        'bearerAuth' => [
                            'type' => 'http',
                            'scheme' => 'bearer',
                        ],
                        'apiKey' => [
                            'type' => 'apiKey',
                            'in' => 'header',
                            'name' => 'X-API-Key',
                        ],
                    ],
                ],
                'paths' => [
                    '/status' => [
                        'get' => [
                            'summary' => 'Get status',
                            'responses' => [
                                '200' => ['description' => 'Success'],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->app->instance(OpenApiGenerator::class, $openApiGenerator);

        // Act
        $this->artisan('spectrum:export:insomnia')
            ->assertSuccessful();

        // Assert
        $collection = json_decode(File::get(storage_path('app/spectrum/insomnia/insomnia_collection.json')), true);

        // Find environments
        $environments = collect($collection['resources'])->where('_type', 'environment');
        $this->assertNotEmpty($environments);

        // Check base environment has required variables
        $baseEnv = $environments->firstWhere('name', 'Base Environment');
        $this->assertNotNull($baseEnv);
        $this->assertArrayHasKey('base_url', $baseEnv['data']);

        // Check production environment has auth variables
        $prodEnv = $environments->firstWhere('name', 'Production Environment');
        $this->assertNotNull($prodEnv);
        $this->assertArrayHasKey('bearer_token', $prodEnv['data']);
        $this->assertArrayHasKey('api_key', $prodEnv['data']);
    }
}
