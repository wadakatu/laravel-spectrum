<?php

namespace LaravelSpectrum\Tests\Feature;

use LaravelSpectrum\Analyzers\ControllerAnalyzer;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Support\LumenCompatibilityHelper;
use LaravelSpectrum\Support\TypeInference;
use LaravelSpectrum\Tests\TestCase;

class LumenCompatibilityTest extends TestCase
{
    /** @test */
    public function it_detects_lumen_environment()
    {
        // In test environment, we should not be in Lumen
        $this->assertFalse(LumenCompatibilityHelper::isLumen());
    }

    /** @test */
    public function it_returns_correct_route_patterns_for_laravel()
    {
        config(['spectrum.route_patterns' => ['api/*']]);

        $patterns = LumenCompatibilityHelper::getRoutePatterns();

        $this->assertEquals(['api/*'], $patterns);
    }

    /** @test */
    public function it_returns_routes_collection()
    {
        $routes = LumenCompatibilityHelper::getRoutes();

        $this->assertNotNull($routes);
    }

    /** @test */
    public function it_generates_openapi_with_inline_validation()
    {
        // Create a test controller with inline validation
        $controllerCode = '<?php
        namespace App\Http\Controllers;
        
        use Illuminate\Http\Request;
        
        class UserController extends Controller
        {
            public function store(Request $request)
            {
                $this->validate($request, [
                    "name" => "required|string|max:255",
                    "email" => "required|email|unique:users",
                    "password" => "required|string|min:8|confirmed",
                    "age" => "sometimes|integer|min:0|max:150",
                ]);
                
                $user = User::create($request->all());
                
                return response()->json($user, 201);
            }
            
            public function update(Request $request, $id)
            {
                $this->validate($request, [
                    "name" => ["sometimes", "string", "max:255"],
                    "email" => ["sometimes", "email", "unique:users,email," . $id],
                ], [
                    "name.string" => "Name must be a string",
                    "email.unique" => "This email is already taken",
                ]);
                
                $user = User::findOrFail($id);
                $user->update($request->all());
                
                return response()->json($user);
            }
        }';

        // Create temporary file for testing
        $tempFile = tempnam(sys_get_temp_dir(), 'test_controller');
        file_put_contents($tempFile, $controllerCode);

        // Mock the controller analyzer to return our test data
        $controllerInfo = [
            'formRequest' => null,
            'inlineValidation' => [
                'rules' => [
                    'name' => 'required|string|max:255',
                    'email' => 'required|email|unique:users',
                    'password' => 'required|string|min:8|confirmed',
                    'age' => 'sometimes|integer|min:0|max:150',
                ],
                'messages' => [],
                'attributes' => [],
            ],
            'resource' => null,
            'returnsCollection' => false,
            'fractal' => null,
        ];

        $routes = [
            [
                'uri' => 'api/users',
                'httpMethods' => ['post'],
                'controller' => 'App\Http\Controllers\UserController',
                'method' => 'store',
                'name' => 'users.store',
                'middleware' => ['api'],
                'parameters' => [],
            ],
        ];

        // First mock the controller analyzer
        $this->app->instance(ControllerAnalyzer::class,
            \Mockery::mock(ControllerAnalyzer::class, function ($mock) use ($controllerInfo) {
                $mock->shouldReceive('analyze')
                    ->with('App\Http\Controllers\UserController', 'store')
                    ->andReturn($controllerInfo);
            })
        );

        // Now get the generator which will use our mocked analyzer
        $generator = app(OpenApiGenerator::class);

        $spec = $generator->generate($routes);

        // Assert the spec contains inline validation parameters
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('/api/users', $spec['paths']);
        $this->assertArrayHasKey('post', $spec['paths']['/api/users']);

        $operation = $spec['paths']['/api/users']['post'];

        // Debug output to see what's actually in the operation
        if (! isset($operation['requestBody'])) {
            $this->fail('Request body not found in operation. Operation content: '.json_encode($operation, JSON_PRETTY_PRINT));
        }

        $this->assertArrayHasKey('requestBody', $operation);

        $schema = $operation['requestBody']['content']['application/json']['schema'];
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('email', $schema['properties']);
        $this->assertArrayHasKey('password', $schema['properties']);
        $this->assertArrayHasKey('age', $schema['properties']);

        // Check required fields
        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('name', $schema['required']);
        $this->assertContains('email', $schema['required']);
        $this->assertContains('password', $schema['required']);
        $this->assertNotContains('age', $schema['required']); // 'sometimes' should not be required

        // Check property details
        $this->assertEquals('string', $schema['properties']['name']['type']);
        $this->assertEquals(255, $schema['properties']['name']['maxLength']);

        $this->assertEquals('string', $schema['properties']['email']['type']);
        $this->assertEquals('email', $schema['properties']['email']['format']);

        $this->assertEquals('string', $schema['properties']['password']['type']);
        $this->assertEquals(8, $schema['properties']['password']['minLength']);

        $this->assertEquals('integer', $schema['properties']['age']['type']);
        $this->assertEquals(0, $schema['properties']['age']['minimum']);
        $this->assertEquals(150, $schema['properties']['age']['maximum']);

        // Clean up
        unlink($tempFile);
    }

