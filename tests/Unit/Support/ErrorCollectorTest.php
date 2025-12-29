<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\DTO\ErrorEntry;
use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Tests\TestCase;

class ErrorCollectorTest extends TestCase
{
    public function test_collects_errors_without_throwing(): void
    {
        $collector = new ErrorCollector(failOnError: false);

        $collector->addError('TestContext', 'Test error message', ['key' => 'value']);

        $this->assertTrue($collector->hasErrors());
        $this->assertCount(1, $collector->getErrors());

        $errors = $collector->getErrors();
        $this->assertInstanceOf(ErrorEntry::class, $errors[0]);
        $this->assertEquals('TestContext', $errors[0]->context);
        $this->assertEquals('Test error message', $errors[0]->message);
        $this->assertEquals(['key' => 'value'], $errors[0]->metadata);
        $this->assertNotEmpty($errors[0]->timestamp);
        $this->assertNotNull($errors[0]->trace);
    }

    public function test_throws_on_error_when_configured(): void
    {
        $collector = new ErrorCollector(failOnError: true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error in TestContext: Test error message');

        $collector->addError('TestContext', 'Test error message');
    }

    public function test_collects_warnings(): void
    {
        $collector = new ErrorCollector;

        $collector->addWarning('WarningContext', 'Warning message', ['severity' => 'low']);

        $this->assertFalse($collector->hasErrors());
        $this->assertCount(1, $collector->getWarnings());

        $warnings = $collector->getWarnings();
        $this->assertInstanceOf(ErrorEntry::class, $warnings[0]);
        $this->assertEquals('WarningContext', $warnings[0]->context);
        $this->assertEquals('Warning message', $warnings[0]->message);
        $this->assertEquals(['severity' => 'low'], $warnings[0]->metadata);
        $this->assertNull($warnings[0]->trace);
    }

    public function test_generates_comprehensive_report(): void
    {
        $collector = new ErrorCollector;

        $collector->addError('Context1', 'Error 1', ['type' => 'parse']);
        $collector->addError('Context2', 'Error 2', ['type' => 'validation']);
        $collector->addWarning('Context3', 'Warning 1', ['level' => 'info']);

        $report = $collector->generateReport();

        $this->assertEquals(2, $report['summary']['total_errors']);
        $this->assertEquals(1, $report['summary']['total_warnings']);
        $this->assertArrayHasKey('generated_at', $report['summary']);

        $this->assertCount(2, $report['errors']);
        $this->assertCount(1, $report['warnings']);

        // Report should contain arrays (converted from DTOs)
        $this->assertIsArray($report['errors'][0]);
        $this->assertEquals('Context1', $report['errors'][0]['context']);
    }

    public function test_empty_collector_report(): void
    {
        $collector = new ErrorCollector;

        $report = $collector->generateReport();

        $this->assertEquals(0, $report['summary']['total_errors']);
        $this->assertEquals(0, $report['summary']['total_warnings']);
        $this->assertEmpty($report['errors']);
        $this->assertEmpty($report['warnings']);
    }

    public function test_error_trace_limited_to_5_frames(): void
    {
        $collector = new ErrorCollector;

        $collector->addError('TestContext', 'Test error');

        $errors = $collector->getErrors();
        $this->assertInstanceOf(ErrorEntry::class, $errors[0]);
        $trace = $errors[0]->trace;

        $this->assertIsArray($trace);
        $this->assertLessThanOrEqual(5, count($trace));
    }

    public function test_error_entry_is_error_type(): void
    {
        $collector = new ErrorCollector;

        $collector->addError('TestContext', 'Test error');
        $collector->addWarning('TestContext', 'Test warning');

        $errors = $collector->getErrors();
        $warnings = $collector->getWarnings();

        $this->assertTrue($errors[0]->isError());
        $this->assertFalse($errors[0]->isWarning());

        $this->assertFalse($warnings[0]->isError());
        $this->assertTrue($warnings[0]->isWarning());
    }
}
