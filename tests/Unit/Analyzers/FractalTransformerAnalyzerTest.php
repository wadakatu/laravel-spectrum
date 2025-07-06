<?php

namespace LaravelPrism\Tests\Unit\Analyzers;

use LaravelPrism\Analyzers\FractalTransformerAnalyzer;
use LaravelPrism\Tests\Fixtures\Transformers\ComplexTransformer;
use LaravelPrism\Tests\Fixtures\Transformers\PostTransformer;
use LaravelPrism\Tests\Fixtures\Transformers\UserTransformer;
use LaravelPrism\Tests\TestCase;

class FractalTransformerAnalyzerTest extends TestCase
{
    private FractalTransformerAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new FractalTransformerAnalyzer;
    }

    /** @test */
    public function it_analyzes_basic_fractal_transformer()
    {
        $result = $this->analyzer->analyze(UserTransformer::class);

        $this->assertEquals('fractal', $result['type']);

        // Check properties from transform method
        $this->assertArrayHasKey('properties', $result);
        $this->assertArrayHasKey('id', $result['properties']);
        $this->assertArrayHasKey('name', $result['properties']);
        $this->assertArrayHasKey('email', $result['properties']);
        $this->assertArrayHasKey('created_at', $result['properties']);
        $this->assertArrayHasKey('updated_at', $result['properties']);

        // Check property types
        $this->assertEquals('integer', $result['properties']['id']['type']);
        $this->assertEquals('string', $result['properties']['name']['type']);
        $this->assertEquals('string', $result['properties']['email']['type']);
        $this->assertEquals('string', $result['properties']['created_at']['type']);
        $this->assertEquals('string', $result['properties']['updated_at']['type']);
    }

    /** @test */
    public function it_extracts_available_includes()
    {
        $result = $this->analyzer->analyze(UserTransformer::class);

        $this->assertArrayHasKey('availableIncludes', $result);
        $this->assertArrayHasKey('posts', $result['availableIncludes']);
        $this->assertArrayHasKey('profile', $result['availableIncludes']);

        // Check include types
        $this->assertEquals('array', $result['availableIncludes']['posts']['type']);
        $this->assertTrue($result['availableIncludes']['posts']['collection']);
        $this->assertEquals('object', $result['availableIncludes']['profile']['type']);
        $this->assertFalse($result['availableIncludes']['profile']['collection']);
    }

    /** @test */
    public function it_extracts_default_includes()
    {
        $result = $this->analyzer->analyze(UserTransformer::class);

        $this->assertArrayHasKey('defaultIncludes', $result);
        $this->assertContains('profile', $result['defaultIncludes']);
        $this->assertNotContains('posts', $result['defaultIncludes']);
    }

    /** @test */
    public function it_analyzes_transformer_with_nullable_fields()
    {
        $result = $this->analyzer->analyze(PostTransformer::class);

        $this->assertEquals('fractal', $result['type']);

        // Check nullable field
        $this->assertArrayHasKey('published_at', $result['properties']);
        $this->assertTrue($result['properties']['published_at']['nullable']);
    }

    /** @test */
    public function it_analyzes_complex_transformer_with_nested_data()
    {
        $result = $this->analyzer->analyze(ComplexTransformer::class);

        $this->assertEquals('fractal', $result['type']);

        // Check nested data structure
        $this->assertArrayHasKey('data', $result['properties']);
        $this->assertEquals('object', $result['properties']['data']['type']);

        // Check array types
        $this->assertArrayHasKey('settings', $result['properties']);
        $this->assertEquals('array', $result['properties']['settings']['type']);

        // Check nested objects
        $this->assertArrayHasKey('flags', $result['properties']);
        $this->assertEquals('object', $result['properties']['flags']['type']);

        $this->assertArrayHasKey('metrics', $result['properties']);
        $this->assertEquals('object', $result['properties']['metrics']['type']);
    }

    /** @test */
    public function it_returns_empty_array_for_non_transformer_class()
    {
        $result = $this->analyzer->analyze(\stdClass::class);
        $this->assertEmpty($result);
    }

    /** @test */
    public function it_returns_empty_array_for_non_existent_class()
    {
        $result = $this->analyzer->analyze('NonExistentClass');
        $this->assertEmpty($result);
    }

    /** @test */
    public function it_analyzes_include_methods_with_null_returns()
    {
        $result = $this->analyzer->analyze(ComplexTransformer::class);

        $this->assertArrayHasKey('nested_resource', $result['availableIncludes']);
        // ComplexTransformerのincludeNestedResourceは条件付きでnullを返す可能性がある
        // しかし、デフォルトではobjectまたはnullとして扱われる
        $this->assertContains($result['availableIncludes']['nested_resource']['type'], ['object', 'null']);
    }

    /** @test */
    public function it_identifies_transformer_classes_from_include_methods()
    {
        $result = $this->analyzer->analyze(UserTransformer::class);

        $this->assertArrayHasKey('posts', $result['availableIncludes']);
        $this->assertEquals('PostTransformer', $result['availableIncludes']['posts']['transformer']);

        $this->assertArrayHasKey('profile', $result['availableIncludes']);
        $this->assertEquals('ProfileTransformer', $result['availableIncludes']['profile']['transformer']);
    }

    /** @test */
    public function it_generates_appropriate_examples_for_properties()
    {
        $result = $this->analyzer->analyze(UserTransformer::class);

        $this->assertArrayHasKey('example', $result['properties']['id']);
        $this->assertIsInt($result['properties']['id']['example']);

        $this->assertArrayHasKey('example', $result['properties']['name']);
        $this->assertIsString($result['properties']['name']['example']);

        $this->assertArrayHasKey('example', $result['properties']['email']);
        $this->assertStringContainsString('@', $result['properties']['email']['example']);
    }
}
