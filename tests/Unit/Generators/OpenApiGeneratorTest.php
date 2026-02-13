<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use LaravelSpectrum\Analyzers\AuthenticationAnalyzer;
use LaravelSpectrum\Analyzers\ControllerAnalyzer;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Analyzers\ResourceAnalyzer;
use LaravelSpectrum\Converters\OpenApi31Converter;
use LaravelSpectrum\DTO\AuthenticationResult;
use LaravelSpectrum\DTO\AuthenticationScheme;
use LaravelSpectrum\DTO\AuthenticationType;
use LaravelSpectrum\DTO\ControllerInfo;
use LaravelSpectrum\DTO\OpenApiParameter;
use LaravelSpectrum\DTO\OpenApiRequestBody;
use LaravelSpectrum\DTO\OpenApiResponse;
use LaravelSpectrum\DTO\OpenApiSchema;
use LaravelSpectrum\DTO\ResponseLinkInfo;
use LaravelSpectrum\DTO\ResourceInfo;
use LaravelSpectrum\DTO\RouteAuthentication;
use LaravelSpectrum\DTO\TagDefinition;
use LaravelSpectrum\Generators\CallbackGenerator;
use LaravelSpectrum\Generators\ErrorResponseGenerator;
use LaravelSpectrum\Generators\ExampleGenerator;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Generators\OperationMetadataGenerator;
use LaravelSpectrum\Generators\PaginationSchemaGenerator;
use LaravelSpectrum\Generators\ParameterGenerator;
use LaravelSpectrum\Generators\RequestBodyGenerator;
use LaravelSpectrum\Generators\ResponseSchemaGenerator;
use LaravelSpectrum\Generators\SchemaGenerator;
use LaravelSpectrum\Generators\SchemaRegistry;
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

    private $schemaRegistry;

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
        $this->schemaRegistry = new SchemaRegistry;

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
            $this->openApi31Converter,
            new CallbackGenerator,
            $this->schemaRegistry
        );
    }

    /**
     * Helper method to generate OpenAPI spec as array for backward compatibility in tests.
     *
     * @param  array<int, array<string, mixed>>  $routes
     * @return array<string, mixed>
     */
    private function generateAsArray(array $routes): array
    {
        return $this->generator->generate($routes)->toArray();
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
            ->andReturn(AuthenticationResult::empty());

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->with([])
            ->andReturn([]);

        $result = $this->generateAsArray($routes);

        $this->assertArrayHasKey('openapi', $result);
        $this->assertEquals('3.0.0', $result['openapi']);
        $this->assertArrayHasKey('info', $result);
        $this->assertArrayHasKey('paths', $result);
        $this->assertArrayHasKey('servers', $result);
        $this->assertArrayHasKey('components', $result);
    }

    #[Test]
    public function it_includes_contact_in_info_when_configured(): void
    {
        config([
            'spectrum.contact' => [
                'name' => 'API Support',
                'email' => 'api@example.com',
                'url' => 'https://example.com/support',
            ],
        ]);

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')->once();
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->once()->andReturn(null);
        $this->authenticationAnalyzer->shouldReceive('analyze')->once()->andReturn(AuthenticationResult::empty());
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->once()->with([])->andReturn([]);

        $result = $this->generateAsArray([]);

        $this->assertArrayHasKey('contact', $result['info']);
        $this->assertEquals('API Support', $result['info']['contact']['name']);
        $this->assertEquals('api@example.com', $result['info']['contact']['email']);
        $this->assertEquals('https://example.com/support', $result['info']['contact']['url']);
    }

    #[Test]
    public function it_includes_license_in_info_when_configured(): void
    {
        config([
            'spectrum.license' => [
                'name' => 'MIT',
                'url' => 'https://opensource.org/licenses/MIT',
            ],
        ]);

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')->once();
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->once()->andReturn(null);
        $this->authenticationAnalyzer->shouldReceive('analyze')->once()->andReturn(AuthenticationResult::empty());
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->once()->with([])->andReturn([]);

        $result = $this->generateAsArray([]);

        $this->assertArrayHasKey('license', $result['info']);
        $this->assertEquals('MIT', $result['info']['license']['name']);
        $this->assertEquals('https://opensource.org/licenses/MIT', $result['info']['license']['url']);
    }

    #[Test]
    public function it_includes_terms_of_service_in_info_when_configured(): void
    {
        config(['spectrum.terms_of_service' => 'https://example.com/terms']);

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')->once();
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->once()->andReturn(null);
        $this->authenticationAnalyzer->shouldReceive('analyze')->once()->andReturn(AuthenticationResult::empty());
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->once()->with([])->andReturn([]);

        $result = $this->generateAsArray([]);

        $this->assertArrayHasKey('termsOfService', $result['info']);
        $this->assertEquals('https://example.com/terms', $result['info']['termsOfService']);
    }

    #[Test]
    public function it_omits_empty_info_fields(): void
    {
        // Ensure no contact/license/terms configured
        config([
            'spectrum.contact' => null,
            'spectrum.license' => null,
            'spectrum.terms_of_service' => null,
        ]);

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')->once();
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->once()->andReturn(null);
        $this->authenticationAnalyzer->shouldReceive('analyze')->once()->andReturn(AuthenticationResult::empty());
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->once()->with([])->andReturn([]);

        $result = $this->generateAsArray([]);

        $this->assertArrayNotHasKey('contact', $result['info']);
        $this->assertArrayNotHasKey('license', $result['info']);
        $this->assertArrayNotHasKey('termsOfService', $result['info']);
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
            ->andReturn(AuthenticationResult::empty());

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')
            ->once()
            ->andReturn(ControllerInfo::fromArray([
                'method' => 'index',
                'responseType' => 'json',
                'hasValidation' => false,
            ]));

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

        $result = $this->generateAsArray($routes);

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
            ->andReturn(AuthenticationResult::empty());

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')
            ->once()
            ->andReturn(ControllerInfo::fromArray([
                'method' => 'store',
                'responseType' => 'json',
                'hasValidation' => true,
                'formRequest' => 'App\Http\Requests\StoreUserRequest',
            ]));

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
            ->andReturn(new OpenApiRequestBody(
                content: [
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
                required: true,
            ));

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
                '422' => new OpenApiResponse(
                    statusCode: 422,
                    description: 'Validation Error',
                    content: [
                        'application/json' => [
                            'schema' => ['type' => 'object'],
                        ],
                    ],
                ),
            ]);

        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')
            ->once()
            ->andReturn([]);

        $this->exampleGenerator->shouldReceive('generateErrorExample')
            ->withAnyArgs()
            ->andReturn(['message' => 'Validation failed']);

        $result = $this->generateAsArray($routes);

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

        $sanctumScheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'sanctum',
            scheme: 'bearer',
            bearerFormat: 'JWT',
        );

        $routeAuth = new RouteAuthentication(
            scheme: $sanctumScheme,
            middleware: ['auth:sanctum'],
            required: true,
        );

        $authResult = new AuthenticationResult(
            schemes: ['sanctum' => $sanctumScheme],
            routes: [0 => $routeAuth],
        );

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')
            ->once();

        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')
            ->once()
            ->andReturn(null);

        $this->authenticationAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn($authResult);

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->with(['sanctum' => $sanctumScheme->toArray()])
            ->andReturn([
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
            ]);

        $this->securitySchemeGenerator->shouldReceive('generateEndpointSecurity')
            ->once()
            ->with($routeAuth->toArray())
            ->andReturn([
                ['bearerAuth' => []],
            ]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')
            ->once()
            ->andReturn(ControllerInfo::fromArray([
                'method' => 'show',
                'responseType' => 'json',
                'hasValidation' => false,
            ]));

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
                '401' => new OpenApiResponse(
                    statusCode: 401,
                    description: 'Unauthorized',
                    content: [
                        'application/json' => [
                            'schema' => ['type' => 'object'],
                        ],
                    ],
                ),
            ]);

        $this->exampleGenerator->shouldReceive('generateErrorExample')
            ->once()
            ->with(401)
            ->andReturn(['message' => 'Unauthenticated']);

        $result = $this->generateAsArray($routes);

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
            ->andReturn(AuthenticationResult::empty());

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')
            ->once()
            ->andReturn(ControllerInfo::fromArray([
                'method' => 'show',
                'responseType' => 'json',
                'hasValidation' => false,
            ]));

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
                new OpenApiParameter(
                    name: 'user',
                    in: OpenApiParameter::IN_PATH,
                    required: true,
                    schema: OpenApiSchema::integer(),
                ),
            ]);

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')
            ->once()
            ->andReturn([]);

        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')
            ->once()
            ->andReturn([]);

        $result = $this->generateAsArray($routes);

        $operation = $result['paths']['/api/users/{user}']['get'];
        $this->assertArrayHasKey('parameters', $operation);
        $this->assertCount(1, $operation['parameters']);
        $this->assertEquals('user', $operation['parameters'][0]['name']);
        $this->assertEquals('path', $operation['parameters'][0]['in']);
    }

    #[Test]
    public function it_includes_response_links_in_generated_responses(): void
    {
        $routes = [
            [
                'uri' => 'api/users',
                'httpMethods' => ['POST'],
                'controller' => 'App\Http\Controllers\UserController',
                'method' => 'store',
                'middleware' => [],
                'parameters' => [],
            ],
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')->once();
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->once()->andReturn(null);
        $this->authenticationAnalyzer->shouldReceive('analyze')->once()->andReturn(AuthenticationResult::empty());
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->once()->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')
            ->once()
            ->andReturn(new ControllerInfo(
                responseLinks: [
                    new ResponseLinkInfo(
                        statusCode: 201,
                        name: 'GetUserById',
                        operationId: 'usersShow',
                        parameters: ['user' => '$response.body#/id'],
                    ),
                ],
            ));

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')->once()->andReturn('/api/users');
        $this->metadataGenerator->shouldReceive('generateSummary')->once()->andReturn('Create User');
        $this->metadataGenerator->shouldReceive('generateOperationId')->once()->andReturn('usersStore');
        $this->tagGenerator->shouldReceive('generate')->once()->andReturn(['User']);
        $this->parameterGenerator->shouldReceive('generate')->once()->andReturn([]);
        $this->requestBodyGenerator->shouldReceive('generate')->once()->andReturn(null);

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')->once()->andReturn([]);
        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')->once()->andReturn([]);

        $result = $this->generateAsArray($routes);

        $response = $result['paths']['/api/users']['post']['responses']['201'];
        $this->assertArrayHasKey('links', $response);
        $this->assertArrayHasKey('GetUserById', $response['links']);
        $this->assertSame('usersShow', $response['links']['GetUserById']['operationId']);
    }

    #[Test]
    public function it_merges_multiple_response_links_for_same_status(): void
    {
        $routes = [
            [
                'uri' => 'api/users',
                'httpMethods' => ['POST'],
                'controller' => 'App\Http\Controllers\UserController',
                'method' => 'store',
                'middleware' => [],
                'parameters' => [],
            ],
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')->once();
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->once()->andReturn(null);
        $this->authenticationAnalyzer->shouldReceive('analyze')->once()->andReturn(AuthenticationResult::empty());
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->once()->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')
            ->once()
            ->andReturn(new ControllerInfo(
                responseLinks: [
                    new ResponseLinkInfo(
                        statusCode: 201,
                        name: 'GetUserById',
                        operationId: 'usersShow',
                        parameters: ['user' => '$response.body#/id'],
                    ),
                    new ResponseLinkInfo(
                        statusCode: 201,
                        name: 'GetUserPosts',
                        operationId: 'usersPostsIndex',
                        parameters: ['user' => '$response.body#/id'],
                    ),
                ],
            ));

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')->once()->andReturn('/api/users');
        $this->metadataGenerator->shouldReceive('generateSummary')->once()->andReturn('Create User');
        $this->metadataGenerator->shouldReceive('generateOperationId')->once()->andReturn('usersStore');
        $this->tagGenerator->shouldReceive('generate')->once()->andReturn(['User']);
        $this->parameterGenerator->shouldReceive('generate')->once()->andReturn([]);
        $this->requestBodyGenerator->shouldReceive('generate')->once()->andReturn(null);
        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')->once()->andReturn([]);
        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')->once()->andReturn([]);

        $result = $this->generateAsArray($routes);
        $links = $result['paths']['/api/users']['post']['responses']['201']['links'];

        $this->assertArrayHasKey('GetUserById', $links);
        $this->assertArrayHasKey('GetUserPosts', $links);
    }

    #[Test]
    public function it_skips_response_links_when_status_code_response_does_not_exist(): void
    {
        $routes = [
            [
                'uri' => 'api/users',
                'httpMethods' => ['GET'],
                'controller' => 'App\Http\Controllers\UserController',
                'method' => 'index',
                'middleware' => [],
                'parameters' => [],
            ],
        ];

        Log::spy();

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')->once();
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->once()->andReturn(null);
        $this->authenticationAnalyzer->shouldReceive('analyze')->once()->andReturn(AuthenticationResult::empty());
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->once()->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')
            ->once()
            ->andReturn(new ControllerInfo(
                responseLinks: [
                    new ResponseLinkInfo(
                        statusCode: 201,
                        name: 'GetUserById',
                        operationId: 'usersShow',
                    ),
                ],
            ));

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')->once()->andReturn('/api/users');
        $this->metadataGenerator->shouldReceive('generateSummary')->once()->andReturn('List Users');
        $this->metadataGenerator->shouldReceive('generateOperationId')->once()->andReturn('usersIndex');
        $this->tagGenerator->shouldReceive('generate')->once()->andReturn(['User']);
        $this->parameterGenerator->shouldReceive('generate')->once()->andReturn([]);
        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')->once()->andReturn([]);
        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')->once()->andReturn([]);

        $result = $this->generateAsArray($routes);
        $response200 = $result['paths']['/api/users']['get']['responses']['200'];

        $this->assertArrayNotHasKey('links', $response200);
        Log::shouldHaveReceived('warning')->once();
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
            ->andReturn(AuthenticationResult::empty());

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

        $result = $this->generateAsArray($routes);

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
            ->andReturn(AuthenticationResult::empty());

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->with([])
            ->andReturn([]);

        // Converter should NOT be called
        $this->openApi31Converter->shouldNotReceive('convert');

        $result = $this->generateAsArray($routes);

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
            ->andReturn(AuthenticationResult::empty());

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->with([])
            ->andReturn([]);

        // Converter should NOT be called for invalid version
        $this->openApi31Converter->shouldNotReceive('convert');

        $result = $this->generateAsArray($routes);

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
            ->andReturn(AuthenticationResult::empty());

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->with([])
            ->andReturn([]);

        // Converter should NOT be called for null version
        $this->openApi31Converter->shouldNotReceive('convert');

        $result = $this->generateAsArray($routes);

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
            $realConverter,
            new CallbackGenerator,
            new SchemaRegistry
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
            ->andReturn(AuthenticationResult::empty());

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->andReturn([]);

        $result = $generator->generate($routes)->toArray();

        // Verify OpenAPI version is 3.1.0
        $this->assertEquals('3.1.0', $result['openapi']);

        // Verify webhooks section was added
        $this->assertArrayHasKey('webhooks', $result);
        $this->assertInstanceOf(\stdClass::class, $result['webhooks']);
    }

    #[Test]
    public function it_registers_resource_schemas_in_components(): void
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

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')->once();
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->once()->andReturn(null);
        $this->authenticationAnalyzer->shouldReceive('analyze')->once()->andReturn(AuthenticationResult::empty());
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->once()->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')
            ->once()
            ->andReturn(ControllerInfo::fromArray([
                'method' => 'index',
                'responseType' => 'json',
                'hasValidation' => false,
                'resource' => 'App\Http\Resources\UserResource',
                'returnsCollection' => true,
            ]));

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')->once()->andReturn('/api/users');
        $this->metadataGenerator->shouldReceive('generateSummary')->once()->andReturn('List all User');
        $this->metadataGenerator->shouldReceive('generateOperationId')->once()->andReturn('usersIndex');

        $this->tagGenerator->shouldReceive('generate')->once()->andReturn(['User']);
        $this->parameterGenerator->shouldReceive('generate')->once()->andReturn([]);
        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')->once()->andReturn([]);
        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')->once()->andReturn([]);

        $this->resourceAnalyzer->shouldReceive('analyze')
            ->once()
            ->with('App\Http\Resources\UserResource')
            ->andReturn(ResourceInfo::fromArray([
                'properties' => [
                    'id' => ['type' => 'integer', 'example' => 1],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                ],
            ]));

        $this->schemaGenerator->shouldReceive('generateFromResource')
            ->once()
            ->andReturn([
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'example' => 1],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                ],
            ]);

        $this->exampleGenerator->shouldReceive('generateFromResource')
            ->once()
            ->andReturn([
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]);

        $this->exampleGenerator->shouldReceive('generateCollectionExample')
            ->once()
            ->andReturn([
                ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
                ['id' => 2, 'name' => 'Jane Doe', 'email' => 'jane@example.com'],
            ]);

        $result = $this->generateAsArray($routes);

        // Verify that schema is registered in components.schemas
        $this->assertArrayHasKey('components', $result);
        $this->assertArrayHasKey('schemas', $result['components']);
        $this->assertArrayHasKey('UserResource', $result['components']['schemas']);

        // Verify the schema structure
        $schema = $result['components']['schemas']['UserResource'];
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('email', $schema['properties']);
    }

    #[Test]
    public function it_uses_ref_for_resource_schema_in_response(): void
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
                'parameters' => [],
            ],
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')->once();
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->once()->andReturn(null);
        $this->authenticationAnalyzer->shouldReceive('analyze')->once()->andReturn(AuthenticationResult::empty());
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->once()->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')
            ->once()
            ->andReturn(ControllerInfo::fromArray([
                'method' => 'show',
                'responseType' => 'json',
                'hasValidation' => false,
                'resource' => 'App\Http\Resources\UserResource',
                'returnsCollection' => false,
            ]));

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')->once()->andReturn('/api/users/{user}');
        $this->metadataGenerator->shouldReceive('generateSummary')->once()->andReturn('Get User by ID');
        $this->metadataGenerator->shouldReceive('generateOperationId')->once()->andReturn('usersShow');

        $this->tagGenerator->shouldReceive('generate')->once()->andReturn(['User']);
        $this->parameterGenerator->shouldReceive('generate')->once()->andReturn([]);
        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')->once()->andReturn([]);
        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')->once()->andReturn([]);

        $this->resourceAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn(ResourceInfo::fromArray([
                'properties' => [
                    'id' => ['type' => 'integer', 'example' => 1],
                    'name' => ['type' => 'string'],
                ],
            ]));

        $this->schemaGenerator->shouldReceive('generateFromResource')
            ->once()
            ->andReturn([
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'example' => 1],
                    'name' => ['type' => 'string'],
                ],
            ]);

        $this->exampleGenerator->shouldReceive('generateFromResource')
            ->once()
            ->andReturn(['id' => 1, 'name' => 'John Doe']);

        $result = $this->generateAsArray($routes);

        // Verify that the response uses $ref
        $responseContent = $result['paths']['/api/users/{user}']['get']['responses']['200']['content']['application/json'];
        $this->assertArrayHasKey('schema', $responseContent);
        $this->assertArrayHasKey('$ref', $responseContent['schema']);
        $this->assertEquals('#/components/schemas/UserResource', $responseContent['schema']['$ref']);
    }

    #[Test]
    public function it_uses_ref_for_collection_response(): void
    {
        $routes = [
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

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')->once();
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->once()->andReturn(null);
        $this->authenticationAnalyzer->shouldReceive('analyze')->once()->andReturn(AuthenticationResult::empty());
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->once()->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')
            ->once()
            ->andReturn(ControllerInfo::fromArray([
                'method' => 'index',
                'responseType' => 'json',
                'hasValidation' => false,
                'resource' => 'App\Http\Resources\PostResource',
                'returnsCollection' => true,
            ]));

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')->once()->andReturn('/api/posts');
        $this->metadataGenerator->shouldReceive('generateSummary')->once()->andReturn('List all Post');
        $this->metadataGenerator->shouldReceive('generateOperationId')->once()->andReturn('postsIndex');

        $this->tagGenerator->shouldReceive('generate')->once()->andReturn(['Post']);
        $this->parameterGenerator->shouldReceive('generate')->once()->andReturn([]);
        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')->once()->andReturn([]);
        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')->once()->andReturn([]);

        $this->resourceAnalyzer->shouldReceive('analyze')
            ->once()
            ->andReturn(ResourceInfo::fromArray([
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                ],
            ]));

        $this->schemaGenerator->shouldReceive('generateFromResource')
            ->once()
            ->andReturn([
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                ],
            ]);

        $this->exampleGenerator->shouldReceive('generateFromResource')
            ->once()
            ->andReturn(['id' => 1, 'title' => 'Hello World']);

        $this->exampleGenerator->shouldReceive('generateCollectionExample')
            ->once()
            ->andReturn([
                ['id' => 1, 'title' => 'Hello World'],
                ['id' => 2, 'title' => 'Another Post'],
            ]);

        $result = $this->generateAsArray($routes);

        // Verify that the response uses $ref for array items
        $responseContent = $result['paths']['/api/posts']['get']['responses']['200']['content']['application/json'];
        $this->assertArrayHasKey('schema', $responseContent);
        $this->assertEquals('array', $responseContent['schema']['type']);
        $this->assertArrayHasKey('items', $responseContent['schema']);
        $this->assertArrayHasKey('$ref', $responseContent['schema']['items']);
        $this->assertEquals('#/components/schemas/PostResource', $responseContent['schema']['items']['$ref']);
    }

    #[Test]
    public function it_reuses_same_schema_for_multiple_endpoints(): void
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
                'uri' => 'api/users/{user}',
                'httpMethods' => ['GET'],
                'controller' => 'App\Http\Controllers\UserController',
                'action' => 'show',
                'method' => 'show',
                'middleware' => [],
                'name' => 'users.show',
                'parameters' => [],
            ],
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')->once();
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->once()->andReturn(null);
        $this->authenticationAnalyzer->shouldReceive('analyze')->once()->andReturn(AuthenticationResult::empty());
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->once()->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')
            ->twice()
            ->andReturn(ControllerInfo::fromArray([
                'method' => 'index',
                'responseType' => 'json',
                'hasValidation' => false,
                'resource' => 'App\Http\Resources\UserResource',
                'returnsCollection' => false,
            ]));

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')
            ->twice()
            ->andReturn('/api/users', '/api/users/{user}');
        $this->metadataGenerator->shouldReceive('generateSummary')->twice()->andReturn('List all User', 'Get User');
        $this->metadataGenerator->shouldReceive('generateOperationId')->twice()->andReturn('usersIndex', 'usersShow');

        $this->tagGenerator->shouldReceive('generate')->twice()->andReturn(['User']);
        $this->parameterGenerator->shouldReceive('generate')->twice()->andReturn([]);
        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')->twice()->andReturn([]);
        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')->twice()->andReturn([]);

        // Resource analyzer should only be called once per unique resource
        $this->resourceAnalyzer->shouldReceive('analyze')
            ->once()
            ->with('App\Http\Resources\UserResource')
            ->andReturn(ResourceInfo::fromArray([
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                ],
            ]));

        $this->schemaGenerator->shouldReceive('generateFromResource')
            ->once()
            ->andReturn([
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                ],
            ]);

        $this->exampleGenerator->shouldReceive('generateFromResource')
            ->once()
            ->andReturn(['id' => 1, 'name' => 'John Doe']);

        $result = $this->generateAsArray($routes);

        // Verify only one schema is registered
        $this->assertCount(1, $result['components']['schemas']);
        $this->assertArrayHasKey('UserResource', $result['components']['schemas']);

        // Both endpoints should use the same $ref
        $this->assertEquals(
            '#/components/schemas/UserResource',
            $result['paths']['/api/users']['get']['responses']['200']['content']['application/json']['schema']['$ref']
        );
        $this->assertEquals(
            '#/components/schemas/UserResource',
            $result['paths']['/api/users/{user}']['get']['responses']['200']['content']['application/json']['schema']['$ref']
        );
    }

    #[Test]
    public function it_includes_401_response_for_routes_with_auth_middleware(): void
    {
        $routes = [
            [
                'uri' => 'api/users',
                'httpMethods' => ['GET'],
                'controller' => 'App\Http\Controllers\UserController',
                'method' => 'index',
                'middleware' => ['auth:sanctum'],
            ],
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes');
        $this->authenticationAnalyzer->shouldReceive('analyze')->andReturn(AuthenticationResult::empty());
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->andReturn(null);
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->andReturn([]);

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')
            ->andReturnUsing(fn ($uri) => '/'.$uri);
        $this->metadataGenerator->shouldReceive('generateSummary')->andReturn('Summary');
        $this->metadataGenerator->shouldReceive('generateOperationId')->andReturn('operationId');

        $this->tagGenerator->shouldReceive('generate')->andReturn(['Users']);

        $this->parameterGenerator->shouldReceive('generate')->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')->andReturn(ControllerInfo::empty());

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')->andReturn([]);
        // Verify that getDefaultErrorResponses is called with requiresAuth=true
        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')
            ->once()
            ->with('get', true, false)
            ->andReturn([
                '401' => new OpenApiResponse(
                    statusCode: 401,
                    description: 'Unauthorized',
                    content: [
                        'application/json' => [
                            'schema' => ['type' => 'object'],
                        ],
                    ],
                ),
            ]);

        $this->exampleGenerator->shouldReceive('generateErrorExample')
            ->with(401)
            ->andReturn(['message' => 'Unauthenticated']);

        $result = $this->generateAsArray($routes);

        $this->assertArrayHasKey('401', $result['paths']['/api/users']['get']['responses']);
    }

    #[Test]
    public function it_excludes_401_response_for_routes_without_auth_middleware(): void
    {
        $routes = [
            [
                'uri' => 'api/public',
                'httpMethods' => ['GET'],
                'controller' => 'App\Http\Controllers\PublicController',
                'method' => 'index',
                'middleware' => [],
            ],
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes');
        $this->authenticationAnalyzer->shouldReceive('analyze')->andReturn(AuthenticationResult::empty());
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->andReturn(null);
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->andReturn([]);

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')
            ->andReturnUsing(fn ($uri) => '/'.$uri);
        $this->metadataGenerator->shouldReceive('generateSummary')->andReturn('Summary');
        $this->metadataGenerator->shouldReceive('generateOperationId')->andReturn('operationId');

        $this->tagGenerator->shouldReceive('generate')->andReturn(['Public']);

        $this->parameterGenerator->shouldReceive('generate')->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')->andReturn(ControllerInfo::empty());

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')->andReturn([]);
        // Verify that getDefaultErrorResponses is called with requiresAuth=false
        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')
            ->once()
            ->with('get', false, false)
            ->andReturn([]);

        $result = $this->generateAsArray($routes);

        $this->assertArrayNotHasKey('401', $result['paths']['/api/public']['get']['responses']);
    }

    #[Test]
    public function it_detects_auth_with_guard_colon_prefix(): void
    {
        $routes = [
            [
                'uri' => 'api/users',
                'httpMethods' => ['GET'],
                'controller' => 'App\Http\Controllers\UserController',
                'method' => 'index',
                'middleware' => ['auth:api'],
            ],
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes');
        $this->authenticationAnalyzer->shouldReceive('analyze')->andReturn(AuthenticationResult::empty());
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->andReturn(null);
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->andReturn([]);

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')
            ->andReturnUsing(fn ($uri) => '/'.$uri);
        $this->metadataGenerator->shouldReceive('generateSummary')->andReturn('Summary');
        $this->metadataGenerator->shouldReceive('generateOperationId')->andReturn('operationId');

        $this->tagGenerator->shouldReceive('generate')->andReturn(['Users']);

        $this->parameterGenerator->shouldReceive('generate')->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')->andReturn(ControllerInfo::empty());

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')->andReturn([]);
        // auth:api should be detected as requiring auth
        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')
            ->once()
            ->with('get', true, false)
            ->andReturn([
                '401' => new OpenApiResponse(
                    statusCode: 401,
                    description: 'Unauthorized',
                    content: [
                        'application/json' => [
                            'schema' => ['type' => 'object'],
                        ],
                    ],
                ),
            ]);

        $this->exampleGenerator->shouldReceive('generateErrorExample')
            ->with(401)
            ->andReturn(['message' => 'Unauthenticated']);

        $result = $this->generateAsArray($routes);

        $this->assertArrayHasKey('401', $result['paths']['/api/users']['get']['responses']);
    }

    #[Test]
    public function it_collects_tags_from_multiple_operations_and_deduplicates(): void
    {
        // Reset tagGroupGenerator mock for this test
        $this->tagGroupGenerator = Mockery::mock(TagGroupGenerator::class);

        // Expect generateTagDefinitions to receive sorted unique tags
        $this->tagGroupGenerator->shouldReceive('generateTagDefinitions')
            ->once()
            ->with(['Posts', 'Users'])
            ->andReturn([
                new TagDefinition(name: 'Posts'),
                new TagDefinition(name: 'Users'),
            ]);
        $this->tagGroupGenerator->shouldReceive('generateTagGroups')->andReturn([]);

        // Recreate generator with updated mock
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
            $this->openApi31Converter,
            new CallbackGenerator,
            $this->schemaRegistry
        );

        $routes = [
            [
                'uri' => 'api/users',
                'httpMethods' => ['GET'],
                'controller' => 'App\Http\Controllers\UserController',
                'method' => 'index',
                'middleware' => [],
            ],
            [
                'uri' => 'api/posts',
                'httpMethods' => ['GET'],
                'controller' => 'App\Http\Controllers\PostController',
                'method' => 'index',
                'middleware' => [],
            ],
            [
                'uri' => 'api/users/{user}',
                'httpMethods' => ['GET'],
                'controller' => 'App\Http\Controllers\UserController',
                'method' => 'show',
                'middleware' => [],
            ],
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes');
        $this->authenticationAnalyzer->shouldReceive('analyze')->andReturn(AuthenticationResult::empty());
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->andReturn(null);
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->andReturn([]);

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')
            ->andReturnUsing(fn ($uri) => '/'.$uri);
        $this->metadataGenerator->shouldReceive('generateSummary')->andReturn('Summary');
        $this->metadataGenerator->shouldReceive('generateOperationId')->andReturn('operationId');

        // Return different tags including duplicates
        $this->tagGenerator->shouldReceive('generate')
            ->andReturnUsing(function ($route) {
                if (str_contains($route['uri'], 'users')) {
                    return ['Users'];
                }

                return ['Posts'];
            });

        $this->parameterGenerator->shouldReceive('generate')->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')->andReturn(ControllerInfo::empty());

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')->andReturn([]);
        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')->andReturn([]);

        $result = $this->generateAsArray($routes);

        // Verify tags section contains sorted unique tags
        $this->assertArrayHasKey('tags', $result);
        $this->assertCount(2, $result['tags']);
        $this->assertEquals('Posts', $result['tags'][0]['name']);
        $this->assertEquals('Users', $result['tags'][1]['name']);
    }

    #[Test]
    public function it_handles_operations_without_tags(): void
    {
        // Reset tagGroupGenerator mock for this test
        $this->tagGroupGenerator = Mockery::mock(TagGroupGenerator::class);

        // Only one tag should be collected
        $this->tagGroupGenerator->shouldReceive('generateTagDefinitions')
            ->once()
            ->with(['Users'])
            ->andReturn([new TagDefinition(name: 'Users')]);
        $this->tagGroupGenerator->shouldReceive('generateTagGroups')->andReturn([]);

        // Recreate generator with updated mock
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
            $this->openApi31Converter,
            new CallbackGenerator,
            $this->schemaRegistry
        );

        $routes = [
            [
                'uri' => 'api/users',
                'httpMethods' => ['GET'],
                'controller' => 'App\Http\Controllers\UserController',
                'method' => 'index',
                'middleware' => [],
            ],
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes');
        $this->authenticationAnalyzer->shouldReceive('analyze')->andReturn(AuthenticationResult::empty());
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->andReturn(null);
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->andReturn([]);

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')
            ->andReturnUsing(fn ($uri) => '/'.$uri);
        $this->metadataGenerator->shouldReceive('generateSummary')->andReturn('Summary');
        $this->metadataGenerator->shouldReceive('generateOperationId')->andReturn('operationId');

        $this->tagGenerator->shouldReceive('generate')->andReturn(['Users']);

        $this->parameterGenerator->shouldReceive('generate')->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')->andReturn(ControllerInfo::empty());

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')->andReturn([]);
        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')->andReturn([]);

        $result = $this->generateAsArray($routes);

        // Verify tags section
        $this->assertArrayHasKey('tags', $result);
        $this->assertCount(1, $result['tags']);
        $this->assertEquals('Users', $result['tags'][0]['name']);
    }

    #[Test]
    public function it_uses_injected_schema_registry(): void
    {
        // Create a mock SchemaRegistry
        $mockRegistry = Mockery::mock(SchemaRegistry::class);

        // The injected registry should be used
        $mockRegistry->shouldReceive('clear')->once();
        $mockRegistry->shouldReceive('all')->andReturn([]);
        $mockRegistry->shouldReceive('validateReferences')->once()->andReturn([]);

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
            $this->openApi31Converter,
            new CallbackGenerator,
            $mockRegistry
        );

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes');
        $this->authenticationAnalyzer->shouldReceive('analyze')->andReturn(AuthenticationResult::empty());
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->andReturn(null);
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->andReturn([]);

        $result = $generator->generate([])->toArray();

        // Mockery will fail if clear() wasn't called on the injected registry
        // Also verify the result structure to ensure generation completed
        $this->assertArrayHasKey('openapi', $result);
    }

    #[Test]
    public function it_clears_schema_registry_before_each_generation(): void
    {
        // Pre-populate the registry
        $this->schemaRegistry->register('OldSchema', ['type' => 'object']);

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes');
        $this->authenticationAnalyzer->shouldReceive('analyze')->andReturn(AuthenticationResult::empty());
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->andReturn(null);
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->andReturn([]);

        // Generate should clear the registry
        $result = $this->generateAsArray([]);

        // OldSchema should not be in the result because clear() was called
        $this->assertArrayNotHasKey('OldSchema', $result['components']['schemas']);
    }

    #[Test]
    public function it_merges_new_schemas_with_existing_components_schemas(): void
    {
        $routes = [
            [
                'uri' => 'api/users',
                'httpMethods' => ['GET'],
                'controller' => 'App\Http\Controllers\UserController',
                'method' => 'index',
                'middleware' => [],
            ],
        ];

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes');
        $this->authenticationAnalyzer->shouldReceive('analyze')->andReturn(AuthenticationResult::empty());
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->andReturn(null);
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->andReturn([]);

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')
            ->andReturnUsing(fn ($uri) => '/'.$uri);
        $this->metadataGenerator->shouldReceive('generateSummary')->andReturn('Summary');
        $this->metadataGenerator->shouldReceive('generateOperationId')->andReturn('operationId');

        $this->tagGenerator->shouldReceive('generate')->andReturn(['Users']);

        $this->parameterGenerator->shouldReceive('generate')->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')->andReturn(ControllerInfo::fromArray([
            'resource' => 'App\Http\Resources\UserResource',
            'returnsCollection' => false,
        ]));

        $this->resourceAnalyzer->shouldReceive('analyze')
            ->with('App\Http\Resources\UserResource')
            ->andReturn(ResourceInfo::fromArray([
                'properties' => [
                    'id' => ['type' => 'integer'],
                ],
            ]));

        $this->schemaGenerator->shouldReceive('generateFromResource')
            ->andReturn([
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                ],
            ]);

        $this->exampleGenerator->shouldReceive('generateFromResource')
            ->andReturn(['id' => 1]);

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')->andReturn([]);
        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')->andReturn([]);

        $result = $this->generateAsArray($routes);

        // Verify the schema is registered and merged properly
        $this->assertArrayHasKey('UserResource', $result['components']['schemas']);
        $this->assertEquals('object', $result['components']['schemas']['UserResource']['type']);
    }

    #[Test]
    public function it_applies_global_security_when_required_is_true(): void
    {
        $bearerScheme = new AuthenticationScheme(
            type: AuthenticationType::HTTP,
            name: 'bearer',
            scheme: 'bearer',
        );
        $authResult = new AuthenticationResult(
            schemes: ['bearer' => $bearerScheme],
            routes: [],
        );

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes');
        $this->authenticationAnalyzer->shouldReceive('analyze')->andReturn($authResult);
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')
            ->andReturn(['scheme' => 'bearer', 'required' => true]);

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->with(['bearer' => $bearerScheme->toArray()])
            ->andReturn([]);
        $this->securitySchemeGenerator->shouldReceive('generateEndpointSecurity')
            ->once()
            ->with(['scheme' => 'bearer', 'required' => true])
            ->andReturn([['bearer' => []]]);

        $result = $this->generateAsArray([]);

        $this->assertArrayHasKey('security', $result);
        $this->assertEquals([['bearer' => []]], $result['security']);
    }

    #[Test]
    public function it_does_not_apply_global_security_when_required_is_false(): void
    {
        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes');
        $this->authenticationAnalyzer->shouldReceive('analyze')->andReturn(AuthenticationResult::empty());
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')
            ->andReturn(['scheme' => 'bearer', 'required' => false]);

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->andReturn([]);
        // generateEndpointSecurity should NOT be called
        $this->securitySchemeGenerator->shouldNotReceive('generateEndpointSecurity');

        $result = $this->generateAsArray([]);

        $this->assertArrayNotHasKey('security', $result);
    }

    #[Test]
    public function it_includes_title_in_info_object(): void
    {
        config(['spectrum.title' => 'My Custom API']);

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')->once();
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->once()->andReturn(null);
        $this->authenticationAnalyzer->shouldReceive('analyze')->once()->andReturn(AuthenticationResult::empty());
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->once()->with([])->andReturn([]);

        $result = $this->generateAsArray([]);

        $this->assertArrayHasKey('title', $result['info']);
        $this->assertEquals('My Custom API', $result['info']['title']);
    }

    #[Test]
    public function it_uses_app_name_in_default_title_when_spectrum_title_not_configured(): void
    {
        // Set app.name to a known value
        config(['app.name' => 'TestApp']);

        // Set spectrum.title to null to trigger fallback
        // Note: The fallback in OpenApiGenerator.php uses config('app.name').' API'
        // When spectrum.title is null, config() returns null, not the fallback
        // So we need to set the entire spectrum config without title
        $spectrumConfig = config('spectrum');
        unset($spectrumConfig['title']);
        config(['spectrum' => $spectrumConfig]);

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')->once();
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->once()->andReturn(null);
        $this->authenticationAnalyzer->shouldReceive('analyze')->once()->andReturn(AuthenticationResult::empty());
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->once()->with([])->andReturn([]);

        $result = $this->generateAsArray([]);

        $this->assertArrayHasKey('title', $result['info']);
        $this->assertEquals('TestApp API', $result['info']['title']);
    }

    #[Test]
    public function it_generates_request_body_for_delete_route_with_validation(): void
    {
        $routes = [
            [
                'uri' => 'api/users/batch',
                'httpMethods' => ['DELETE'],
                'controller' => 'App\Http\Controllers\UserController',
                'action' => 'batchDelete',
                'method' => 'batchDelete',
                'middleware' => [],
                'name' => 'users.batch-delete',
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
            ->andReturn(AuthenticationResult::empty());

        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')
            ->once()
            ->andReturn([]);

        $this->controllerAnalyzer->shouldReceive('analyzeToResult')
            ->once()
            ->andReturn(ControllerInfo::fromArray([
                'method' => 'batchDelete',
                'responseType' => 'json',
                'hasValidation' => true,
                'inlineValidation' => [
                    'rules' => [
                        'ids' => ['required', 'array', 'min:1', 'max:100'],
                        'ids.*' => ['integer', 'distinct'],
                    ],
                ],
            ]));

        $this->metadataGenerator->shouldReceive('convertToOpenApiPath')
            ->once()
            ->andReturn('/api/users/batch');

        $this->metadataGenerator->shouldReceive('generateSummary')
            ->once()
            ->andReturn('Batch delete users');

        $this->metadataGenerator->shouldReceive('generateOperationId')
            ->once()
            ->andReturn('usersBatchDelete');

        $this->tagGenerator->shouldReceive('generate')
            ->once()
            ->andReturn(['User']);

        $this->parameterGenerator->shouldReceive('generate')
            ->once()
            ->andReturn([]);

        $this->requestBodyGenerator->shouldReceive('generate')
            ->once()
            ->andReturn(new OpenApiRequestBody(
                content: [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'ids' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'integer'],
                                    'minItems' => 1,
                                    'maxItems' => 100,
                                ],
                            ],
                            'required' => ['ids'],
                        ],
                    ],
                ],
                required: true,
            ));

        $this->errorResponseGenerator->shouldReceive('generateErrorResponses')
            ->once()
            ->andReturn([
                '422' => new OpenApiResponse(
                    statusCode: 422,
                    description: 'Validation Error',
                    content: [
                        'application/json' => [
                            'schema' => ['type' => 'object'],
                        ],
                    ],
                ),
            ]);

        $this->errorResponseGenerator->shouldReceive('getDefaultErrorResponses')
            ->once()
            ->andReturn([]);

        $this->exampleGenerator->shouldReceive('generateErrorExample')
            ->withAnyArgs()
            ->andReturn(['message' => 'Validation failed']);

        $result = $this->generateAsArray($routes);

        $this->assertArrayHasKey('/api/users/batch', $result['paths']);
        $this->assertArrayHasKey('delete', $result['paths']['/api/users/batch']);
        $operation = $result['paths']['/api/users/batch']['delete'];

        $this->assertArrayHasKey('requestBody', $operation);
        $this->assertArrayHasKey('content', $operation['requestBody']);
        $this->assertArrayHasKey('application/json', $operation['requestBody']['content']);
    }

    #[Test]
    public function it_logs_warning_for_broken_schema_references(): void
    {
        // Create a mock SchemaRegistry that returns broken references
        $schemaRegistry = Mockery::mock(SchemaRegistry::class);
        $schemaRegistry->shouldReceive('clear')->once();
        $schemaRegistry->shouldReceive('all')->once()->andReturn([]);
        $schemaRegistry->shouldReceive('validateReferences')->once()->andReturn(['NonExistentResource']);

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
            $this->openApi31Converter,
            new CallbackGenerator,
            $schemaRegistry
        );

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')->once();
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->once()->andReturn(null);
        $this->authenticationAnalyzer->shouldReceive('analyze')->once()->andReturn(AuthenticationResult::empty());
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->once()->with([])->andReturn([]);

        // Assert that a warning is logged
        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once()
            ->with(Mockery::on(function ($message) {
                return str_contains($message, 'NonExistentResource');
            }));

        $result = $generator->generate([]);

        $this->assertInstanceOf(\LaravelSpectrum\DTO\OpenApiSpec::class, $result);
        $this->assertEquals('3.0.0', $result->openapi);
    }

    #[Test]
    public function it_does_not_log_warning_when_all_references_are_valid(): void
    {
        // Create a mock SchemaRegistry with no broken references
        $schemaRegistry = Mockery::mock(SchemaRegistry::class);
        $schemaRegistry->shouldReceive('clear')->once();
        $schemaRegistry->shouldReceive('all')->once()->andReturn([
            'UserResource' => ['type' => 'object'],
            'PostResource' => ['type' => 'object'],
        ]);
        $schemaRegistry->shouldReceive('validateReferences')->once()->andReturn([]);

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
            $this->openApi31Converter,
            new CallbackGenerator,
            $schemaRegistry
        );

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')->once();
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->once()->andReturn(null);
        $this->authenticationAnalyzer->shouldReceive('analyze')->once()->andReturn(AuthenticationResult::empty());
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->once()->with([])->andReturn([]);

        // Use spy to verify warning is NOT called
        \Illuminate\Support\Facades\Log::spy();

        $result = $generator->generate([])->toArray();

        // Verify warning was not logged
        \Illuminate\Support\Facades\Log::shouldNotHaveReceived('warning');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('openapi', $result);
        // Verify schemas are populated correctly
        $this->assertArrayHasKey('UserResource', $result['components']['schemas']);
        $this->assertArrayHasKey('PostResource', $result['components']['schemas']);
    }

    #[Test]
    public function it_logs_warning_for_multiple_broken_references(): void
    {
        // Create a mock SchemaRegistry that returns multiple broken references
        $schemaRegistry = Mockery::mock(SchemaRegistry::class);
        $schemaRegistry->shouldReceive('clear')->once();
        $schemaRegistry->shouldReceive('all')->once()->andReturn([
            'UserResource' => ['type' => 'object'],
        ]);
        $schemaRegistry->shouldReceive('validateReferences')->once()->andReturn(['BrokenRef1', 'BrokenRef2']);

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
            $this->openApi31Converter,
            new CallbackGenerator,
            $schemaRegistry
        );

        $this->authenticationAnalyzer->shouldReceive('loadCustomSchemes')->once();
        $this->authenticationAnalyzer->shouldReceive('getGlobalAuthentication')->once()->andReturn(null);
        $this->authenticationAnalyzer->shouldReceive('analyze')->once()->andReturn(AuthenticationResult::empty());
        $this->securitySchemeGenerator->shouldReceive('generateSecuritySchemes')->once()->with([])->andReturn([]);

        // Assert that a warning is logged with all broken references
        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once()
            ->with(Mockery::on(function ($message) {
                return str_contains($message, 'BrokenRef1') && str_contains($message, 'BrokenRef2');
            }));

        $result = $generator->generate([]);

        $this->assertInstanceOf(\LaravelSpectrum\DTO\OpenApiSpec::class, $result);
        $this->assertEquals('3.0.0', $result->openapi);
        // Verify UserResource is still in schemas
        $this->assertArrayHasKey('UserResource', $result->getSchemas());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
