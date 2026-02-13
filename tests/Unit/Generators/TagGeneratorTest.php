<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\Generators\TagGenerator;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TagGeneratorTest extends TestCase
{
    protected TagGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new TagGenerator;
    }

    #[Test]
    public function it_generates_tag_from_simple_uri(): void
    {
        $route = [
            'uri' => 'api/users',
            'controller' => 'UserController',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals(['User'], $tags);
    }

    #[Test]
    public function it_removes_parameters_from_tags(): void
    {
        $route = [
            'uri' => 'api/v1/posts/{post}',
            'controller' => 'PostController',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals(['Post'], $tags);
    }

    #[Test]
    public function it_prefers_controller_based_tags_for_nested_resources_by_default(): void
    {
        $route = [
            'uri' => 'api/v1/posts/{post}/comments',
            'controller' => 'CommentController',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals(['Comment'], $tags);
    }

    #[Test]
    public function it_uses_controller_name_for_generic_paths(): void
    {
        $route = [
            'uri' => 'api/v1/{resource}',
            'controller' => 'UserController',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals(['User'], $tags);
    }

    #[Test]
    public function it_respects_custom_tag_mappings_from_config(): void
    {
        $this->app['config']->set('spectrum.tags', [
            'api/v1/auth/*' => 'Authentication',
            'api/v1/admin/*' => 'Administration',
        ]);

        $route = [
            'uri' => 'api/v1/auth/login',
            'controller' => 'AuthController',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals(['Authentication'], $tags);
    }

    #[Test]
    public function it_handles_deeply_nested_resources(): void
    {
        $route = [
            'uri' => 'api/v1/posts/{post}/comments/{comment}/likes',
            'controller' => 'LikeController',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals(['Like'], $tags);
    }

    #[Test]
    public function it_can_generate_multiple_tags_when_tag_depth_is_increased(): void
    {
        $this->app['config']->set('spectrum.tag_depth', 3);

        $route = [
            'uri' => 'api/v1/posts/{post}/comments/{comment}/likes',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals(['Post', 'Comment', 'Like'], $tags);
    }

    #[Test]
    public function it_returns_empty_tags_when_tag_depth_is_zero_without_controller(): void
    {
        $this->app['config']->set('spectrum.tag_depth', 0);

        $route = [
            'uri' => 'api/v1/posts/{post}/comments',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals([], $tags);
    }

    #[Test]
    public function it_handles_simple_resource_paths(): void
    {
        $route = [
            'uri' => 'api/users',
            'controller' => 'UserController',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals(['User'], $tags);
    }

    #[Test]
    public function it_ignores_common_prefixes(): void
    {
        $route = [
            'uri' => 'api/v1/users',
            'controller' => 'UserController',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals(['User'], $tags);
    }

    #[Test]
    public function it_handles_optional_parameters(): void
    {
        $route = [
            'uri' => 'api/posts/{post?}',
            'controller' => 'PostController',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals(['Post'], $tags);
    }

    #[Test]
    public function it_handles_custom_tag_mapping_with_exact_match(): void
    {
        $this->app['config']->set('spectrum.tags', [
            'api/v1/auth/login' => 'Authentication',
            'api/v1/auth/logout' => 'Authentication',
        ]);

        $route = [
            'uri' => 'api/v1/auth/login',
            'controller' => 'AuthController',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals(['Authentication'], $tags);
    }

    #[Test]
    public function it_handles_array_of_custom_tags(): void
    {
        $this->app['config']->set('spectrum.tags', [
            'api/v1/auth/*' => ['Authentication', 'Security'],
        ]);

        $route = [
            'uri' => 'api/v1/auth/login',
            'controller' => 'AuthController',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals(['Authentication', 'Security'], $tags);
    }

    #[Test]
    public function it_removes_duplicate_tags(): void
    {
        $route = [
            'uri' => 'api/users/users', // Unusual but should handle duplicates
            'controller' => 'UserController',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals(['User'], $tags);
    }

    #[Test]
    public function it_handles_route_without_controller(): void
    {
        $route = [
            'uri' => 'api/v1/{resource}',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals([], $tags);
    }

    #[Test]
    public function it_singularizes_plural_resources(): void
    {
        $route = [
            'uri' => 'api/categories',
            'controller' => 'CategoryController',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals(['Category'], $tags);
    }

    #[Test]
    public function it_handles_v2_and_v3_prefixes(): void
    {
        $route = [
            'uri' => 'api/v2/products',
            'controller' => 'ProductController',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals(['Product'], $tags);

        $route['uri'] = 'api/v3/products';
        $tags = $this->generator->generate($route);

        $this->assertEquals(['Product'], $tags);
    }

    #[Test]
    public function it_returns_empty_tags_for_controller_named_only_controller(): void
    {
        $route = [
            'uri' => 'api/v1/{resource}',
            'controller' => 'Controller',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals([], $tags);
    }

    #[Test]
    public function it_handles_empty_uri(): void
    {
        $route = [
            'uri' => '',
            'controller' => 'UserController',
        ];

        $tags = $this->generator->generate($route);

        $this->assertEquals(['User'], $tags);
    }
}
