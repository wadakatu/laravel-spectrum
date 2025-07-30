<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Analyzers\AuthenticationAnalyzer;
use LaravelSpectrum\Analyzers\ControllerAnalyzer;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\Analyzers\ResourceAnalyzer;
use LaravelSpectrum\Generators\ErrorResponseGenerator;
use LaravelSpectrum\Generators\ExampleGenerator;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Generators\PaginationSchemaGenerator;
use LaravelSpectrum\Generators\ResponseSchemaGenerator;
use LaravelSpectrum\Generators\SchemaGenerator;
use LaravelSpectrum\Generators\SecuritySchemeGenerator;
use LaravelSpectrum\Support\PaginationDetector;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class OpenApiGeneratorTest extends TestCase
{
    private OpenApiGenerator $generator;

    private $requestAnalyzer;

    private $resourceAnalyzer;

    private $controllerAnalyzer;

    private $inlineValidationAnalyzer;

    private $schemaGenerator;

    private $errorResponseGenerator;

    private $authenticationAnalyzer;

    private $securitySchemeGenerator;

    private $paginationSchemaGenerator;

    private $paginationDetector;

    private $exampleGenerator;

    private $responseSchemaGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestAnalyzer = Mockery::mock(FormRequestAnalyzer::class);
        $this->resourceAnalyzer = Mockery::mock(ResourceAnalyzer::class);
        $this->controllerAnalyzer = Mockery::mock(ControllerAnalyzer::class);
        $this->inlineValidationAnalyzer = Mockery::mock(InlineValidationAnalyzer::class);
        $this->schemaGenerator = Mockery::mock(SchemaGenerator::class);
        $this->errorResponseGenerator = Mockery::mock(ErrorResponseGenerator::class);
        $this->authenticationAnalyzer = Mockery::mock(AuthenticationAnalyzer::class);
        $this->securitySchemeGenerator = Mockery::mock(SecuritySchemeGenerator::class);
        $this->paginationSchemaGenerator = Mockery::mock(PaginationSchemaGenerator::class);
        $this->paginationDetector = Mockery::mock(PaginationDetector::class);
        $this->exampleGenerator = Mockery::mock(ExampleGenerator::class);
        $this->responseSchemaGenerator = Mockery::mock(ResponseSchemaGenerator::class);

        $this->generator = new OpenApiGenerator(
            $this->requestAnalyzer,
            $this->resourceAnalyzer,
            $this->controllerAnalyzer,
            $this->inlineValidationAnalyzer,
            $this->schemaGenerator,
            $this->errorResponseGenerator,
            $this->authenticationAnalyzer,
            $this->securitySchemeGenerator,
            $this->paginationSchemaGenerator,
            $this->paginationDetector,
            $this->exampleGenerator,
            $this->responseSchemaGenerator
        );
    }

    #[Test]
    public function it_generates_basic_openapi_structure()
    {
        $routes = [];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')
            ->once();

        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')
            ->once()
            ->andReturn(null);

        $this->authenticationAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn(['schemes' => []]);

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->with([])
            ->andReturn([]);

        $result = $this->generator->generate($routes);

        $this->assertArrayHasKey('openapi', $result);
        $this->assertEquals('3.0.0', $result['openapi']);
        $this->assertArrayHasKey('info', $result);
        $this->assertArrayHasKey('paths', $result);
        $this->assertArrayHasKey('servers', $result);
        $this->assertArrayHasKey('components', $result);
    }

    #[Test]
    public function it_generates_operation_for_get_route()
    {
        Route::get('/api/users', 'UserController@index')->name('users.index');

        $routes = [
            [
                'uri' => 'api/users',
                'httpMethods' => ['GET'],
                'controller' => 'App\Http\Controllers\UserController',
                'action' => 'index',
                'method' => 'index',
                'middleware' => [],
                'name' => 'users.index',
                'parameters' => [],
            ],
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')
            ->once();

        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')
            ->once()
            ->andReturn(null);

        $this->authenticationAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn(['schemes' => []]);

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'method' => 'index',
                'responseType' => 'json',
                'hasValidation' => false,
            ]);

        // Inline validation analyzer won't be called since there's no inline validation

        // Resource analyzer won't be called since controllerInfo has no 'resource' key

        // Pagination detector won't be called without resource or collection response

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')
            ->once()
            ->andReturn([]);

        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')
            ->once()
            ->andReturn([]);

        // Response schema generator won't be called without response in controllerInfo

        // Example generator won't be called without resource

        $result = $this->generator->generate($routes);

        $this->assertArrayHasKey('/api/users', $result['paths']);
        $this->assertArrayHasKey('get', $result['paths']['/api/users']);
        $operation = $result['paths']['/api/users']['get'];

        $this->assertArrayHasKey('operationId', $operation);
        $this->assertArrayHasKey('summary', $operation);
        $this->assertArrayHasKey('tags', $operation);
        $this->assertArrayHasKey('responses', $operation);
    }

    #[Test]
    public function it_generates_request_body_for_post_route()
    {
        $routes = [
            [
                'uri' => 'api/users',
                'httpMethods' => ['POST'],
                'controller' => 'App\Http\Controllers\UserController',
                'action' => 'store',
                'method' => 'store',
                'middleware' => [],
                'name' => 'users.store',
                'parameters' => [],
            ],
        ];

        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')
            ->once();

        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')
            ->once()
            ->andReturn(null);

        $this->authenticationAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn(['schemes' => []]);

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'method' => 'store',
                'responseType' => 'json',
                'hasValidation' => true,
                'formRequest' => 'App\\Http\\Requests\\StoreUserRequest',
            ]);

        // Inline validation analyzer won't be called since there's no inline validation

        $this->requestAnalyzer->shouldReceive('analyzeWithConditionalRules')
            ->once()
            ->andReturn([
                'conditional_rules' => [],
                'parameters' => [
                    'rules' => $validationRules,
                    'rulesMethod' => true,
                ],
            ]);

        $this->requestAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'rules' => $validationRules,
                'rulesMethod' => true,
            ]);

        $this->requestAnalyzer->shouldReceive('analyzeWithDetails')
            ->once()
            ->andReturn([
                'rules' => $validationRules,
                'messages' => [],
                'attributes' => [],
            ]);

        // Note: generateFromRules is not called in this case

        $this->schemaGenerator->shouldReceive('generateFromParameters')
            ->once()
            ->andReturn([
                'type' => 'object',
                'required' => ['name', 'email'],
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 255],
                    'email' => ['type' => 'string', 'format' => 'email'],
                ],
            ]);

        // Note: ExampleGenerator is not called for request bodies

        // Resource analyzer won't be called since controllerInfo has no 'resource' key

        // Pagination detector won't be called without resource or collection response

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')
            ->once()
            ->andReturn([
                '422' => [
                    'description' => 'Validation error',
                    'content' => [
                        'application/json' => [
                            'schema' => ['type' => 'object'],
                        ],
                    ],
                ],
            ]);

        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')
            ->once()
            ->andReturn([]);

        $this->exampleGenerator->shouldReceive('generateErrorExample')
            ->times(2)
            ->andReturn([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'name' => ['The name field is required.'],
                    'email' => ['The email field is required.'],
                ],
            ]);

        // Response schema generator won't be called without response in controllerInfo

        // Example generator won't be called without resource

        $result = $this->generator->generate($routes);

        $this->assertArrayHasKey('/api/users', $result['paths']);
        $this->assertArrayHasKey('post', $result['paths']['/api/users']);
        $operation = $result['paths']['/api/users']['post'];

        $this->assertArrayHasKey('requestBody', $operation);
        $this->assertArrayHasKey('content', $operation['requestBody']);
        $this->assertArrayHasKey('application/json', $operation['requestBody']['content']);
        $this->assertArrayHasKey('schema', $operation['requestBody']['content']['application/json']);
        // Note: OpenApiGenerator doesn't add examples to request bodies
    }

    #[Test]
    public function it_handles_authenticated_routes()
    {
        $routes = [
            [
                'uri' => 'api/profile',
                'httpMethods' => ['GET'],
                'controller' => 'App\Http\Controllers\ProfileController',
                'action' => 'show',
                'method' => 'show',
                'middleware' => ['auth:sanctum'],
                'name' => 'profile.show',
                'parameters' => [],
            ],
        ];

        $authSchemes = [
            'sanctum' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
            ],
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')
            ->once();

        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')
            ->once()
            ->andReturn(null);

        $this->authenticationAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'schemes' => $authSchemes,
                'routes' => [
                    0 => ['type' => 'bearer', 'scheme' => 'sanctum'],
                ],
            ]);

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->with($authSchemes)
            ->andReturn([
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
            ]);

        $this->securitySchemeGenerator->shouldReceive('generateEndpointSecurity')
            ->once()
            ->with(['type' => 'bearer', 'scheme' => 'sanctum'])
            ->andReturn([
                ['bearerAuth' => []],
            ]);

        $this->controllerAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'method' => 'show',
                'responseType' => 'json',
                'hasValidation' => false,
            ]);

        // Inline validation analyzer won't be called since there's no inline validation

        // Resource analyzer won't be called since controllerInfo has no 'resource' key

        // Pagination detector won't be called without resource or collection response

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')
            ->once()
            ->andReturn([
                '401' => [
                    'description' => 'Unauthenticated',
                    'content' => [
                        'application/json' => [
                            'schema' => ['type' => 'object'],
                        ],
                    ],
                ],
            ]);

        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')
            ->once()
            ->andReturn([]);

        // Response schema generator won't be called without response in controllerInfo

        // Example generator won't be called without resource

        $result = $this->generator->generate($routes);

        $this->assertArrayHasKey('/api/profile', $result['paths']);
        $this->assertArrayHasKey('get', $result['paths']['/api/profile']);
        $operation = $result['paths']['/api/profile']['get'];

        $this->assertArrayHasKey('security', $operation);
        $this->assertIsArray($operation['security']);
        $this->assertNotEmpty($operation['security']);

        $this->assertArrayHasKey('components', $result);
        $this->assertArrayHasKey('securitySchemes', $result['components']);
        $this->assertArrayHasKey('bearerAuth', $result['components']['securitySchemes']);
    }

    #[Test]
    public function it_generates_path_parameters()
    {
        $routes = [
            [
                'uri' => 'api/users/{user}',
                'httpMethods' => ['GET'],
                'controller' => 'App\Http\Controllers\UserController',
                'action' => 'show',
                'method' => 'show',
                'middleware' => [],
                'name' => 'users.show',
                'parameters' => [
                    [
                        'name' => 'user',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')
            ->once();

        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')
            ->once()
            ->andReturn(null);

        $this->authenticationAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn(['schemes' => []]);

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'method' => 'show',
                'responseType' => 'json',
                'hasValidation' => false,
            ]);

        // Inline validation analyzer won't be called since there's no inline validation

        // Resource analyzer won't be called since controllerInfo has no 'resource' key

        // Pagination detector won't be called without resource or collection response

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')
            ->once()
            ->andReturn([]);

        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')
            ->once()
            ->andReturn([]);

        // Response schema generator won't be called without response in controllerInfo

        // Example generator won't be called without resource

        $result = $this->generator->generate($routes);

        $this->assertArrayHasKey('/api/users/{user}', $result['paths']);
        $this->assertArrayHasKey('get', $result['paths']['/api/users/{user}']);
        $operation = $result['paths']['/api/users/{user}']['get'];

        $this->assertArrayHasKey('parameters', $operation);
        $this->assertIsArray($operation['parameters']);

        $pathParam = collect($operation['parameters'])->firstWhere('in', 'path');
        $this->assertNotNull($pathParam);
        $this->assertEquals('user', $pathParam['name']);
        $this->assertTrue($pathParam['required']);
        $this->assertArrayHasKey('schema', $pathParam);
    }

    #[Test]
    public function it_handles_file_upload_routes()
    {
        $routes = [
            [
                'uri' => 'api/upload',
                'httpMethods' => ['POST'],
                'controller' => 'App\Http\Controllers\UploadController',
                'action' => 'store',
                'method' => 'store',
                'middleware' => [],
                'name' => 'upload.store',
                'parameters' => [],
            ],
        ];

        $validationRules = [
            'file' => 'required|file|mimes:jpg,png,pdf|max:2048',
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')
            ->once();

        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')
            ->once()
            ->andReturn(null);

        $this->authenticationAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn(['schemes' => []]);

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'method' => 'store',
                'responseType' => 'json',
                'hasValidation' => true,
                'inlineValidation' => $validationRules,
            ]);

        $this->inlineValidationAnalyzer->shouldReceive('generateParameters')
            ->once()
            ->with($validationRules)
            ->andReturn($validationRules);

        // Request analyzer won't be called since there's inline validation

        $this->schemaGenerator->shouldReceive('generateFromParameters')
            ->once()
            ->with($validationRules)
            ->andReturn([
                'content' => [
                    'multipart/form-data' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['file'],
                            'properties' => [
                                'file' => [
                                    'type' => 'string',
                                    'format' => 'binary',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        // Note: ExampleGenerator is not called for inline validation in request bodies

        // Resource analyzer won't be called since controllerInfo has no 'resource' key

        // Pagination detector won't be called without resource or collection response

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')
            ->once()
            ->andReturn([]);

        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')
            ->once()
            ->andReturn([]);

        // Response schema generator won't be called without response in controllerInfo

        // Example generator won't be called without resource

        $result = $this->generator->generate($routes);

        $this->assertArrayHasKey('/api/upload', $result['paths']);
        $this->assertArrayHasKey('post', $result['paths']['/api/upload']);
        $operation = $result['paths']['/api/upload']['post'];

        $this->assertArrayHasKey('requestBody', $operation);
        $this->assertArrayHasKey('content', $operation['requestBody']);
        $this->assertArrayHasKey('multipart/form-data', $operation['requestBody']['content']);
    }

    #[Test]
    public function it_generates_query_parameters()
    {
        $routes = [
            [
                'uri' => 'api/users',
                'httpMethods' => ['GET'],
                'controller' => 'App\Http\Controllers\UserController',
                'action' => 'index',
                'method' => 'index',
                'middleware' => [],
                'name' => 'users.index',
                'parameters' => [],
            ],
        ];

        $queryParameters = [
            ['name' => 'page', 'type' => 'integer', 'description' => 'Page number'],
            ['name' => 'per_page', 'type' => 'integer', 'description' => 'Items per page'],
            ['name' => 'search', 'type' => 'string', 'description' => 'Search query'],
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')
            ->once();

        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')
            ->once()
            ->andReturn(null);

        $this->authenticationAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn(['schemes' => []]);

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'method' => 'index',
                'responseType' => 'json',
                'hasValidation' => false,
                'queryParameters' => $queryParameters,
            ]);

        // Inline validation analyzer won't be called since there's no inline validation

        // Resource analyzer won't be called since controllerInfo has no 'resource' key

        // Note: PaginationDetector is not called without pagination info in controllerInfo

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')
            ->once()
            ->andReturn([]);

        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')
            ->once()
            ->andReturn([]);

        // Response schema generator won't be called without response in controllerInfo

        // Example generator won't be called without resource

        $result = $this->generator->generate($routes);

        $this->assertArrayHasKey('/api/users', $result['paths']);
        $this->assertArrayHasKey('get', $result['paths']['/api/users']);
        $operation = $result['paths']['/api/users']['get'];

        $this->assertArrayHasKey('parameters', $operation);
        $this->assertIsArray($operation['parameters']);

        $queryParams = collect($operation['parameters'])->where('in', 'query');
        $this->assertCount(3, $queryParams);

        $pageParam = $queryParams->firstWhere('name', 'page');
        $this->assertNotNull($pageParam);
        $this->assertEquals('query', $pageParam['in']);
        $this->assertEquals('integer', $pageParam['schema']['type']);
    }

    #[Test]
    public function it_generates_proper_tags()
    {
        $routes = [
            [
                'uri' => 'api/users',
                'httpMethods' => ['GET'],
                'controller' => 'App\Http\Controllers\UserController',
                'action' => 'index',
                'method' => 'index',
                'middleware' => [],
                'name' => 'users.index',
                'parameters' => [],
            ],
            [
                'uri' => 'api/posts',
                'httpMethods' => ['GET'],
                'controller' => 'App\Http\Controllers\PostController',
                'action' => 'index',
                'method' => 'index',
                'middleware' => [],
                'name' => 'posts.index',
                'parameters' => [],
            ],
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')
            ->once();

        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')
            ->once()
            ->andReturn(null);

        $this->authenticationAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn(['schemes' => []]);

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyze')
            ->twice()
            ->andReturn([
                'method' => 'index',
                'responseType' => 'json',
                'hasValidation' => false,
            ]);

        // Inline validation analyzer won't be called since there's no inline validation

        // Resource analyzer won't be called since controllerInfo has no 'resource' key

        // Pagination detector won't be called without resource or collection response

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')
            ->twice()
            ->andReturn([]);

        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')
            ->twice()
            ->andReturn([]);

        // Note: ResponseSchemaGenerator is not called without response info in controllerInfo

        // Example generator won't be called without resource

        $result = $this->generator->generate($routes);

        $userOperation = $result['paths']['/api/users']['get'];
        $postOperation = $result['paths']['/api/posts']['get'];

        $this->assertArrayHasKey('tags', $userOperation);
        $this->assertContains('User', $userOperation['tags']);

        $this->assertArrayHasKey('tags', $postOperation);
        $this->assertContains('Post', $postOperation['tags']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