    /** @test */
    public function it_handles_validator_make_pattern()
    {
        $controllerInfo = [
            'formRequest' => null,
            'inlineValidation' => [
                'rules' => [
                    'title' => 'required|string|max:100',
                    'content' => 'required|string',
                ],
                'messages' => [],
                'attributes' => [],
            ],
            'resource' => null,
            'returnsCollection' => false,
            'fractal' => null,
        ];

        $routes = [
            [
                'uri' => 'api/posts',
                'httpMethods' => ['post'],
                'controller' => 'App\Http\Controllers\PostController',
                'method' => 'store',
                'name' => 'posts.store',
                'middleware' => ['api'],
                'parameters' => [],
            ],
        ];

        $this->app->instance(ControllerAnalyzer::class,
            \Mockery::mock(ControllerAnalyzer::class, function ($mock) use ($controllerInfo) {
                $mock->shouldReceive('analyze')
                    ->with('App\Http\Controllers\PostController', 'store')
                    ->andReturn($controllerInfo);
            })
        );

        $generator = app(OpenApiGenerator::class);

        $spec = $generator->generate($routes);

        // Assert validation rules are properly converted to OpenAPI
        $operation = $spec['paths']['/api/posts']['post'];
        $schema = $operation['requestBody']['content']['application/json']['schema'];

        $this->assertEquals('string', $schema['properties']['title']['type']);
        $this->assertEquals(100, $schema['properties']['title']['maxLength']);
        $this->assertEquals('string', $schema['properties']['content']['type']);

        // Check error responses include 422
        $this->assertArrayHasKey('422', $operation['responses']);
        $this->assertStringContainsString('Validation', $operation['responses']['422']['description']);
    }

    /** @test */
    public function it_prefers_form_request_over_inline_validation()
    {
        $controllerInfo = [
            'formRequest' => 'App\Http\Requests\StoreUserRequest',
            'inlineValidation' => [
                'rules' => [
                    'name' => 'required|string',
                ],
            ],
            'resource' => null,
            'returnsCollection' => false,
            'fractal' => null,
        ];

        // Mock FormRequestAnalyzer to return some data
        $this->app->instance(FormRequestAnalyzer::class,
            \Mockery::mock(FormRequestAnalyzer::class, function ($mock) {
                $mock->shouldReceive('analyze')
                    ->andReturn([
                        [
                            'name' => 'email',
                            'type' => 'string',
                            'format' => 'email',
                            'required' => true,
                            'description' => 'Email address',
                        ],
                    ]);
                $mock->shouldReceive('analyzeWithDetails')
                    ->andReturn([
                        'rules' => ['email' => 'required|email'],
                        'messages' => [],
                        'attributes' => [],
                    ]);
            })
        );

        $routes = [
            [
                'uri' => 'api/users',
                'httpMethods' => ['post'],
                'controller' => 'App\Http\Controllers\UserController',
                'method' => 'store',
                'name' => 'users.store',
                'middleware' => ['api'],
                'parameters' => [],
            ],
        ];

        $this->app->instance(ControllerAnalyzer::class,
            \Mockery::mock(ControllerAnalyzer::class, function ($mock) use ($controllerInfo) {
                $mock->shouldReceive('analyze')
                    ->with('App\Http\Controllers\UserController', 'store')
                    ->andReturn($controllerInfo);
            })
        );

        $generator = app(OpenApiGenerator::class);

        $spec = $generator->generate($routes);

        // Should use FormRequest data, not inline validation
        $schema = $spec['paths']['/api/users']['post']['requestBody']['content']['application/json']['schema'];

        $this->assertArrayHasKey('email', $schema['properties']);
        $this->assertArrayNotHasKey('name', $schema['properties']); // From inline validation, should not be present
    }

    /** @test */
    public function it_handles_custom_validation_messages_and_attributes()
    {
        $inlineValidationAnalyzer = new InlineValidationAnalyzer(new TypeInference);

        $validation = [
            'rules' => [
                'name' => 'required|string|max:255',
                'email' => 'required|email',
            ],
            'messages' => [
                'name.required' => 'お名前は必須です',
                'email.required' => 'メールアドレスは必須です',
            ],
            'attributes' => [
                'name' => 'Full Name',
                'email' => 'Email Address',
            ],
        ];

        $parameters = $inlineValidationAnalyzer->generateParameters($validation);

        $this->assertEquals('Full Name - Required, Maximum: 255', $parameters[0]['description']);
        $this->assertEquals('Email Address - Required, Must be a valid email address', $parameters[1]['description']);
    }
}
