<?php

namespace Tests\Unit\Generators;

use LaravelSpectrum\Analyzers\AuthenticationAnalyzer;
use LaravelSpectrum\Analyzers\ControllerAnalyzer;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Analyzers\InlineValidationAnalyzer;
use LaravelSpectrum\Analyzers\ResourceAnalyzer;
use LaravelSpectrum\Generators\ErrorResponseGenerator;
use LaravelSpectrum\Generators\OpenApiGenerator;
use LaravelSpectrum\Generators\SchemaGenerator;
use LaravelSpectrum\Generators\SecuritySchemeGenerator;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use ReflectionClass;

class OpenApiGeneratorTagsTest extends TestCase
{
    protected OpenApiGenerator $generator;

    protected $mockFormRequestAnalyzer;

    protected $mockResourceAnalyzer;

    protected $mockControllerAnalyzer;

    protected $mockInlineValidationAnalyzer;

    protected $mockSchemaGenerator;

    protected $mockErrorResponseGenerator;

    protected $mockAuthenticationAnalyzer;

    protected $mockSecuritySchemeGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockFormRequestAnalyzer = Mockery::mock(FormRequestAnalyzer::class);
        $this->mockResourceAnalyzer = Mockery::mock(ResourceAnalyzer::class);
        $this->mockControllerAnalyzer = Mockery::mock(ControllerAnalyzer::class);
        $this->mockInlineValidationAnalyzer = Mockery::mock(InlineValidationAnalyzer::class);
        $this->mockSchemaGenerator = Mockery::mock(SchemaGenerator::class);
        $this->mockErrorResponseGenerator = Mockery::mock(ErrorResponseGenerator::class);
        $this->mockAuthenticationAnalyzer = Mockery::mock(AuthenticationAnalyzer::class);
        $this->mockSecuritySchemeGenerator = Mockery::mock(SecuritySchemeGenerator::class);

        $this->generator = new OpenApiGenerator(
            $this->mockFormRequestAnalyzer,
            $this->mockResourceAnalyzer,
            $this->mockControllerAnalyzer,
            $this->mockInlineValidationAnalyzer,
            $this->mockSchemaGenerator,
            $this->mockErrorResponseGenerator,
            $this->mockAuthenticationAnalyzer,
            $this->mockSecuritySchemeGenerator
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_removes_parameters_from_tags()
    {
        $route = [
            'uri' => 'api/v1/posts/{post}',
            'controller' => 'PostController',
            'method' => 'show',
        ];

        $tags = $this->callProtectedMethod($this->generator, 'generateTags', [$route]);

        $this->assertEquals(['Post'], $tags);
    }

    /** @test */
    public function it_handles_nested_resources_with_multiple_tags()
    {
        $route = [
            'uri' => 'api/v1/posts/{post}/comments',
            'controller' => 'CommentController',
            'method' => 'index',
        ];

        $tags = $this->callProtectedMethod($this->generator, 'generateTags', [$route]);

        $this->assertEquals(['Post', 'Comment'], $tags);
    }

    /** @test */
    public function it_uses_controller_name_as_fallback_for_generic_paths()
    {
        $route = [
            'uri' => 'api/v1/{resource}',
            'controller' => 'UserController',
            'method' => 'index',
        ];

        $tags = $this->callProtectedMethod($this->generator, 'generateTags', [$route]);

        $this->assertEquals(['User'], $tags);
    }

    /** @test */
    public function it_respects_custom_tag_mappings_from_config()
    {
        // テスト用の設定値をセット
        $this->app['config']->set('spectrum.tags', [
            'api/v1/auth/*' => 'Authentication',
            'api/v1/admin/*' => 'Administration',
        ]);

        $route = [
            'uri' => 'api/v1/auth/login',
            'controller' => 'AuthController',
            'method' => 'login',
        ];

        $tags = $this->callProtectedMethod($this->generator, 'generateTags', [$route]);

        $this->assertEquals(['Authentication'], $tags);
    }

    /** @test */
    public function it_handles_deeply_nested_resources()
    {
        $route = [
            'uri' => 'api/v1/posts/{post}/comments/{comment}/likes',
            'controller' => 'LikeController',
            'method' => 'index',
        ];

        $tags = $this->callProtectedMethod($this->generator, 'generateTags', [$route]);

        $this->assertEquals(['Post', 'Comment', 'Like'], $tags);
    }

    /** @test */
    public function it_handles_simple_resource_paths()
    {
        $route = [
            'uri' => 'api/users',
            'controller' => 'UserController',
            'method' => 'index',
        ];

        $tags = $this->callProtectedMethod($this->generator, 'generateTags', [$route]);

        $this->assertEquals(['User'], $tags);
    }

    /** @test */
    public function it_ignores_common_prefixes()
    {
        $route = [
            'uri' => 'api/v1/users',
            'controller' => 'UserController',
            'method' => 'index',
        ];

        $tags = $this->callProtectedMethod($this->generator, 'generateTags', [$route]);

        $this->assertEquals(['User'], $tags);
    }

    /** @test */
    public function it_handles_optional_parameters()
    {
        $route = [
            'uri' => 'api/posts/{post?}',
            'controller' => 'PostController',
            'method' => 'show',
        ];

        $tags = $this->callProtectedMethod($this->generator, 'generateTags', [$route]);

        $this->assertEquals(['Post'], $tags);
    }

    /** @test */
    public function it_handles_custom_tag_mapping_with_exact_match()
    {
        $this->app['config']->set('spectrum.tags', [
            'api/v1/auth/login' => 'Authentication',
            'api/v1/auth/logout' => 'Authentication',
        ]);

        $route = [
            'uri' => 'api/v1/auth/login',
            'controller' => 'AuthController',
            'method' => 'login',
        ];

        $tags = $this->callProtectedMethod($this->generator, 'generateTags', [$route]);

        $this->assertEquals(['Authentication'], $tags);
    }

    protected function callProtectedMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
