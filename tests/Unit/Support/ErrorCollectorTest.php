<?php

namespace LaravelSpectrum\Tests\Unit\Support;

use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Tests\TestCase;

class ErrorCollectorTest extends TestCase
{
    public function test_collects_errors_without_throwing()
    {
        $collector = new ErrorCollector(failOnError: false);

        $collector->addError('TestContext', 'Test error message', ['key' => 'value']);

        $this->assertTrue($collector->hasErrors());
        $this->assertCount(1, $collector->getErrors());

        $errors = $collector->getErrors();
        $this->assertEquals('TestContext', $errors[0]['context']);
        $this->assertEquals('Test error message', $errors[0]['message']);
        $this->assertEquals(['key' => 'value'], $errors[0]['metadata']);
        $this->assertArrayHasKey('timestamp', $errors[0]);
        $this->assertArrayHasKey('trace', $errors[0]);
    }

    public function test_throws_on_error_when_configured()
    {
        $collector = new ErrorCollector(failOnError: true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error in TestContext: Test error message');

        $collector->addError('TestContext', 'Test error message');
    }

    public function test_collects_warnings()
    {
        $collector = new ErrorCollector;

        $collector->addWarning('WarningContext', 'Warning message', ['severity' => 'low']);

        $this->assertFalse($collector->hasErrors());
        $this->assertCount(1, $collector->getWarnings());

        $warnings = $collector->getWarnings();
        $this->assertEquals('WarningContext', $warnings[0]['context']);
        $this->assertEquals('Warning message', $warnings[0]['message']);
        $this->assertEquals(['severity' => 'low'], $warnings[0]['metadata']);
    }

    public function test_generates_comprehensive_report()
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
    }

    public function test_empty_collector_report()
    {
        $collector = new ErrorCollector;

        $report = $collector->generateReport();

        $this->assertEquals(0, $report['summary']['total_errors']);
        $this->assertEquals(0, $report['summary']['total_warnings']);
        $this->assertEmpty($report['errors']);
        $this->assertEmpty($report['warnings']);
    }

    public function test_error_trace_limited_to_5_frames()
    {
        $collector = new ErrorCollector;

        $collector->addError('TestContext', 'Test error');

        $errors = $collector->getErrors();
        $trace = $errors[0]['trace'];

        $this->assertIsArray($trace);
        $this->assertLessThanOrEqual(5, count($trace));
    }
}
