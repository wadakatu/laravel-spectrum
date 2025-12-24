<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\FractalTransformerAnalyzer;
use LaravelSpectrum\Analyzers\Support\AstHelper;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Tests\Fixtures\Transformers\ComplexTransformer;
use LaravelSpectrum\Tests\Fixtures\Transformers\PostTransformer;
use LaravelSpectrum\Tests\Fixtures\Transformers\UserTransformer;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FractalTransformerAnalyzerTest extends TestCase
{
    private FractalTransformerAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = $this->app->make(FractalTransformerAnalyzer::class);
    }

    #[Test]
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

    #[Test]
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

    #[Test]
    public function it_extracts_default_includes()
    {
        $result = $this->analyzer->analyze(UserTransformer::class);

        $this->assertArrayHasKey('defaultIncludes', $result);
        $this->assertContains('profile', $result['defaultIncludes']);
        $this->assertNotContains('posts', $result['defaultIncludes']);
    }

    #[Test]
    public function it_analyzes_transformer_with_nullable_fields()
    {
        $result = $this->analyzer->analyze(PostTransformer::class);

        $this->assertEquals('fractal', $result['type']);

        // Check nullable field
        $this->assertArrayHasKey('published_at', $result['properties']);
        $this->assertTrue($result['properties']['published_at']['nullable']);
    }

    #[Test]
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

    #[Test]
    public function it_returns_empty_array_for_non_transformer_class()
    {
        $result = $this->analyzer->analyze(\stdClass::class);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_empty_array_for_non_existent_class()
    {
        $result = $this->analyzer->analyze('NonExistentClass');
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_analyzes_include_methods_with_null_returns()
    {
        $result = $this->analyzer->analyze(ComplexTransformer::class);

        $this->assertArrayHasKey('nested_resource', $result['availableIncludes']);
        // ComplexTransformerのincludeNestedResourceは条件付きでnullを返す可能性がある
        // しかし、デフォルトではobjectまたはnullとして扱われる
        $this->assertContains($result['availableIncludes']['nested_resource']['type'], ['object', 'null']);
    }

    #[Test]
    public function it_identifies_transformer_classes_from_include_methods()
    {
        $result = $this->analyzer->analyze(UserTransformer::class);

        $this->assertArrayHasKey('posts', $result['availableIncludes']);
        $this->assertEquals('PostTransformer', $result['availableIncludes']['posts']['transformer']);

        $this->assertArrayHasKey('profile', $result['availableIncludes']);
        $this->assertEquals('ProfileTransformer', $result['availableIncludes']['profile']['transformer']);
    }

    #[Test]
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

    // ========== ErrorCollector tests ==========

    #[Test]
    public function it_logs_warning_to_error_collector_when_class_does_not_exist(): void
    {
        $errorCollector = new ErrorCollector;
        $analyzer = new FractalTransformerAnalyzer(
            $this->app->make(AstHelper::class),
            $errorCollector
        );

        $result = $analyzer->analyze('NonExistentTransformerClass');

        $this->assertEmpty($result);

        $warnings = $errorCollector->getWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertEquals('class_not_found', $warnings[0]['metadata']['error_type']);
        $this->assertStringContainsString('NonExistentTransformerClass', $warnings[0]['message']);
    }

    #[Test]
    public function it_logs_warning_to_error_collector_when_class_is_not_transformer(): void
    {
        $errorCollector = new ErrorCollector;
        $analyzer = new FractalTransformerAnalyzer(
            $this->app->make(AstHelper::class),
            $errorCollector
        );

        $result = $analyzer->analyze(\stdClass::class);

        $this->assertEmpty($result);

        $warnings = $errorCollector->getWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertEquals('invalid_parent_class', $warnings[0]['metadata']['error_type']);
        $this->assertStringContainsString('stdClass', $warnings[0]['message']);
    }
}
