<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers\Support;

use LaravelSpectrum\Analyzers\Support\AnonymousClassAnalyzer;
use LaravelSpectrum\Analyzers\Support\AstHelper;
use LaravelSpectrum\Analyzers\Support\FormRequestAstExtractor;
use LaravelSpectrum\Analyzers\Support\ParameterBuilder;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Tests\Fixtures\FormRequests\AnonymousClassFixture;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use PhpParser\Error as PhpParserError;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use PHPUnit\Framework\Attributes\Test;

class AnonymousClassAnalyzerTest extends TestCase
{
    private AnonymousClassAnalyzer $analyzer;

    private FormRequestAstExtractor $astExtractor;

    private ParameterBuilder $parameterBuilder;

    private ErrorCollector $errorCollector;

    private Parser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $astHelper = new AstHelper($this->parser);
        $this->astExtractor = new FormRequestAstExtractor($astHelper, new PrettyPrinter\Standard);
        $this->parameterBuilder = Mockery::mock(ParameterBuilder::class);
        $this->errorCollector = new ErrorCollector;

        $this->analyzer = new AnonymousClassAnalyzer(
            $this->parser,
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

    // ========== AST-based analysis tests ==========

    #[Test]
    public function it_analyzes_anonymous_class_using_ast_when_file_available(): void
    {
        $anonymousRequest = AnonymousClassFixture::getSimpleAnonymousRequest();
        $reflection = new \ReflectionClass($anonymousRequest);

        $expectedRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ];
        $expectedAttributes = [
            'name' => 'User Name',
            'email' => 'Email Address',
        ];

        $this->parameterBuilder->shouldReceive('buildFromRules')
            ->once()
            ->with($expectedRules, $expectedAttributes, Mockery::any(), Mockery::any())
            ->andReturn([
                ['name' => 'name', 'type' => 'string', 'required' => true],
                ['name' => 'email', 'type' => 'string', 'required' => true],
            ]);

        $result = $this->analyzer->analyze($reflection);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function it_logs_warning_when_parser_returns_null_ast(): void
    {
        $mockParser = Mockery::mock(Parser::class);
        $mockParser->shouldReceive('parse')->andReturn(null);

        $analyzer = new AnonymousClassAnalyzer(
            $mockParser,
            $this->astExtractor,
            $this->parameterBuilder,
            $this->errorCollector
        );

        // Create a temporary file to pass the file_exists check
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, '<?php return new class { public function rules(): array { return []; } };');

        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn($tempFile);
        $reflection->shouldReceive('getStartLine')->andReturn(1);
        $reflection->shouldReceive('getEndLine')->andReturn(1);
        $reflection->shouldReceive('getName')->andReturn('TestAnonymousClass');
        $reflection->shouldReceive('getNamespaceName')->andReturn('');
        // Mock for reflection fallback
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andReturn(new \stdClass);
        $reflection->shouldReceive('hasProperty')->with('request')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('rules')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('attributes')->andReturn(false);

        $this->parameterBuilder->shouldReceive('buildFromRules')
            ->andReturn([]);

        $analyzer->analyze($reflection);

        $warnings = $this->errorCollector->getWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Parser returned null AST', $warnings[0]['message']);
        $this->assertEquals('anonymous_ast_null_result', $warnings[0]['metadata']['error_type']);

        unlink($tempFile);
    }

    #[Test]
    public function it_logs_warning_when_anonymous_class_node_not_found(): void
    {
        $mockAstExtractor = Mockery::mock(FormRequestAstExtractor::class);
        $mockAstExtractor->shouldReceive('findAnonymousClassNode')->andReturn(null);

        $analyzer = new AnonymousClassAnalyzer(
            $this->parser,
            $mockAstExtractor,
            $this->parameterBuilder,
            $this->errorCollector
        );

        // Create a temporary file to pass the file_exists check
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, '<?php return new class { public function rules(): array { return []; } };');

        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn($tempFile);
        $reflection->shouldReceive('getStartLine')->andReturn(1);
        $reflection->shouldReceive('getEndLine')->andReturn(1);
        $reflection->shouldReceive('getName')->andReturn('TestAnonymousClass');
        $reflection->shouldReceive('getNamespaceName')->andReturn('');
        // Mock for reflection fallback
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andReturn(new \stdClass);
        $reflection->shouldReceive('hasProperty')->with('request')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('rules')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('attributes')->andReturn(false);

