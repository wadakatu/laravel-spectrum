<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\DTO;

use LaravelSpectrum\DTO\ErrorEntry;
use LaravelSpectrum\Support\AnalyzerErrorType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ErrorEntryTest extends TestCase
{
    #[Test]
    public function it_creates_error_entry(): void
    {
        $entry = ErrorEntry::error(
            context: 'TestAnalyzer',
            message: 'Something went wrong',
            metadata: ['file' => 'test.php'],
        );

        $this->assertEquals('TestAnalyzer', $entry->context);
        $this->assertEquals('Something went wrong', $entry->message);
        $this->assertEquals(['file' => 'test.php'], $entry->metadata);
        $this->assertTrue($entry->isError());
        $this->assertFalse($entry->isWarning());
        $this->assertNotNull($entry->timestamp);
        $this->assertNotNull($entry->trace);
    }

    #[Test]
    public function it_creates_warning_entry(): void
    {
        $entry = ErrorEntry::warning(
            context: 'TestAnalyzer',
            message: 'Something might be wrong',
            metadata: ['line' => 42],
        );

        $this->assertEquals('TestAnalyzer', $entry->context);
        $this->assertEquals('Something might be wrong', $entry->message);
        $this->assertEquals(['line' => 42], $entry->metadata);
        $this->assertFalse($entry->isError());
        $this->assertTrue($entry->isWarning());
        $this->assertNotNull($entry->timestamp);
        $this->assertNull($entry->trace);
    }

    #[Test]
    public function it_creates_entry_with_empty_metadata(): void
    {
        $entry = ErrorEntry::error(
            context: 'TestAnalyzer',
            message: 'Error without metadata',
        );

        $this->assertEquals([], $entry->metadata);
    }

    #[Test]
    public function it_creates_entry_with_error_type(): void
    {
        $entry = ErrorEntry::error(
            context: 'FormRequestAnalyzer',
            message: 'Failed to analyze',
            metadata: [],
            errorType: AnalyzerErrorType::AnalysisError,
        );

        $this->assertEquals(AnalyzerErrorType::AnalysisError, $entry->errorType);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $entry = ErrorEntry::error(
            context: 'TestAnalyzer',
            message: 'Test error',
            metadata: ['key' => 'value'],
        );

        $array = $entry->toArray();

        $this->assertEquals('TestAnalyzer', $array['context']);
        $this->assertEquals('Test error', $array['message']);
        $this->assertEquals(['key' => 'value'], $array['metadata']);
        $this->assertEquals('error', $array['type']);
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertArrayHasKey('trace', $array);
    }

    #[Test]
    public function it_converts_warning_to_array_without_trace(): void
    {
        $entry = ErrorEntry::warning(
            context: 'TestAnalyzer',
            message: 'Test warning',
        );

        $array = $entry->toArray();

        $this->assertEquals('warning', $array['type']);
        $this->assertArrayNotHasKey('trace', $array);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $data = [
            'context' => 'TestAnalyzer',
            'message' => 'Restored error',
            'metadata' => ['restored' => true],
            'type' => 'error',
            'timestamp' => '2024-01-01T00:00:00+00:00',
            'trace' => [['file' => 'test.php', 'line' => 10]],
        ];

        $entry = ErrorEntry::fromArray($data);

        $this->assertEquals('TestAnalyzer', $entry->context);
        $this->assertEquals('Restored error', $entry->message);
        $this->assertEquals(['restored' => true], $entry->metadata);
        $this->assertTrue($entry->isError());
        $this->assertEquals('2024-01-01T00:00:00+00:00', $entry->timestamp);
        $this->assertEquals([['file' => 'test.php', 'line' => 10]], $entry->trace);
    }

    #[Test]
    public function it_creates_warning_from_array(): void
    {
        $data = [
            'context' => 'TestAnalyzer',
            'message' => 'Restored warning',
            'type' => 'warning',
            'timestamp' => '2024-01-01T00:00:00+00:00',
        ];

        $entry = ErrorEntry::fromArray($data);

        $this->assertTrue($entry->isWarning());
        $this->assertNull($entry->trace);
    }

    #[Test]
    public function it_survives_serialization_round_trip(): void
    {
        $original = ErrorEntry::error(
            context: 'TestAnalyzer',
            message: 'Round trip test',
            metadata: ['test' => 'data'],
        );

        $restored = ErrorEntry::fromArray($original->toArray());

        $this->assertEquals($original->context, $restored->context);
        $this->assertEquals($original->message, $restored->message);
        $this->assertEquals($original->metadata, $restored->metadata);
        $this->assertEquals($original->isError(), $restored->isError());
        $this->assertEquals($original->timestamp, $restored->timestamp);
    }

    #[Test]
    public function it_includes_error_type_in_array_when_present(): void
    {
        $entry = ErrorEntry::error(
            context: 'TestAnalyzer',
            message: 'Typed error',
            errorType: AnalyzerErrorType::UnsupportedFeature,
        );

        $array = $entry->toArray();

        $this->assertEquals('unsupported_feature', $array['errorType']);
    }

    #[Test]
    public function it_restores_error_type_from_array(): void
    {
        $data = [
            'context' => 'TestAnalyzer',
            'message' => 'Typed error',
            'type' => 'error',
            'timestamp' => '2024-01-01T00:00:00+00:00',
            'errorType' => 'analysis_error',
        ];

        $entry = ErrorEntry::fromArray($data);

        $this->assertEquals(AnalyzerErrorType::AnalysisError, $entry->errorType);
    }
}
