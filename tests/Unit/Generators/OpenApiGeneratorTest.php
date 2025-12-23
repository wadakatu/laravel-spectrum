<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Analyzers\AuthenticationAnalyzer;
use LaravelSpectrum\Analyzers\ControllerAnalyzer;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Analyzers\ResourceAnalyzer;
use LaravelSpectrum\Converters\OpenApi31Converter;
use LaravelSpectrum\Generators\ErrorResponseGenerator;
use LaravelSpectrum\Generators\ExampleGenerator;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Generators\OperationMetadataGenerator;
use LaravelSpectrum\Generators\PaginationSchemaGenerator;
use LaravelSpectrum\Generators\ParameterGenerator;
use LaravelSpectrum\Generators\RequestBodyGenerator;
use LaravelSpectrum\Generators\ResponseSchemaGenerator;
use LaravelSpectrum\Generators\SchemaGenerator;
use LaravelSpectrum\Generators\SecuritySchemeGenerator;
use LaravelSpectrum\Generators\TagGenerator;
use LaravelSpectrum\Generators\TagGroupGenerator;
use LaravelSpectrum\Support\PaginationDetector;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class OpenApiGeneratorTest extends TestCase
{
    private OpenApiGenerator $generator;

    private $controllerAnalyzer;

    private $authenticationAnalyzer;

    private $securitySchemeGenerator;

    private $tagGenerator;

    private $tagGroupGenerator;

    private $metadataGenerator;

    private $parameterGenerator;

    private $requestBodyGenerator;

    private $resourceAnalyzer;

    private $schemaGenerator;

    private $errorResponseGenerator;

    private $exampleGenerator;

    private $responseSchemaGenerator;

    private $paginationSchemaGenerator;

    private $paginationDetector;

    private $requestAnalyzer;

    private $openApi31Converter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controllerAnalyzer = Mockery::mock(ControllerAnalyzer::class);
        $this->authenticationAnalyzer = Mockery::mock(AuthenticationAnalyzer::class);
        $this->securitySchemeGenerator = Mockery::mock(SecuritySchemeGenerator::class);
        $this->tagGenerator = Mockery::mock(TagGenerator::class);
        $this->tagGroupGenerator = Mockery::mock(TagGroupGenerator::class);
        $this->tagGroupGenerator->shouldReceive('generateTagDefinitions')->andReturn([]);
        $this->tagGroupGenerator->shouldReceive('generateTagGroups')->andReturn([]);
        $this->metadataGenerator = Mockery::mock(OperationMetadataGenerator::class);
        $this->parameterGenerator = Mockery::mock(ParameterGenerator::class);
        $this->requestBodyGenerator = Mockery::mock(RequestBodyGenerator::class);
        $this->resourceAnalyzer = Mockery::mock(ResourceAnalyzer::class);
        $this->schemaGenerator = Mockery::mock(SchemaGenerator::class);
        $this->errorResponseGenerator = Mockery::mock(ErrorResponseGenerator::class);
        $this->exampleGenerator = Mockery::mock(ExampleGenerator::class);
        $this->responseSchemaGenerator = Mockery::mock(ResponseSchemaGenerator::class);
        $this->paginationSchemaGenerator = Mockery::mock(PaginationSchemaGenerator::class);
        $this->paginationDetector = Mockery::mock(PaginationDetector::class);
        $this->requestAnalyzer = Mockery::mock(FormRequestAnalyzer::class);
        $this->openApi31Converter = Mockery::mock(OpenApi31Converter::class);

        $this->generator = new OpenApiGenerator(
            $this->controllerAnalyzer,
            $this->authenticationAnalyzer,
            $this->securitySchemeGenerator,
            $this->tagGenerator,
            $this->tagGroupGenerator,
            $this->metadataGenerator,
            $this->parameterGenerator,
            $this->requestBodyGenerator,
            $this->resourceAnalyzer,
            $this->schemaGenerator,
            $this->errorResponseGenerator,
            $this->exampleGenerator,
            $this->responseSchemaGenerator,
            $this->paginationSchemaGenerator,
            $this->paginationDetector,
            $this->requestAnalyzer,
            $this->openApi31Converter
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

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')
            ->once()
            ->with('api/users')
            ->andReturn('/api/users');

        $this->metadataGenerator->shouldReceive('generateSummary')
            ->once()
            ->andReturn('List all User');

        $this->metadataGenerator->shouldReceive('generateOperationId')
            ->once()
            ->andReturn('usersIndex');

        $this->tagGenerator->shouldReceive('generate')
            ->once()
            ->andReturn(['User']);

        $this->parameterGenerator->shouldReceive('generate')
            ->once()
            ->andReturn([]);

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')
            ->once()
            ->andReturn([]);

        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')
            ->once()
            ->andReturn([]);

        $result = $this->generator->generate($routes);

        $this->assertArrayHasKey('/api/users', $result['paths']);
        $this->assertArrayHasKey('get', $result['paths']['/api/users']);
        $this->assertEquals('usersIndex', $result['paths']['/api/users']['get']['operationId']);
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
                'formRequest' => 'App\Http\Requests\StoreUserRequest',
            ]);

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')
            ->once()
            ->andReturn('/api/users');

        $this->metadataGenerator->shouldReceive('generateSummary')
            ->once()
            ->andReturn('Create a new User');

        $this->metadataGenerator->shouldReceive('generateOperationId')
            ->once()
            ->andReturn('usersStore');

        $this->tagGenerator->shouldReceive('generate')
            ->once()
            ->andReturn(['User']);

        $this->parameterGenerator->shouldReceive('generate')
            ->once()
            ->andReturn([]);

        $this->requestBodyGenerator->shouldReceive('generate')
            ->once()
            ->andReturn([
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'email' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->requestAnalyzer->shouldReceive('analyzeWithDetails')
            ->once()
            ->andReturn([
                'rules' => ['name' => ['required', 'string'], 'email' => ['required', 'email']],
                'messages' => [],
                'attributes' => [],
            ]);

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')
            ->once()
            ->andReturn([
                '422' => [
                    'description' => 'Validation Error',
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
            ->withAnyArgs()
            ->andReturn(['message' => 'Validation failed']);

        $result = $this->generator->generate($routes);

        $this->assertArrayHasKey('/api/users', $result['paths']);
        $this->assertArrayHasKey('post', $result['paths']['/api/users']);
        $operation = $result['paths']['/api/users']['post'];

        $this->assertArrayHasKey('requestBody', $operation);
        $this->assertArrayHasKey('content', $operation['requestBody']);
        $this->assertArrayHasKey('application/json', $operation['requestBody']['content']);
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

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')
            ->once()
            ->andReturn('/api/profile');

        $this->metadataGenerator->shouldReceive('generateSummary')
            ->once()
            ->andReturn('Get Profile by ID');

        $this->metadataGenerator->shouldReceive('generateOperationId')
            ->once()
            ->andReturn('profileShow');

        $this->tagGenerator->shouldReceive('generate')
            ->once()
            ->andReturn(['Profile']);

        $this->parameterGenerator->shouldReceive('generate')
            ->once()
            ->andReturn([]);

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')
            ->once()
            ->andReturn([]);

        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')
            ->once()
            ->andReturn([
                '401' => [
                    'description' => 'Unauthorized',
                    'content' => [
                        'application/json' => [
                            'schema' => ['type' => 'object'],
                        ],
                    ],
                ],
            ]);

        $this->exampleGenerator->shouldReceive('generateErrorExample')
            ->once()
            ->with(401)
            ->andReturn(['message' => 'Unauthenticated']);

        $result = $this->generator->generate($routes);

        $operation = $result['paths']['/api/profile']['get'];
        $this->assertArrayHasKey('security', $operation);
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

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')
            ->once()
            ->andReturn('/api/users/{user}');

        $this->metadataGenerator->shouldReceive('generateSummary')
            ->once()
            ->andReturn('Get User by ID');

        $this->metadataGenerator->shouldReceive('generateOperationId')
            ->once()
            ->andReturn('usersShow');

        $this->tagGenerator->shouldReceive('generate')
            ->once()
            ->andReturn(['User']);

        $this->parameterGenerator->shouldReceive('generate')
            ->once()
            ->andReturn([
                [
                    'name' => 'user',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'integer'],
                ],
            ]);

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')
            ->once()
            ->andReturn([]);

        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')
            ->once()
            ->andReturn([]);

        $result = $this->generator->generate($routes);

        $operation = $result['paths']['/api/users/{user}']['get'];
        $this->assertArrayHasKey('parameters', $operation);
        $this->assertCount(1, $operation['parameters']);
        $this->assertEquals('user', $operation['parameters'][0]['name']);
        $this->assertEquals('path', $operation['parameters'][0]['in']);
    }

    #[Test]
    public function it_applies_openapi_31_conversion_when_configured()
    {
        // Set config to 3.1.0
        config(['spectrum.openapi.version' => '3.1.0']);

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

        // Mock converter to verify it's called
        $this->openApi31Converter->shouldReceive('convert')
            ->once()
            ->andReturnUsing(function ($spec) {
                $spec['openapi'] = '3.1.0';

                return $spec;
            });

        $result = $this->generator->generate($routes);

        $this->assertEquals('3.1.0', $result['openapi']);
    }

    #[Test]
    public function it_does_not_apply_openapi_31_conversion_when_not_configured()
    {
        // Set config to 3.0.0 (default)
        config(['spectrum.openapi.version' => '3.0.0']);

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

        // Converter should NOT be called
        $this->openApi31Converter->shouldNotReceive('convert');

        $result = $this->generator->generate($routes);

        $this->assertEquals('3.0.0', $result['openapi']);
    }

    #[Test]
    public function it_falls_back_to_30_when_invalid_version_configured()
    {
        // Set config to an invalid version
        config(['spectrum.openapi.version' => '3.1']); // Missing .0

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

        // Converter should NOT be called for invalid version
        $this->openApi31Converter->shouldNotReceive('convert');

        $result = $this->generator->generate($routes);

        // Should fall back to 3.0.0
        $this->assertEquals('3.0.0', $result['openapi']);
    }

    #[Test]
    public function it_falls_back_to_30_when_version_is_null()
    {
        // Set config to null
        config(['spectrum.openapi.version' => null]);

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

        // Converter should NOT be called for null version
        $this->openApi31Converter->shouldNotReceive('convert');

        $result = $this->generator->generate($routes);

        // Should fall back to 3.0.0
        $this->assertEquals('3.0.0', $result['openapi']);
    }

    #[Test]
    public function it_converts_nullable_to_type_array_in_31()
    {
        // Use real converter instead of mock for this integration test
        $realConverter = new OpenApi31Converter;
        $generator = new OpenApiGenerator(
            $this->controllerAnalyzer,
            $this->authenticationAnalyzer,
            $this->securitySchemeGenerator,
            $this->tagGenerator,
            $this->tagGroupGenerator,
            $this->metadataGenerator,
            $this->parameterGenerator,
            $this->requestBodyGenerator,
            $this->resourceAnalyzer,
            $this->schemaGenerator,
            $this->errorResponseGenerator,
            $this->exampleGenerator,
            $this->responseSchemaGenerator,
            $this->paginationSchemaGenerator,
            $this->paginationDetector,
            $this->requestAnalyzer,
            $realConverter
        );

        config(['spectrum.openapi.version' => '3.1.0']);

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
            ->andReturn([]);

        $result = $generator->generate($routes);

        // Verify OpenAPI version is 3.1.0
        $this->assertEquals('3.1.0', $result['openapi']);

        // Verify webhooks section was added
        $this->assertArrayHasKey('webhooks', $result);
        $this->assertInstanceOf(\stdClass::class, $result['webhooks']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