        $this->parameterBuilder->shouldReceive('buildFromRules')
            ->andReturn([]);

        $analyzer->analyze($reflection);

        $warnings = $this->errorCollector->getWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Anonymous class node not found', $warnings[0]['message']);
        $this->assertEquals('anonymous_class_node_not_found', $warnings[0]['metadata']['error_type']);

        unlink($tempFile);
    }

    #[Test]
    public function it_logs_warning_when_php_parser_throws_error(): void
    {
        $mockParser = Mockery::mock(Parser::class);
        $mockParser->shouldReceive('parse')->andThrow(new PhpParserError('Syntax error'));

        $analyzer = new AnonymousClassAnalyzer(
            $mockParser,
            $this->astExtractor,
            $this->parameterBuilder,
            $this->errorCollector
        );

        // Create a temporary file to pass the file_exists check
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, '<?php return new class { public function rules(): array { return []; } };');

        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn($tempFile);
        $reflection->shouldReceive('getStartLine')->andReturn(1);
        $reflection->shouldReceive('getEndLine')->andReturn(1);
        $reflection->shouldReceive('getName')->andReturn('TestAnonymousClass');
        $reflection->shouldReceive('getNamespaceName')->andReturn('');
        // Mock for reflection fallback
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andReturn(new \stdClass);
        $reflection->shouldReceive('hasProperty')->with('request')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('rules')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('attributes')->andReturn(false);

        $this->parameterBuilder->shouldReceive('buildFromRules')
            ->andReturn([]);

        $analyzer->analyze($reflection);

        $warnings = $this->errorCollector->getWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Failed to parse anonymous class AST', $warnings[0]['message']);
        $this->assertEquals('anonymous_ast_parse_error', $warnings[0]['metadata']['error_type']);
        $this->assertEquals('PhpParser\\Error', $warnings[0]['metadata']['exception_class']);

        unlink($tempFile);
    }

    // ========== extractAnonymousClassCode() tests ==========

    #[Test]
    public function it_logs_error_when_file_read_fails(): void
    {
        // Create a directory - file_exists() returns true but file_get_contents() fails
        // Note: In PHP 8.x, file_get_contents on a directory throws an error that gets caught
        // by the outer exception handler, so we verify an error is logged
        $tempDir = sys_get_temp_dir().'/test_dir_'.uniqid();
        mkdir($tempDir);

        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn($tempDir);
        $reflection->shouldReceive('getName')->andReturn('TestClass');
        $reflection->shouldReceive('getStartLine')->andReturn(1);
        $reflection->shouldReceive('getEndLine')->andReturn(1);
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andReturn(new \stdClass);
        $reflection->shouldReceive('hasProperty')->with('request')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('rules')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('attributes')->andReturn(false);
        $reflection->shouldReceive('getNamespaceName')->andReturn('');

        $this->parameterBuilder->shouldReceive('buildFromRules')
            ->andReturn([]);

        $this->analyzer->analyze($reflection);

        $errors = $this->errorCollector->getErrors();
        $this->assertNotEmpty($errors);
        // In PHP 8.x, file_get_contents on a directory throws, which is caught by outer handler
        $this->assertStringContainsString('file_get_contents', $errors[0]['message']);

        rmdir($tempDir);
    }

    #[Test]
    public function it_logs_warning_when_line_info_unavailable(): void
    {
        // Create a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, '<?php class Test {}');

        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn($tempFile);
        $reflection->shouldReceive('getStartLine')->andReturn(false);
        $reflection->shouldReceive('getEndLine')->andReturn(false);
        $reflection->shouldReceive('getName')->andReturn('TestClass');
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andReturn(new \stdClass);
        $reflection->shouldReceive('hasProperty')->with('request')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('rules')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('attributes')->andReturn(false);
        $reflection->shouldReceive('getNamespaceName')->andReturn('');

        $this->parameterBuilder->shouldReceive('buildFromRules')
            ->andReturn([]);

        $this->analyzer->analyze($reflection);

        $warnings = $this->errorCollector->getWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Line information not available', $warnings[0]['message']);
        $this->assertEquals('anonymous_line_info_unavailable', $warnings[0]['metadata']['error_type']);

        unlink($tempFile);
    }

    // ========== analyzeWithConditionalRules() AST tests ==========

    #[Test]
    public function it_analyzes_conditional_rules_using_ast(): void
    {
        $anonymousRequest = AnonymousClassFixture::getConditionalAnonymousRequest();
        $reflection = new \ReflectionClass($anonymousRequest);

        // Since conditional rules exist, buildFromConditionalRules should be called
        $this->parameterBuilder->shouldReceive('buildFromConditionalRules')
            ->once()
            ->andReturn([
                ['name' => 'name', 'type' => 'string', 'required' => true],
                ['name' => 'subscription_id', 'type' => 'string', 'required' => false],
            ]);

        $result = $this->analyzer->analyzeWithConditionalRules($reflection);

        $this->assertArrayHasKey('parameters', $result);
        $this->assertArrayHasKey('conditional_rules', $result);
        $this->assertNotEmpty($result['conditional_rules']['rules_sets']);
    }

    #[Test]
    public function it_falls_back_when_no_conditional_rules_in_ast(): void
    {
        // Mock AST extractor to simulate successful parsing but no conditional rules
        $mockAstExtractor = Mockery::mock(FormRequestAstExtractor::class);
        $mockClassNode = Mockery::mock(\PhpParser\Node\Stmt\Class_::class);
        $mockAstExtractor->shouldReceive('findAnonymousClassNode')->andReturn($mockClassNode);
        $mockAstExtractor->shouldReceive('extractConditionalRules')->andReturn(['rules_sets' => [], 'merged_rules' => []]);
        $mockAstExtractor->shouldReceive('extractRules')->andReturn(['name' => 'required']);
        $mockAstExtractor->shouldReceive('extractAttributes')->andReturn([]);
        $mockAstExtractor->shouldReceive('extractUseStatements')->andReturn([]);

        $analyzer = new AnonymousClassAnalyzer(
            $this->parser,
            $mockAstExtractor,
            $this->parameterBuilder,
            $this->errorCollector
        );

        // Create a temporary file to pass the file_exists check
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, '<?php return new class { public function rules(): array { return []; } };');

        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn($tempFile);
        $reflection->shouldReceive('getStartLine')->andReturn(1);
        $reflection->shouldReceive('getEndLine')->andReturn(1);
        $reflection->shouldReceive('getName')->andReturn('TestSimpleClass');
        $reflection->shouldReceive('getNamespaceName')->andReturn('');
        // Mock for fallback analyze()
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andReturn(new \stdClass);
        $reflection->shouldReceive('hasProperty')->with('request')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('rules')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('attributes')->andReturn(false);

        // No conditional rules, so it falls back to standard analysis
        $this->parameterBuilder->shouldReceive('buildFromRules')
            ->once()
            ->andReturn([['name' => 'name', 'type' => 'string']]);

        $result = $analyzer->analyzeWithConditionalRules($reflection);

        $this->assertArrayHasKey('parameters', $result);
        $this->assertArrayHasKey('conditional_rules', $result);
        $this->assertEmpty($result['conditional_rules']['rules_sets']);

        // Should log a warning about fallback
        $warnings = $this->errorCollector->getWarnings();
        $fallbackWarnings = array_filter($warnings, fn ($w) => isset($w['metadata']['error_type']) && $w['metadata']['error_type'] === 'anonymous_conditional_rules_fallback');
        $this->assertNotEmpty($fallbackWarnings, 'Expected fallback warning to be logged');

        unlink($tempFile);
    }

    #[Test]
    public function it_logs_warning_when_conditional_ast_returns_null(): void
    {
        $mockParser = Mockery::mock(Parser::class);
        $mockParser->shouldReceive('parse')->andReturn(null);

        $analyzer = new AnonymousClassAnalyzer(
            $mockParser,
            $this->astExtractor,
            $this->parameterBuilder,
            $this->errorCollector
        );

        // Create a temporary file to pass the file_exists check
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, '<?php return new class { public function rules(): array { return []; } };');

        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn($tempFile);
        $reflection->shouldReceive('getStartLine')->andReturn(1);
        $reflection->shouldReceive('getEndLine')->andReturn(1);
        $reflection->shouldReceive('getName')->andReturn('TestConditionalClass');
        $reflection->shouldReceive('getNamespaceName')->andReturn('');
        // Mock for fallback analyze()
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andReturn(new \stdClass);
        $reflection->shouldReceive('hasProperty')->with('request')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('rules')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('attributes')->andReturn(false);

        // When AST is null, it falls back to standard analysis
        $this->parameterBuilder->shouldReceive('buildFromRules')
            ->andReturn([]);

        $analyzer->analyzeWithConditionalRules($reflection);

        $warnings = $this->errorCollector->getWarnings();
        $astNullWarnings = array_filter($warnings, fn ($w) => str_contains($w['metadata']['error_type'], 'ast_null'));
        $this->assertNotEmpty($astNullWarnings);

        unlink($tempFile);
    }

    #[Test]
    public function it_logs_error_when_conditional_analysis_throws(): void
    {
        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn(false);
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andThrow(new \Exception('Conditional test error'));
        $reflection->shouldReceive('getName')->andReturn('TestConditionalClass');

        $result = $this->analyzer->analyzeWithConditionalRules($reflection);

        $this->assertArrayHasKey('parameters', $result);
        $this->assertEmpty($result['parameters']);

        $errors = $this->errorCollector->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Conditional test error', $errors[0]['message']);
        // Exception is caught by analyze() fallback, so error type is from analyze()
        $this->assertEquals('anonymous_reflection_analysis_error', $errors[0]['metadata']['error_type']);
    }

    // ========== invokeMethodSafely() tests ==========

    #[Test]
    public function it_logs_error_when_critical_method_invocation_fails(): void
    {
        $mockInstance = new class
        {
            public function rules(): array
            {
                throw new \RuntimeException('Method invocation failed');
            }
        };

        $reflection = new \ReflectionClass($mockInstance);

        // This triggers invokeMethodSafely with criticalForAnalysis=true
        $result = $this->analyzer->analyzeDetails($reflection);

        $this->assertEmpty($result['rules']);

        $errors = $this->errorCollector->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Failed to invoke rules()', $errors[0]['message']);
        $this->assertEquals('anonymous_method_invocation_error', $errors[0]['metadata']['error_type']);
        $this->assertEquals('RuntimeException', $errors[0]['metadata']['exception_class']);
    }

    #[Test]
    public function it_logs_warning_when_non_critical_method_fails(): void
    {
        $mockInstance = new class
        {
            public function rules(): array
            {
                return ['name' => 'required'];
            }

            public function attributes(): array
            {
                throw new \RuntimeException('Attributes method failed');
            }
        };

        $reflection = new \ReflectionClass($mockInstance);

        $result = $this->analyzer->analyzeDetails($reflection);

        // Rules should be extracted
        $this->assertEquals(['name' => 'required'], $result['rules']);
        // Attributes should be empty (default) due to non-critical failure
        $this->assertEmpty($result['attributes']);

        // Warning should be logged for non-critical method failure
        $warnings = $this->errorCollector->getWarnings();
        $attributeWarnings = array_filter($warnings, fn ($w) => str_contains($w['message'], 'attributes'));
        $this->assertNotEmpty($attributeWarnings);
        $this->assertEquals('anonymous_non_critical_method_failure', array_values($attributeWarnings)[0]['metadata']['error_type']);
    }

    // ========== Additional edge case tests ==========

    #[Test]
    public function it_handles_empty_anonymous_request_with_no_methods(): void
    {
        $anonymousRequest = AnonymousClassFixture::getEmptyAnonymousRequest();
        $reflection = new \ReflectionClass($anonymousRequest);

        $this->parameterBuilder->shouldReceive('buildFromRules')
            ->once()
            ->with([], [], Mockery::any(), Mockery::any())
            ->andReturn([]);

        $result = $this->analyzer->analyze($reflection);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_logs_warning_when_conditional_php_parser_throws_error(): void
    {
        $mockParser = Mockery::mock(Parser::class);
        $mockParser->shouldReceive('parse')->andThrow(new PhpParserError('Conditional syntax error'));

        $analyzer = new AnonymousClassAnalyzer(
            $mockParser,
            $this->astExtractor,
            $this->parameterBuilder,
            $this->errorCollector
        );

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, '<?php return new class { public function rules(): array { return []; } };');

        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn($tempFile);
        $reflection->shouldReceive('getStartLine')->andReturn(1);
        $reflection->shouldReceive('getEndLine')->andReturn(1);
        $reflection->shouldReceive('getName')->andReturn('TestConditionalClass');
        $reflection->shouldReceive('getNamespaceName')->andReturn('');
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andReturn(new \stdClass);
        $reflection->shouldReceive('hasProperty')->with('request')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('rules')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('attributes')->andReturn(false);

        $this->parameterBuilder->shouldReceive('buildFromRules')->andReturn([]);

        $analyzer->analyzeWithConditionalRules($reflection);

        $warnings = $this->errorCollector->getWarnings();
        $parseErrorWarnings = array_filter($warnings, fn ($w) => $w['metadata']['error_type'] === 'anonymous_conditional_ast_parse_error');
        $this->assertNotEmpty($parseErrorWarnings, 'Expected conditional AST parse error warning');

        unlink($tempFile);
    }

    #[Test]
    public function it_logs_warning_when_conditional_class_node_not_found(): void
    {
        $mockAstExtractor = Mockery::mock(FormRequestAstExtractor::class);
        $mockAstExtractor->shouldReceive('findAnonymousClassNode')->andReturn(null);

        $analyzer = new AnonymousClassAnalyzer(
            $this->parser,
            $mockAstExtractor,
            $this->parameterBuilder,
            $this->errorCollector
        );

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, '<?php return new class {};');

        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn($tempFile);
        $reflection->shouldReceive('getStartLine')->andReturn(1);
        $reflection->shouldReceive('getEndLine')->andReturn(1);
        $reflection->shouldReceive('getName')->andReturn('TestClass');
        $reflection->shouldReceive('getNamespaceName')->andReturn('');
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andReturn(new \stdClass);
        $reflection->shouldReceive('hasProperty')->with('request')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('rules')->andReturn(false);
        $reflection->shouldReceive('hasMethod')->with('attributes')->andReturn(false);

        $this->parameterBuilder->shouldReceive('buildFromRules')->andReturn([]);

        $analyzer->analyzeWithConditionalRules($reflection);

        $warnings = $this->errorCollector->getWarnings();
        $nodeNotFoundWarnings = array_filter($warnings, fn ($w) => $w['metadata']['error_type'] === 'anonymous_conditional_class_node_not_found');
        $this->assertNotEmpty($nodeNotFoundWarnings, 'Expected conditional class node not found warning');

        unlink($tempFile);
    }

    #[Test]
    public function it_creates_error_collector_when_not_provided(): void
    {
        $analyzer = new AnonymousClassAnalyzer(
            $this->parser,
            $this->astExtractor,
            $this->parameterBuilder,
            null // No error collector provided
        );

        $reflection = Mockery::mock(\ReflectionClass::class);
        $reflection->shouldReceive('getFileName')->andReturn(false);
        $reflection->shouldReceive('newInstanceWithoutConstructor')->andThrow(new \Exception('Test exception'));
        $reflection->shouldReceive('getName')->andReturn('TestClass');

        // Should not throw, just return empty array
        $result = $analyzer->analyze($reflection);
        $this->assertEmpty($result);
    }

    // ========== Integration test ==========

    #[Test]
    public function it_performs_complete_ast_analysis_with_real_anonymous_class(): void
    {
        // Use real ParameterBuilder for integration test
        $realParameterBuilder = $this->app->make(ParameterBuilder::class);
        $analyzer = new AnonymousClassAnalyzer(
            $this->parser,
            $this->astExtractor,
            $realParameterBuilder,
            $this->errorCollector
        );

        $anonymousRequest = AnonymousClassFixture::getSimpleAnonymousRequest();
        $reflection = new \ReflectionClass($anonymousRequest);

        $result = $analyzer->analyze($reflection);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        // Verify the parameters are correctly extracted
        $paramNames = array_column($result, 'name');
        $this->assertContains('name', $paramNames);
        $this->assertContains('email', $paramNames);
    }
}
