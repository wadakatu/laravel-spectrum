<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers\Support;

use LaravelSpectrum\Analyzers\Support\AnonymousClassAnalyzer;
use LaravelSpectrum\Analyzers\Support\FormRequestAstExtractor;
use LaravelSpectrum\Analyzers\Support\ParameterBuilder;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use PhpParser\PrettyPrinter;
use PHPUnit\Framework\Attributes\Test;

class AnonymousClassAnalyzerTest extends TestCase
{
    private AnonymousClassAnalyzer $analyzer;

    private FormRequestAstExtractor $astExtractor;

    private ParameterBuilder $parameterBuilder;

    private ErrorCollector $errorCollector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->astExtractor = new FormRequestAstExtractor(new PrettyPrinter\Standard);
        $this->parameterBuilder = Mockery::mock(ParameterBuilder::class);
        $this->errorCollector = new ErrorCollector;

        $this->analyzer = new AnonymousClassAnalyzer(
            $this->astExtractor,
            $this->parameterBuilder,
            $this->errorCollector
        );
    }

    // ========== analyze() tests ==========

    #[Test]
    public function it_returns_empty_array_when_reflection_fails(): void
    {
        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn(false);
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andThrow(new \Exception('Cannot instantiate'));
        $reflection->shouldReceive('getName')->andReturn('TestClass');

        $result = $this->analyzer->analyze($reflection);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_uses_reflection_when_file_path_not_available(): void
    {
        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn(false);
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andReturn(new \stdClass);
        $reflection->shouldReceive('hasProperty')->with('request')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('rules')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('attributes')->andReturn(false);
        $reflection->shouldReceive('getNamespaceName')->andReturn('');
        $reflection->shouldReceive('getName')->andReturn('TestClass');

        $this->parameterBuilder->shouldReceive('buildFromRules')
            ->with([], [], '')
            ->andReturn([]);

        $result = $this->analyzer->analyze($reflection);

        $this->assertIsArray($result);
    }

    // ========== analyzeDetails() tests ==========

    #[Test]
    public function it_returns_empty_structure_when_details_extraction_fails(): void
    {
        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andThrow(new \Exception('Cannot instantiate'));
        $reflection->shouldReceive('getName')->andReturn('TestClass');
        $reflection->shouldReceive('getFileName')->andReturn('unknown');

        $result = $this->analyzer->analyzeDetails($reflection);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rules', $result);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertEmpty($result['rules']);
    }

    #[Test]
    public function it_extracts_rules_attributes_messages_via_reflection(): void
    {
        $mockInstance = new class
        {
            public function rules(): array
            {
                return ['name' => 'required'];
            }

            public function attributes(): array
            {
                return ['name' => 'User Name'];
            }

            public function messages(): array
            {
                return ['name.required' => 'Name is required.'];
            }
        };

        $reflection = new \ReflectionClass($mockInstance);

        $result = $this->analyzer->analyzeDetails($reflection);

        $this->assertArrayHasKey('rules', $result);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertEquals(['name' => 'required'], $result['rules']);
        $this->assertEquals(['name' => 'User Name'], $result['attributes']);
        $this->assertEquals(['name.required' => 'Name is required.'], $result['messages']);
    }

    #[Test]
    public function it_returns_empty_when_rules_method_throws(): void
    {
        $mockInstance = new class
        {
            public function rules(): array
            {
                throw new \Exception('Rules method failed');
            }
        };

        $reflection = new \ReflectionClass($mockInstance);

        $result = $this->analyzer->analyzeDetails($reflection);

        $this->assertArrayHasKey('rules', $result);
        $this->assertEmpty($result['rules']);
    }

    // ========== analyzeWithConditionalRules() tests ==========

    #[Test]
    public function it_returns_empty_structure_for_conditional_rules_on_failure(): void
    {
        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn(false);
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andThrow(new \Exception('Cannot instantiate'));
        $reflection->shouldReceive('getName')->andReturn('TestClass');

        $result = $this->analyzer->analyzeWithConditionalRules($reflection);

        $this->assertArrayHasKey('parameters', $result);
        $this->assertArrayHasKey('conditional_rules', $result);
        $this->assertEmpty($result['parameters']);
        $this->assertArrayHasKey('rules_sets', $result['conditional_rules']);
        $this->assertArrayHasKey('merged_rules', $result['conditional_rules']);
    }

    #[Test]
    public function it_falls_back_to_analyze_when_no_conditional_rules(): void
    {
        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn(false);
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andReturn(new \stdClass);
        $reflection->shouldReceive('hasProperty')->with('request')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('rules')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('attributes')->andReturn(false);
        $reflection->shouldReceive('getNamespaceName')->andReturn('');
        $reflection->shouldReceive('getName')->andReturn('TestClass');

        $this->parameterBuilder->shouldReceive('buildFromRules')
            ->with([], [], '')
            ->andReturn(['param1']);

        $result = $this->analyzer->analyzeWithConditionalRules($reflection);

        $this->assertArrayHasKey('parameters', $result);
        $this->assertArrayHasKey('conditional_rules', $result);
        $this->assertEquals(['param1'], $result['parameters']);
    }

    // ========== createMockInstance() tests (via analyze) ==========

    #[Test]
    public function it_initializes_request_property_when_available(): void
    {
        // Use a mock to simulate a class without a file path (forcing reflection path)
        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn(false);

        $mockInstance = new \stdClass;
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andReturn($mockInstance);
        $reflection->shouldReceive('hasProperty')->with('request')->andReturn(true);

        $mockProperty = Mockery::mock(\ReflectionProperty::class);
        $mockProperty->shouldReceive('setAccessible')->with(true);
        $mockProperty->shouldReceive('setValue')->with($mockInstance, Mockery::type(\Illuminate\Http\Request::class));

        $reflection->shouldReceive('getProperty')->with('request')->andReturn($mockProperty);
        $reflection->shouldReceive('hasMethod')->with('rules')->andReturn(true);

        $mockMethod = Mockery::mock(\ReflectionMethod::class);
        $mockMethod->shouldReceive('setAccessible')->with(true);
        $mockMethod->shouldReceive('invoke')->with($mockInstance)->andReturn(['name' => 'required']);

        $reflection->shouldReceive('getMethod')->with('rules')->andReturn($mockMethod);
        $reflection->shouldReceive('hasMethod')->with('attributes')->andReturn(false);
        $reflection->shouldReceive('getNamespaceName')->andReturn('');
        $reflection->shouldReceive('getName')->andReturn('TestClass');

        $this->parameterBuilder->shouldReceive('buildFromRules')
            ->with(['name' => 'required'], [], '')
            ->andReturn([['name' => 'name', 'type' => 'string']]);

        $result = $this->analyzer->analyze($reflection);

        $this->assertEquals([['name' => 'name', 'type' => 'string']], $result);
    }

    // ========== Error collector tests ==========

    #[Test]
    public function it_logs_error_when_analysis_fails(): void
    {
        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn(false);
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andThrow(new \Exception('Test error'));
        $reflection->shouldReceive('getName')->andReturn('TestAnonymousClass');

        $this->analyzer->analyze($reflection);

        $errors = $this->errorCollector->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Test error', $errors[0]['message']);
        $this->assertEquals('Exception', $errors[0]['metadata']['exception_class']);
    }

    #[Test]
    public function it_logs_error_when_details_extraction_fails(): void
    {
        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andThrow(new \Exception('Details extraction error'));
        $reflection->shouldReceive('getName')->andReturn('TestAnonymousClass');
        $reflection->shouldReceive('getFileName')->andReturn('unknown');

        $this->analyzer->analyzeDetails($reflection);

        $errors = $this->errorCollector->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Details extraction error', $errors[0]['message']);
        $this->assertEquals('Exception', $errors[0]['metadata']['exception_class']);
    }

    // ========== Edge cases ==========

    #[Test]
    public function it_handles_class_without_rules_method(): void
    {
        $mockInstance = new class {};

        $reflection = new \ReflectionClass($mockInstance);

        $this->parameterBuilder->shouldReceive('buildFromRules')
            ->with([], [], '')
            ->andReturn([]);

        $result = $this->analyzer->analyze($reflection);

        $this->assertIsArray($result);
    }

    #[Test]
    public function it_handles_class_without_attributes_method(): void
    {
        $mockInstance = new class
        {
            public function rules(): array
            {
                return ['email' => 'required|email'];
            }
        };

        $reflection = new \ReflectionClass($mockInstance);

        $result = $this->analyzer->analyzeDetails($reflection);

        $this->assertArrayHasKey('attributes', $result);
        $this->assertEmpty($result['attributes']);
    }

    #[Test]
    public function it_handles_class_without_messages_method(): void
    {
        $mockInstance = new class
        {
            public function rules(): array
            {
                return ['email' => 'required'];
            }
        };

        $reflection = new \ReflectionClass($mockInstance);

        $result = $this->analyzer->analyzeDetails($reflection);

        $this->assertArrayHasKey('messages', $result);
        $this->assertEmpty($result['messages']);
    }
}
