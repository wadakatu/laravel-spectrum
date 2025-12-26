<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\Support\AnalyzerErrorType;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Support\HasErrorCollection;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test class that uses the HasErrorCollection trait.
 */
class TestClassWithErrorCollection
{
    use HasErrorCollection;

    public function __construct(?ErrorCollector $errorCollector = null)
    {
        $this->initializeErrorCollector($errorCollector);
    }

    public function triggerError(string $message, AnalyzerErrorType $type, array $metadata = []): void
    {
        $this->logError($message, $type, $metadata);
    }

    public function triggerWarning(string $message, AnalyzerErrorType $type, array $metadata = []): void
    {
        $this->logWarning($message, $type, $metadata);
    }

    public function triggerException(\Throwable $exception, AnalyzerErrorType $type, array $metadata = []): void
    {
        $this->logException($exception, $type, $metadata);
    }
}

/**
 * Test class without initialization for testing error case.
 */
class TestClassWithoutInitialization
{
    use HasErrorCollection;

    public function getCollector(): ErrorCollector
    {
        return $this->getErrorCollector();
    }
}

class HasErrorCollectionTest extends TestCase
{
    #[Test]
    public function it_initializes_error_collector_with_null(): void
    {
        $testClass = new TestClassWithErrorCollection(null);

        $collector = $testClass->getErrorCollector();

        $this->assertInstanceOf(ErrorCollector::class, $collector);
    }

    #[Test]
    public function it_initializes_error_collector_with_provided_instance(): void
    {
        $collector = new ErrorCollector;
        $testClass = new TestClassWithErrorCollection($collector);

        $this->assertSame($collector, $testClass->getErrorCollector());
    }

    #[Test]
    public function it_throws_exception_when_not_initialized(): void
    {
        $testClass = new TestClassWithoutInitialization;

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ErrorCollector not initialized');

        $testClass->getCollector();
    }

    #[Test]
    public function it_logs_error_with_standardized_metadata(): void
    {
        $collector = new ErrorCollector;
        $testClass = new TestClassWithErrorCollection($collector);

        $testClass->triggerError(
            'Test error message',
            AnalyzerErrorType::ParseError,
            ['class' => 'TestClass']
        );

        $this->assertTrue($collector->hasErrors());
        $errors = $collector->getErrors();
        $this->assertCount(1, $errors);

        $error = $errors[0];
        $this->assertEquals(TestClassWithErrorCollection::class, $error['context']);
        $this->assertEquals('Test error message', $error['message']);
        $this->assertEquals(AnalyzerErrorType::ParseError->value, $error['metadata']['error_type']);
        $this->assertEquals('TestClass', $error['metadata']['class']);
    }

    #[Test]
    public function it_logs_warning_with_standardized_metadata(): void
    {
        $collector = new ErrorCollector;
        $testClass = new TestClassWithErrorCollection($collector);

        $testClass->triggerWarning(
            'Test warning message',
            AnalyzerErrorType::UnsupportedFeature,
            ['feature' => 'advanced']
        );

        $this->assertFalse($collector->hasErrors());
        $warnings = $collector->getWarnings();
        $this->assertCount(1, $warnings);

        $warning = $warnings[0];
        $this->assertEquals(TestClassWithErrorCollection::class, $warning['context']);
        $this->assertEquals('Test warning message', $warning['message']);
        $this->assertEquals(AnalyzerErrorType::UnsupportedFeature->value, $warning['metadata']['error_type']);
        $this->assertEquals('advanced', $warning['metadata']['feature']);
    }

    #[Test]
    public function it_logs_exception_with_exception_details(): void
    {
        $collector = new ErrorCollector;
        $testClass = new TestClassWithErrorCollection($collector);

        $exception = new \RuntimeException('Something went wrong');

        $testClass->triggerException(
            $exception,
            AnalyzerErrorType::AnalysisError,
            ['action' => 'processing']
        );

        $this->assertTrue($collector->hasErrors());
        $errors = $collector->getErrors();
        $this->assertCount(1, $errors);

        $error = $errors[0];
        $this->assertEquals(TestClassWithErrorCollection::class, $error['context']);
        $this->assertEquals('Something went wrong', $error['message']);
        $this->assertEquals(AnalyzerErrorType::AnalysisError->value, $error['metadata']['error_type']);
        $this->assertEquals(\RuntimeException::class, $error['metadata']['exception_class']);
        $this->assertArrayHasKey('file', $error['metadata']);
        $this->assertArrayHasKey('line', $error['metadata']);
        $this->assertArrayHasKey('trace', $error['metadata']);
        $this->assertEquals('processing', $error['metadata']['action']);
    }

    #[Test]
    public function it_logs_multiple_errors_and_warnings(): void
    {
        $collector = new ErrorCollector;
        $testClass = new TestClassWithErrorCollection($collector);

        $testClass->triggerError('Error 1', AnalyzerErrorType::ParseError);
        $testClass->triggerError('Error 2', AnalyzerErrorType::AnalysisError);
        $testClass->triggerWarning('Warning 1', AnalyzerErrorType::UnsupportedFeature);

        $this->assertCount(2, $collector->getErrors());
        $this->assertCount(1, $collector->getWarnings());
    }

    #[Test]
    public function it_includes_all_error_types_in_metadata(): void
    {
        $collector = new ErrorCollector;
        $testClass = new TestClassWithErrorCollection($collector);

        $errorTypes = [
            AnalyzerErrorType::ParseError,
            AnalyzerErrorType::AnalysisError,
            AnalyzerErrorType::UnsupportedFeature,
            AnalyzerErrorType::ClassNodeNotFound,
            AnalyzerErrorType::ConditionalAnalysisError,
        ];

        foreach ($errorTypes as $type) {
            $testClass->triggerError("Error for {$type->value}", $type);
        }

        $errors = $collector->getErrors();
        $this->assertCount(count($errorTypes), $errors);

        foreach ($errors as $index => $error) {
            $this->assertEquals($errorTypes[$index]->value, $error['metadata']['error_type']);
        }
    }

    #[Test]
    public function it_shares_error_collector_between_calls(): void
    {
        $collector = new ErrorCollector;
        $testClass = new TestClassWithErrorCollection($collector);

        $testClass->triggerError('First error', AnalyzerErrorType::ParseError);

        $this->assertSame($collector, $testClass->getErrorCollector());
        $this->assertCount(1, $testClass->getErrorCollector()->getErrors());

        $testClass->triggerError('Second error', AnalyzerErrorType::AnalysisError);

        $this->assertCount(2, $testClass->getErrorCollector()->getErrors());
    }
}
