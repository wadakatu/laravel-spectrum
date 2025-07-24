<?php

namespace LaravelSpectrum\Tests\Feature;

use LaravelSpectrum\Support\ErrorCollector;
use LaravelSpectrum\Tests\TestCase;

class ErrorHandlingIntegrationTest extends TestCase
{
    public function test_error_report_file_generation()
    {
        $reportPath = sys_get_temp_dir().'/spectrum-test-error-report.json';

        if (file_exists($reportPath)) {
            unlink($reportPath);
        }

        // Mock route files to prevent actual route loading
        $this->app['config']->set('spectrum.route_patterns', ['api/nonexistent/*']);

        // Run command with error report
        $this->artisan('spectrum:generate', [
            '--error-report' => $reportPath,
            '--no-cache' => true,
        ])->assertExitCode(1); // Will fail because no routes found

        // No error report should be created when there are no errors (just no routes)
        $this->assertFileDoesNotExist($reportPath);
    }

    public function test_error_collector_basic_functionality()
    {
        $collector = new ErrorCollector;

        $collector->addError('TestContext', 'Test error', ['key' => 'value']);
        $collector->addWarning('TestContext', 'Test warning');

        $report = $collector->generateReport();

        $this->assertEquals(1, $report['summary']['total_errors']);
        $this->assertEquals(1, $report['summary']['total_warnings']);
        $this->assertNotEmpty($report['summary']['generated_at']);

        $this->assertCount(1, $report['errors']);
        $this->assertEquals('TestContext', $report['errors'][0]['context']);
        $this->assertEquals('Test error', $report['errors'][0]['message']);
        $this->assertEquals(['key' => 'value'], $report['errors'][0]['metadata']);

        $this->assertCount(1, $report['warnings']);
        $this->assertEquals('TestContext', $report['warnings'][0]['context']);
        $this->assertEquals('Test warning', $report['warnings'][0]['message']);
    }

    public function test_error_collector_fail_on_error()
    {
        $collector = new ErrorCollector(failOnError: true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error in TestContext: Fatal error');

        $collector->addError('TestContext', 'Fatal error');
    }

    protected function tearDown(): void
    {
        // Clean up any test files
        $reportPath = sys_get_temp_dir().'/spectrum-test-error-report.json';
        if (file_exists($reportPath)) {
            unlink($reportPath);
        }

        parent::tearDown();
    }
}
