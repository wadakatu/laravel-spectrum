<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\FractalTransformerAnalyzer;
use LaravelSpectrum\Analyzers\Support\AstHelper;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Tests\Fixtures\Transformers\ComplexTransformer;
use LaravelSpectrum\Tests\Fixtures\Transformers\ExampleGenerationTransformer;
use LaravelSpectrum\Tests\Fixtures\Transformers\MinimalTransformer;
use LaravelSpectrum\Tests\Fixtures\Transformers\MissingIncludeMethodTransformer;
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

    // ========== Additional coverage tests ==========

    #[Test]
    public function it_handles_transformer_without_transform_method(): void
    {
        $result = $this->analyzer->analyze(MinimalTransformer::class);

        $this->assertEquals('fractal', $result['type']);
        $this->assertEmpty($result['properties']);
    }

    #[Test]
    public function it_handles_missing_include_method(): void
    {
        $result = $this->analyzer->analyze(MissingIncludeMethodTransformer::class);

        $this->assertEquals('fractal', $result['type']);
        $this->assertArrayHasKey('author', $result['availableIncludes']);
        $this->assertEquals('unknown', $result['availableIncludes']['author']['type']);
    }

    #[Test]
    public function it_generates_correct_examples_for_various_property_types(): void
    {
        $result = $this->analyzer->analyze(ExampleGenerationTransformer::class);

        // Integer with 'id' pattern
        $this->assertEquals(1, $result['properties']['id']['example']);
        $this->assertEquals(1, $result['properties']['user_id']['example']);

        // Integer with 'count' pattern
        $this->assertEquals(100, $result['properties']['count']['example']);
        $this->assertEquals(100, $result['properties']['total_count']['example']);

        // Default integer
        $this->assertEquals(42, $result['properties']['amount']['example']);

        // Boolean
        $this->assertEquals(true, $result['properties']['is_active']['example']);

        // Array
        $this->assertEquals([], $result['properties']['tags']['example']);

        // String patterns
        $this->assertEquals('user@example.com', $result['properties']['email']['example']);
        $this->assertEquals('John Doe', $result['properties']['name']['example']);
        $this->assertEquals('Sample Title', $result['properties']['title']['example']);
        $this->assertEquals('Sample body text', $result['properties']['body']['example']);
        $this->assertEquals('active', $result['properties']['status']['example']);
        $this->assertEquals('default', $result['properties']['type']['example']);

        // URL pattern
        $this->assertEquals('https://example.com', $result['properties']['avatar_url']['example']);
        $this->assertEquals('https://example.com', $result['properties']['image_url']['example']);

        // _at pattern for dates
        $this->assertStringContainsString('2024', $result['properties']['created_at']['example']);

        // Default string
        $this->assertEquals('string', $result['properties']['description']['example']);
    }

    #[Test]
    public function it_detects_nullable_with_null_coalescing_operator(): void
    {
        $result = $this->analyzer->analyze(ExampleGenerationTransformer::class);

        $this->assertArrayHasKey('nickname', $result['properties']);
        $this->assertTrue($result['properties']['nickname']['nullable']);
    }

    #[Test]
    public function it_handles_ast_parse_returning_null(): void
    {
        $mockAstHelper = $this->createMock(AstHelper::class);
        $mockAstHelper->method('parseFile')->willReturn(null);

        $analyzer = new FractalTransformerAnalyzer($mockAstHelper);
        $result = $analyzer->analyze(UserTransformer::class);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_handles_class_node_not_found(): void
    {
        $mockAstHelper = $this->createMock(AstHelper::class);
        // Return a non-empty array that's not null, but findClassNode returns null
        $mockAstHelper->method('parseFile')->willReturn([new \PhpParser\Node\Stmt\Nop]);
        $mockAstHelper->method('findClassNode')->willReturn(null);

        $errorCollector = new ErrorCollector;
        $analyzer = new FractalTransformerAnalyzer($mockAstHelper, $errorCollector);
        $result = $analyzer->analyze(UserTransformer::class);

        $this->assertEmpty($result);
        $warnings = $errorCollector->getWarnings();
        $this->assertNotEmpty($warnings, 'Expected warning for class_node_not_found');
        $this->assertEquals('class_node_not_found', $warnings[0]['metadata']['error_type']);
    }

    #[Test]
    public function it_handles_reflection_exception(): void
    {
        // Create a mock that will cause ReflectionClass to throw
        $mockAstHelper = $this->createMock(AstHelper::class);

        $errorCollector = new ErrorCollector;
        $analyzer = new FractalTransformerAnalyzer($mockAstHelper, $errorCollector);

        // Use an invalid class name that will cause issues
        $result = $analyzer->analyze('InvalidClassName\That\DoesNotExist');

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_meta_data(): void
    {
        $result = $this->analyzer->analyze(UserTransformer::class);

        $this->assertArrayHasKey('meta', $result);
        $this->assertIsArray($result['meta']);
    }

    #[Test]
    public function it_handles_transformer_without_available_includes(): void
    {
        $result = $this->analyzer->analyze(MinimalTransformer::class);

        $this->assertArrayHasKey('availableIncludes', $result);
        $this->assertEmpty($result['availableIncludes']);
    }

    #[Test]
    public function it_handles_transformer_without_default_includes(): void
    {
        $result = $this->analyzer->analyze(MinimalTransformer::class);

        $this->assertArrayHasKey('defaultIncludes', $result);
        $this->assertEmpty($result['defaultIncludes']);
    }

    #[Test]
    public function it_detects_nested_object_properties(): void
    {
        $result = $this->analyzer->analyze(ExampleGenerationTransformer::class);

        $this->assertArrayHasKey('metadata', $result['properties']);
        $this->assertEquals('object', $result['properties']['metadata']['type']);
        $this->assertArrayHasKey('properties', $result['properties']['metadata']);
    }
}
