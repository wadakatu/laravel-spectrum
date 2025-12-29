<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use Carbon\Carbon;
use LaravelSpectrum\DTO\DiagnosticReport;
use LaravelSpectrum\DTO\ErrorEntry;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DiagnosticReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2024-01-15 10:30:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function it_creates_diagnostic_report_with_errors_and_warnings(): void
    {
        $errors = [
            ErrorEntry::error('TestContext', 'Error message 1', ['key' => 'value1']),
            ErrorEntry::error('TestContext', 'Error message 2', ['key' => 'value2']),
        ];
        $warnings = [
            ErrorEntry::warning('TestContext', 'Warning message 1'),
        ];

        $report = DiagnosticReport::create($errors, $warnings);

        $this->assertCount(2, $report->errors);
        $this->assertCount(1, $report->warnings);
        $this->assertEquals(2, $report->totalErrors);
        $this->assertEquals(1, $report->totalWarnings);
        $this->assertEquals('2024-01-15T10:30:00+00:00', $report->generatedAt);
    }

    #[Test]
    public function it_creates_empty_diagnostic_report(): void
    {
        $report = DiagnosticReport::create([], []);

        $this->assertCount(0, $report->errors);
        $this->assertCount(0, $report->warnings);
        $this->assertEquals(0, $report->totalErrors);
        $this->assertEquals(0, $report->totalWarnings);
    }

    #[Test]
    public function it_checks_if_report_has_errors(): void
    {
        $reportWithErrors = DiagnosticReport::create(
            [ErrorEntry::error('Context', 'Error')],
            []
        );
        $reportWithoutErrors = DiagnosticReport::create([], []);

        $this->assertTrue($reportWithErrors->hasErrors());
        $this->assertFalse($reportWithoutErrors->hasErrors());
    }

    #[Test]
    public function it_checks_if_report_has_warnings(): void
    {
        $reportWithWarnings = DiagnosticReport::create(
            [],
            [ErrorEntry::warning('Context', 'Warning')]
        );
        $reportWithoutWarnings = DiagnosticReport::create([], []);

        $this->assertTrue($reportWithWarnings->hasWarnings());
        $this->assertFalse($reportWithoutWarnings->hasWarnings());
    }

    #[Test]
    public function it_checks_if_report_has_any_issues(): void
    {
        $reportWithErrors = DiagnosticReport::create(
            [ErrorEntry::error('Context', 'Error')],
            []
        );
        $reportWithWarnings = DiagnosticReport::create(
            [],
            [ErrorEntry::warning('Context', 'Warning')]
        );
        $reportWithBoth = DiagnosticReport::create(
            [ErrorEntry::error('Context', 'Error')],
            [ErrorEntry::warning('Context', 'Warning')]
        );
        $emptyReport = DiagnosticReport::create([], []);

        $this->assertTrue($reportWithErrors->hasIssues());
        $this->assertTrue($reportWithWarnings->hasIssues());
        $this->assertTrue($reportWithBoth->hasIssues());
        $this->assertFalse($emptyReport->hasIssues());
    }

    #[Test]
    public function it_converts_to_array_format(): void
    {
        $errors = [
            ErrorEntry::error('TestContext', 'Error message', ['key' => 'value']),
        ];
        $warnings = [
            ErrorEntry::warning('TestContext', 'Warning message'),
        ];

        $report = DiagnosticReport::create($errors, $warnings);
        $array = $report->toArray();

        $this->assertArrayHasKey('summary', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertArrayHasKey('warnings', $array);

        $this->assertEquals(1, $array['summary']['total_errors']);
        $this->assertEquals(1, $array['summary']['total_warnings']);
        $this->assertEquals('2024-01-15T10:30:00+00:00', $array['summary']['generated_at']);

        $this->assertCount(1, $array['errors']);
        $this->assertCount(1, $array['warnings']);

        // Verify errors and warnings are converted to arrays
        $this->assertIsArray($array['errors'][0]);
        $this->assertEquals('Error message', $array['errors'][0]['message']);
        $this->assertIsArray($array['warnings'][0]);
        $this->assertEquals('Warning message', $array['warnings'][0]['message']);
    }

    #[Test]
    public function it_creates_from_array_format(): void
    {
        $data = [
            'summary' => [
                'total_errors' => 1,
                'total_warnings' => 2,
                'generated_at' => '2024-01-15T10:30:00+00:00',
            ],
            'errors' => [
                [
                    'context' => 'TestContext',
                    'message' => 'Error message',
                    'metadata' => ['key' => 'value'],
                    'type' => 'error',
                    'timestamp' => '2024-01-15T10:30:00+00:00',
                    'trace' => null,
                    'error_type' => null,
                ],
            ],
            'warnings' => [
                [
                    'context' => 'TestContext',
                    'message' => 'Warning 1',
                    'metadata' => [],
                    'type' => 'warning',
                    'timestamp' => '2024-01-15T10:30:00+00:00',
                    'trace' => null,
                    'error_type' => null,
                ],
                [
                    'context' => 'TestContext',
                    'message' => 'Warning 2',
                    'metadata' => [],
                    'type' => 'warning',
                    'timestamp' => '2024-01-15T10:30:00+00:00',
                    'trace' => null,
                    'error_type' => null,
                ],
            ],
        ];

        $report = DiagnosticReport::fromArray($data);

        $this->assertEquals(1, $report->totalErrors);
        $this->assertEquals(2, $report->totalWarnings);
        $this->assertEquals('2024-01-15T10:30:00+00:00', $report->generatedAt);
        $this->assertCount(1, $report->errors);
        $this->assertCount(2, $report->warnings);

        $this->assertInstanceOf(ErrorEntry::class, $report->errors[0]);
        $this->assertEquals('Error message', $report->errors[0]->message);
        $this->assertInstanceOf(ErrorEntry::class, $report->warnings[0]);
    }

    #[Test]
    public function it_performs_round_trip_serialization(): void
    {
        $errors = [
            ErrorEntry::error('Context1', 'Error 1', ['meta' => 'data']),
            ErrorEntry::error('Context2', 'Error 2'),
        ];
        $warnings = [
            ErrorEntry::warning('Context3', 'Warning 1'),
        ];

        $original = DiagnosticReport::create($errors, $warnings);
        $array = $original->toArray();
        $restored = DiagnosticReport::fromArray($array);

        $this->assertEquals($original->totalErrors, $restored->totalErrors);
        $this->assertEquals($original->totalWarnings, $restored->totalWarnings);
        $this->assertEquals($original->generatedAt, $restored->generatedAt);
        $this->assertCount(count($original->errors), $restored->errors);
        $this->assertCount(count($original->warnings), $restored->warnings);
    }

    #[Test]
    public function it_provides_total_issues_count(): void
    {
        $report = DiagnosticReport::create(
            [
                ErrorEntry::error('Context', 'Error 1'),
                ErrorEntry::error('Context', 'Error 2'),
            ],
            [
                ErrorEntry::warning('Context', 'Warning 1'),
                ErrorEntry::warning('Context', 'Warning 2'),
                ErrorEntry::warning('Context', 'Warning 3'),
            ]
        );

        $this->assertEquals(5, $report->totalIssues());
    }

    #[Test]
    public function it_filters_errors_by_context(): void
    {
        $errors = [
            ErrorEntry::error('FormRequestAnalyzer', 'Error 1'),
            ErrorEntry::error('RouteAnalyzer', 'Error 2'),
            ErrorEntry::error('FormRequestAnalyzer', 'Error 3'),
        ];

        $report = DiagnosticReport::create($errors, []);
        $filtered = $report->getErrorsByContext('FormRequestAnalyzer');

        $this->assertCount(2, $filtered);
        $this->assertEquals('Error 1', $filtered[0]->message);
        $this->assertEquals('Error 3', $filtered[1]->message);
    }

    #[Test]
    public function it_filters_warnings_by_context(): void
    {
        $warnings = [
            ErrorEntry::warning('SchemaGenerator', 'Warning 1'),
            ErrorEntry::warning('RouteAnalyzer', 'Warning 2'),
            ErrorEntry::warning('SchemaGenerator', 'Warning 3'),
        ];

        $report = DiagnosticReport::create([], $warnings);
        $filtered = $report->getWarningsByContext('SchemaGenerator');

        $this->assertCount(2, $filtered);
        $this->assertEquals('Warning 1', $filtered[0]->message);
        $this->assertEquals('Warning 3', $filtered[1]->message);
    }
}
