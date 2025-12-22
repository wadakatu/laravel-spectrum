<?php

namespace LaravelSpectrum\Tests\Unit\Generators;

use LaravelSpectrum\Generators\OperationMetadataGenerator;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OperationMetadataGeneratorTest extends TestCase
{
    protected OperationMetadataGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new OperationMetadataGenerator;
    }

    // Summary Generation Tests

    #[Test]
    public function it_generates_list_summary_for_get_without_parameter(): void
    {
        $route = ['uri' => 'api/users'];

        $summary = $this->generator->generateSummary($route, 'get');

        $this->assertEquals('List all User', $summary);
    }

    #[Test]
    public function it_generates_get_by_id_summary_for_get_with_parameter(): void
    {
        $route = ['uri' => 'api/users/{user}'];

        $summary = $this->generator->generateSummary($route, 'get');

        $this->assertEquals('Get User by ID', $summary);
    }

    #[Test]
    public function it_generates_create_summary_for_post(): void
    {
        $route = ['uri' => 'api/users'];

        $summary = $this->generator->generateSummary($route, 'post');

        $this->assertEquals('Create a new User', $summary);
    }

    #[Test]
    public function it_generates_update_summary_for_put(): void
    {
        $route = ['uri' => 'api/users/{user}'];

        $summary = $this->generator->generateSummary($route, 'put');

        $this->assertEquals('Update User', $summary);
    }

    #[Test]
    public function it_generates_update_summary_for_patch(): void
    {
        $route = ['uri' => 'api/users/{user}'];

        $summary = $this->generator->generateSummary($route, 'patch');

        $this->assertEquals('Update User', $summary);
    }

    #[Test]
    public function it_generates_delete_summary(): void
    {
        $route = ['uri' => 'api/users/{user}'];

        $summary = $this->generator->generateSummary($route, 'delete');

        $this->assertEquals('Delete User', $summary);
    }

    #[Test]
    public function it_generates_default_summary_for_unknown_method(): void
    {
        $route = ['uri' => 'api/users'];

        $summary = $this->generator->generateSummary($route, 'options');

        $this->assertEquals('Options User', $summary);
    }

    // Operation ID Generation Tests

    #[Test]
    public function it_generates_operation_id_from_route_name(): void
    {
        $route = [
            'uri' => 'api/users',
            'name' => 'users.index',
        ];

        $operationId = $this->generator->generateOperationId($route, 'get');

        $this->assertEquals('usersIndex', $operationId);
    }

    #[Test]
    public function it_generates_operation_id_from_uri_when_no_name(): void
    {
        $route = ['uri' => 'api/users'];

        $operationId = $this->generator->generateOperationId($route, 'get');

        $this->assertEquals('getApiUsers', $operationId);
    }

    #[Test]
    public function it_generates_operation_id_with_parameters(): void
    {
        $route = ['uri' => 'api/users/{user}'];

        $operationId = $this->generator->generateOperationId($route, 'get');

        $this->assertEquals('getApiUsersUser', $operationId);
    }

    #[Test]
    public function it_handles_optional_parameters_in_operation_id(): void
    {
        $route = ['uri' => 'api/posts/{post?}'];

        $operationId = $this->generator->generateOperationId($route, 'get');

        $this->assertEquals('getApiPostsPost', $operationId);
    }

    // Path Conversion Tests

    #[Test]
    public function it_converts_simple_uri_to_openapi_path(): void
    {
        $path = $this->generator->convertToOpenApiPath('api/users');

        $this->assertEquals('/api/users', $path);
    }

    #[Test]
    public function it_converts_uri_with_parameter_to_openapi_path(): void
    {
        $path = $this->generator->convertToOpenApiPath('api/users/{user}');

        $this->assertEquals('/api/users/{user}', $path);
    }

    #[Test]
    public function it_converts_optional_parameter_to_required_format(): void
    {
        $path = $this->generator->convertToOpenApiPath('api/posts/{post?}');

        $this->assertEquals('/api/posts/{post}', $path);
    }

    #[Test]
    public function it_handles_multiple_optional_parameters(): void
    {
        $path = $this->generator->convertToOpenApiPath('api/{category?}/posts/{post?}');

        $this->assertEquals('/api/{category}/posts/{post}', $path);
    }

    // Resource Name Extraction Tests

    #[Test]
    public function it_extracts_resource_name_from_simple_uri(): void
    {
        $name = $this->generator->extractResourceName('api/users');

        $this->assertEquals('User', $name);
    }

    #[Test]
    public function it_extracts_resource_name_ignoring_parameters(): void
    {
        $name = $this->generator->extractResourceName('api/users/{user}');

        $this->assertEquals('User', $name);
    }

    #[Test]
    public function it_singularizes_plural_resource_names(): void
    {
        $name = $this->generator->extractResourceName('api/categories');

        $this->assertEquals('Category', $name);
    }

    #[Test]
    public function it_handles_nested_resources(): void
    {
        $name = $this->generator->extractResourceName('api/users/{user}/posts');

        $this->assertEquals('Post', $name);
    }

    #[Test]
    public function it_studly_cases_resource_names(): void
    {
        $name = $this->generator->extractResourceName('api/user-profiles');

        $this->assertEquals('UserProfile', $name);
    }
}
