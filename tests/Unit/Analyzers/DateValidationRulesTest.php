<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use Illuminate\Foundation\Http\FormRequest;
use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Support\TypeInference;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DateValidationRulesTest extends TestCase
{
    private FormRequestAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock cache that always calls the callback
        $cache = $this->createMock(DocumentationCache::class);
        $cache->method('rememberFormRequest')
            ->willReturnCallback(function ($class, $callback) {
                return $callback();
            });

        $this->analyzer = new FormRequestAnalyzer(new TypeInference, $cache);
    }

    #[Test]
    public function it_handles_basic_date_validation_rules()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'birth_date' => 'required|date',
                    'appointment_time' => 'required|datetime',
                    'formatted_date' => 'required|date_format:Y-m-d',
                    'custom_time' => 'required|date_format:H:i:s',
                    'iso_datetime' => 'required|date_format:Y-m-d\TH:i:sP',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $this->assertCount(5, $parameters);

        $birthDateParam = $this->findParameterByName($parameters, 'birth_date');
        $this->assertEquals('string', $birthDateParam['type']);
        $this->assertEquals('date', $birthDateParam['format'] ?? null);
        $this->assertContains('date', $birthDateParam['validation']);

        $appointmentParam = $this->findParameterByName($parameters, 'appointment_time');
        $this->assertEquals('string', $appointmentParam['type']);
        $this->assertEquals('date-time', $appointmentParam['format'] ?? null);

        $formattedDateParam = $this->findParameterByName($parameters, 'formatted_date');
        $this->assertContains('date_format:Y-m-d', $formattedDateParam['validation']);
        $this->assertStringContainsString('Y-m-d', $formattedDateParam['description'] ?? '');

        $customTimeParam = $this->findParameterByName($parameters, 'custom_time');
        $this->assertContains('date_format:H:i:s', $customTimeParam['validation']);
        $this->assertStringContainsString('H:i:s', $customTimeParam['description'] ?? '');
    }

    #[Test]
    public function it_handles_date_comparison_rules()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'start_date' => 'required|date',
                    'end_date' => 'required|date|after:start_date',
                    'deadline' => 'required|date|after:today',
                    'historical_date' => 'required|date|before:today',
                    'booking_date' => 'required|date|after_or_equal:tomorrow',
                    'expiry_date' => 'required|date|before_or_equal:2025-12-31',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $endDateParam = $this->findParameterByName($parameters, 'end_date');
        $this->assertContains('after:start_date', $endDateParam['validation']);
        $this->assertStringContainsString('must be after start_date', $endDateParam['description'] ?? '');

        $deadlineParam = $this->findParameterByName($parameters, 'deadline');
        $this->assertContains('after:today', $deadlineParam['validation']);
        $this->assertStringContainsString('must be after today', $deadlineParam['description'] ?? '');

        $historicalParam = $this->findParameterByName($parameters, 'historical_date');
        $this->assertContains('before:today', $historicalParam['validation']);
        $this->assertStringContainsString('must be before today', $historicalParam['description'] ?? '');

        $bookingParam = $this->findParameterByName($parameters, 'booking_date');
        $this->assertContains('after_or_equal:tomorrow', $bookingParam['validation']);

        $expiryParam = $this->findParameterByName($parameters, 'expiry_date');
        $this->assertContains('before_or_equal:2025-12-31', $expiryParam['validation']);
    }

    #[Test]
    public function it_handles_date_equals_rule()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'event_date' => 'required|date',
                    'confirmation_date' => 'required|date|date_equals:event_date',
                    'fixed_date' => 'required|date|date_equals:2024-12-25',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $confirmationParam = $this->findParameterByName($parameters, 'confirmation_date');
        $this->assertContains('date_equals:event_date', $confirmationParam['validation']);
        $this->assertStringContainsString('must be equal to event_date', $confirmationParam['description'] ?? '');

        $fixedParam = $this->findParameterByName($parameters, 'fixed_date');
        $this->assertContains('date_equals:2024-12-25', $fixedParam['validation']);
    }

    #[Test]
    public function it_handles_timezone_validation()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'user_timezone' => 'required|timezone',
                    'event_timezone' => 'required|timezone:all',
                    'identifier_timezone' => 'required|timezone:identifier',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $userTimezoneParam = $this->findParameterByName($parameters, 'user_timezone');
        $this->assertEquals('string', $userTimezoneParam['type']);
        $this->assertContains('timezone', $userTimezoneParam['validation']);
        $this->assertStringContainsString('valid timezone', $userTimezoneParam['description'] ?? '');

        $eventTimezoneParam = $this->findParameterByName($parameters, 'event_timezone');
        $this->assertContains('timezone:all', $eventTimezoneParam['validation']);
    }

    #[Test]
    public function it_handles_complex_date_format_patterns()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'mysql_datetime' => 'required|date_format:Y-m-d H:i:s',
                    'iso8601' => 'required|date_format:c',
                    'rfc3339' => 'required|date_format:Y-m-d\TH:i:sP',
                    'unix_timestamp' => 'required|date_format:U',
                    'custom_format' => 'required|date_format:d/m/Y g:i A',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $mysqlParam = $this->findParameterByName($parameters, 'mysql_datetime');
        $this->assertStringContainsString('Y-m-d H:i:s', $mysqlParam['description'] ?? '');
        $this->assertEquals('2024-01-01 14:30:00', $mysqlParam['example'] ?? '');

        $iso8601Param = $this->findParameterByName($parameters, 'iso8601');
        $this->assertContains('date_format:c', $iso8601Param['validation']);

        $unixParam = $this->findParameterByName($parameters, 'unix_timestamp');
        $this->assertContains('date_format:U', $unixParam['validation']);
        $this->assertMatchesRegularExpression('/^\d{10}$/', (string) ($unixParam['example'] ?? ''));
    }

    #[Test]
    public function it_handles_multiple_date_rules_on_same_field()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'booking_start' => 'required|date|after:today|before:+1 year',
                    'booking_end' => [
                        'required',
                        'date',
                        'after:booking_start',
                        'before_or_equal:booking_start +7 days',
                        'date_format:Y-m-d',
                    ],
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $bookingStartParam = $this->findParameterByName($parameters, 'booking_start');
        $this->assertContains('after:today', $bookingStartParam['validation']);
        $this->assertContains('before:+1 year', $bookingStartParam['validation']);

        $bookingEndParam = $this->findParameterByName($parameters, 'booking_end');
        $this->assertContains('after:booking_start', $bookingEndParam['validation']);
        $this->assertContains('date_format:Y-m-d', $bookingEndParam['validation']);
        $this->assertEquals('string', $bookingEndParam['type']);
        $this->assertEquals('date', $bookingEndParam['format'] ?? null);
    }

    #[Test]
    public function it_handles_conditional_date_rules()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'type' => 'required|in:event,deadline,reminder',
                    'event_date' => 'required_if:type,event|date|after:today',
                    'deadline_date' => 'required_if:type,deadline|date|after_or_equal:today',
                    'reminder_date' => 'required_if:type,reminder|date_format:Y-m-d H:i:s|before:event_date',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $eventDateParam = $this->findParameterByName($parameters, 'event_date');
        $this->assertFalse($eventDateParam['required']);
        $this->assertContains('required_if:type,event', $eventDateParam['validation']);
        $this->assertContains('after:today', $eventDateParam['validation']);

        $reminderDateParam = $this->findParameterByName($parameters, 'reminder_date');
        $this->assertContains('date_format:Y-m-d H:i:s', $reminderDateParam['validation']);
        $this->assertContains('before:event_date', $reminderDateParam['validation']);
    }

    #[Test]
    public function it_generates_appropriate_examples_for_date_formats()
    {
        // Arrange
        $testRequestClass = new class extends FormRequest
        {
            public function rules(): array
            {
                return [
                    'simple_date' => 'required|date',
                    'ymd_date' => 'required|date_format:Y-m-d',
                    'dmy_date' => 'required|date_format:d/m/Y',
                    'time_only' => 'required|date_format:H:i',
                    'month_year' => 'required|date_format:F Y',
                ];
            }

            public function attributes(): array
            {
                return [
                    'simple_date' => 'Simple Date',
                    'ymd_date' => 'Date in Y-m-d format',
                    'dmy_date' => 'Date in d/m/Y format',
                    'time_only' => 'Time in H:i format',
                    'month_year' => 'Month and Year',
                ];
            }
        };

        // Act
        $parameters = $this->analyzer->analyze(get_class($testRequestClass));

        // Assert
        $simpleDateParam = $this->findParameterByName($parameters, 'simple_date');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $simpleDateParam['example'] ?? '');

        $ymdParam = $this->findParameterByName($parameters, 'ymd_date');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $ymdParam['example'] ?? '');

        $dmyParam = $this->findParameterByName($parameters, 'dmy_date');
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4}$/', $dmyParam['example'] ?? '');

        $timeParam = $this->findParameterByName($parameters, 'time_only');
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $timeParam['example'] ?? '');

        $monthYearParam = $this->findParameterByName($parameters, 'month_year');
        $this->assertMatchesRegularExpression('/^[A-Za-z]+ \d{4}$/', $monthYearParam['example'] ?? '');
    }

    private function findParameterByName(array $parameters, string $name): ?array
    {
        foreach ($parameters as $parameter) {
            if ($parameter['name'] === $name) {
                return $parameter;
            }
        }

        return null;
    }
}
