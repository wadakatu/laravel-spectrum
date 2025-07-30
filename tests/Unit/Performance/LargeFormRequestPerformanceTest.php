<?php

namespace LaravelSpectrum\Tests\Unit\Performance;

use LaravelSpectrum\Analyzers\FormRequestAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Support\TypeInference;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LaravelSpectrum\Tests\Fixtures\LargeFormRequests\VeryLargeFormRequest;
use LaravelSpectrum\Tests\Fixtures\LargeFormRequests\ExtremelyLargeFormRequest;
use LaravelSpectrum\Generators\SchemaGenerator;

#[Group('performance')]
class LargeFormRequestPerformanceTest extends TestCase
{
    private FormRequestAnalyzer $analyzer;
    private SchemaGenerator $schemaGenerator;

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
        $this->schemaGenerator = new SchemaGenerator();
    }

    #[Test]
    public function it_handles_schema_generation_for_100_fields_efficiently()
    {
        // Arrange - Generate parameters for 100 fields
        $parameters = $this->generateLargeParameterSet(100);

        // Measure performance
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Act - Generate schema from parameters
        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $startMemory;

        // Assert
        $this->assertArrayHasKey('properties', $schema);
        $this->assertCount(100, $schema['properties']);
        
        // Performance assertions
        $this->assertLessThan(0.5, $executionTime, 
            "Schema generation for 100 fields took {$executionTime}s, expected < 0.5s");
        
        $memoryUsedMB = $memoryUsed / 1024 / 1024;
        $this->assertLessThan(20, $memoryUsedMB,
            "Memory usage ({$memoryUsedMB}MB) exceeded 20MB for 100 fields");

        // Verify schema properties
        $this->verifySchemaProperties($schema);

        // Log performance metrics
        $this->logPerformanceMetrics(100, $executionTime, $memoryUsedMB, 'schema generation');
    }

    #[Test]
    public function it_handles_schema_generation_for_500_fields_efficiently()
    {
        // Arrange - Generate parameters for 500 fields
        $parameters = $this->generateLargeParameterSet(500);

        // Measure performance
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Act - Generate schema from parameters
        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $startMemory;

        // Assert
        $this->assertArrayHasKey('properties', $schema);
        $this->assertCount(500, $schema['properties']);
        
        // Performance assertions (more lenient for 500 fields)
        $this->assertLessThan(2.0, $executionTime, 
            "Schema generation for 500 fields took {$executionTime}s, expected < 2s");
        
        $memoryUsedMB = $memoryUsed / 1024 / 1024;
        $this->assertLessThan(100, $memoryUsedMB,
            "Memory usage ({$memoryUsedMB}MB) exceeded 100MB for 500 fields");

        // Log performance metrics
        $this->logPerformanceMetrics(500, $executionTime, $memoryUsedMB, 'schema generation');
    }

    #[Test]
    public function it_handles_form_request_analysis_with_standard_test_fixture()
    {
        // Skip this test as FormRequestAnalyzer requires actual file-based classes
        // Anonymous classes cannot be analyzed via AST parsing
        $this->markTestSkipped(
            'FormRequestAnalyzer requires actual file-based classes for AST parsing. ' .
            'Anonymous classes cannot be analyzed. Use VeryLargeFormRequest or ExtremelyLargeFormRequest fixtures instead.'
        );
    }

    #[Test]
    public function it_handles_complex_nested_parameters_efficiently()
    {
        // Arrange - Generate deeply nested parameters
        $parameters = [];
        
        // Create 50 top-level fields
        for ($i = 1; $i <= 50; $i++) {
            $parameters[] = [
                'name' => "data_{$i}",
                'in' => 'body',
                'required' => true,
                'type' => 'array',
                'description' => "Data Object {$i}",
                'validation' => ['required', 'array'],
                'example' => []
            ];
            
            // Each has 10 nested fields
            for ($j = 1; $j <= 10; $j++) {
                $parameters[] = [
                    'name' => "data_{$i}.item_{$j}",
                    'in' => 'body',
                    'required' => true,
                    'type' => 'string',
                    'description' => "Nested Item {$j} in Data {$i}",
                    'validation' => ['required', 'string'],
                    'example' => "value_{$j}"
                ];
                
                $parameters[] = [
                    'name' => "data_{$i}.item_{$j}.name",
                    'in' => 'body',
                    'required' => true,
                    'type' => 'string',
                    'description' => "Name for Item {$j}",
                    'validation' => ['required', 'string', 'max:100'],
                    'example' => "name_{$j}"
                ];
                
                $parameters[] = [
                    'name' => "data_{$i}.item_{$j}.value",
                    'in' => 'body',
                    'required' => true,
                    'type' => 'number',
                    'description' => "Value for Item {$j}",
                    'validation' => ['required', 'numeric', 'min:0'],
                    'example' => $j * 10
                ];
            }
        }

        // Measure performance
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Act - Generate schema from nested parameters
        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $startMemory;

        // Assert - 50 + (50 * 10 * 3) = 1550 fields
        $this->assertArrayHasKey('properties', $schema);
        $totalFields = $this->countSchemaProperties($schema['properties']);
        $this->assertGreaterThanOrEqual(1550, $totalFields);

        // Performance assertions
        $this->assertLessThan(3.0, $executionTime, 
            "Schema generation for nested structure took {$executionTime}s, expected < 3s");

        $memoryUsedMB = $memoryUsed / 1024 / 1024;
        $this->assertLessThan(150, $memoryUsedMB,
            "Memory usage ({$memoryUsedMB}MB) exceeded 150MB");

        $this->logPerformanceMetrics($totalFields, $executionTime, $memoryUsedMB, 'nested schema');
    }

    #[Test]
    public function it_handles_conditional_rules_with_many_fields_efficiently()
    {
        // Arrange - Generate parameters with conditional rules
        $parameters = [];
        
        $parameters[] = [
            'name' => 'type',
            'in' => 'body',
            'required' => true,
            'type' => 'string',
            'description' => 'Type',
            'validation' => ['required', 'in:personal,business,enterprise'],
            'example' => 'business'
        ];

        // Create 100 fields with conditional dependencies
        for ($i = 1; $i <= 100; $i++) {
            $parameters[] = [
                'name' => "field_{$i}",
                'in' => 'body',
                'required' => false,
                'conditional_required' => true,
                'type' => 'string',
                'description' => "Field {$i}. Required when type is business. Required when type is enterprise. Required when any of these fields are present: field_" . max(1, $i - 1),
                'validation' => [
                    "required_if:type,business",
                    "required_if:type,enterprise",
                    "required_with:field_" . max(1, $i - 1),
                    "prohibited_if:field_" . max(1, $i - 2) . ",disabled",
                    "string",
                    "max:255"
                ],
                'conditional_rules' => [
                    [
                        'type' => 'required_if',
                        'parameters' => 'type,business',
                        'full_rule' => 'required_if:type,business'
                    ],
                    [
                        'type' => 'required_if',
                        'parameters' => 'type,enterprise',
                        'full_rule' => 'required_if:type,enterprise'
                    ],
                    [
                        'type' => 'required_with',
                        'parameters' => 'field_' . max(1, $i - 1),
                        'full_rule' => 'required_with:field_' . max(1, $i - 1)
                    ],
                    [
                        'type' => 'prohibited_if',
                        'parameters' => 'field_' . max(1, $i - 2) . ',disabled',
                        'full_rule' => 'prohibited_if:field_' . max(1, $i - 2) . ',disabled'
                    ]
                ],
                'example' => "value_{$i}"
            ];
        }

        // Measure performance
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Act - Generate schema from parameters with conditional rules
        $schema = $this->schemaGenerator->generateFromParameters($parameters);

        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $startMemory;

        // Assert
        $this->assertArrayHasKey('properties', $schema);
        $this->assertCount(101, $schema['properties']); // 100 fields + type

        // Performance assertions
        $this->assertLessThan(2.0, $executionTime, 
            "Schema generation with conditional rules took {$executionTime}s, expected < 2s");

        $memoryUsedMB = $memoryUsed / 1024 / 1024;
        $this->assertLessThan(100, $memoryUsedMB,
            "Memory usage ({$memoryUsedMB}MB) exceeded 100MB");

        $this->logPerformanceMetrics(101, $executionTime, $memoryUsedMB, 'conditional schema');
    }

    /**
     * Generate a large parameter set for testing
     */
    private function generateLargeParameterSet(int $count): array
    {
        $parameters = [];

        for ($i = 1; $i <= $count; $i++) {
            switch ($i % 10) {
                case 0:
                    $parameters[] = [
                        'name' => "string_field_{$i}",
                        'in' => 'body',
                        'required' => true,
                        'type' => 'string',
                        'description' => "String Field {$i}",
                        'example' => "string_value_{$i}",
                        'validation' => ['required', 'string', 'min:3', 'max:255'],
                        'minLength' => 3,
                        'maxLength' => 255
                    ];
                    break;
                case 1:
                    $parameters[] = [
                        'name' => "integer_field_{$i}",
                        'in' => 'body',
                        'required' => true,
                        'type' => 'integer',
                        'description' => "Integer Field {$i}",
                        'example' => $i,
                        'validation' => ['required', 'integer', 'min:0', 'max:1000000'],
                        'minimum' => 0,
                        'maximum' => 1000000
                    ];
                    break;
                case 2:
                    $parameters[] = [
                        'name' => "email_field_{$i}",
                        'in' => 'body',
                        'required' => true,
                        'type' => 'string',
                        'format' => 'email',
                        'description' => "Email Field {$i}",
                        'example' => "user{$i}@example.com",
                        'validation' => ['required', 'email', 'unique:users,email']
                    ];
                    break;
                case 3:
                    $parameters[] = [
                        'name' => "date_field_{$i}",
                        'in' => 'body',
                        'required' => true,
                        'type' => 'string',
                        'format' => 'date',
                        'description' => "Date Field {$i}",
                        'example' => '2024-01-01',
                        'validation' => ['required', 'date', 'after:today']
                    ];
                    break;
                case 4:
                    $parameters[] = [
                        'name' => "boolean_field_{$i}",
                        'in' => 'body',
                        'required' => true,
                        'type' => 'boolean',
                        'description' => "Boolean Field {$i}",
                        'example' => true,
                        'validation' => ['required', 'boolean']
                    ];
                    break;
                case 5:
                    $parameters[] = [
                        'name' => "array_field_{$i}",
                        'in' => 'body',
                        'required' => true,
                        'type' => 'array',
                        'description' => "Array Field {$i}",
                        'example' => [],
                        'validation' => ['required', 'array', 'min:1']
                    ];
                    break;
                case 6:
                    $parameters[] = [
                        'name' => "numeric_field_{$i}",
                        'in' => 'body',
                        'required' => true,
                        'type' => 'number',
                        'description' => "Numeric Field {$i}",
                        'example' => 19.99,
                        'validation' => ['required', 'numeric', 'between:0,99.99'],
                        'minimum' => 0,
                        'maximum' => 99.99
                    ];
                    break;
                case 7:
                    $parameters[] = [
                        'name' => "url_field_{$i}",
                        'in' => 'body',
                        'required' => true,
                        'type' => 'string',
                        'format' => 'url',
                        'description' => "URL Field {$i}",
                        'example' => "https://example.com/{$i}",
                        'validation' => ['required', 'url']
                    ];
                    break;
                case 8:
                    $parameters[] = [
                        'name' => "json_field_{$i}",
                        'in' => 'body',
                        'required' => true,
                        'type' => 'object',
                        'description' => "JSON Field {$i}",
                        'example' => ['key' => 'value'],
                        'validation' => ['required', 'json']
                    ];
                    break;
                case 9:
                    $parameters[] = [
                        'name' => "optional_field_{$i}",
                        'in' => 'body',
                        'required' => false,
                        'type' => 'string',
                        'description' => "Optional Field {$i}",
                        'example' => null,
                        'validation' => ['sometimes', 'nullable', 'string']
                    ];
                    break;
            }
        }

        return $parameters;
    }

    /**
     * Verify schema properties are correctly generated
     */
    private function verifySchemaProperties(array $schema): void
    {
        $this->assertArrayHasKey('type', $schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        
        // Check a sample of properties
        foreach ($schema['properties'] as $name => $property) {
            $this->assertArrayHasKey('type', $property);
            $this->assertNotEmpty($property['type']);
            
            // Break after checking 10 properties for performance
            static $checked = 0;
            if (++$checked >= 10) {
                break;
            }
        }
        
        // Check required fields
        if (isset($schema['required'])) {
            $this->assertIsArray($schema['required']);
            $this->assertNotEmpty($schema['required']);
        }
    }

    /**
     * Count properties in nested schema
     */
    private function countSchemaProperties(array $properties): int
    {
        $count = count($properties);
        
        foreach ($properties as $property) {
            if (isset($property['properties'])) {
                $count += $this->countSchemaProperties($property['properties']);
            }
        }
        
        return $count;
    }

    /**
     * Log performance metrics
     */
    private function logPerformanceMetrics(
        int $fieldCount, 
        float $executionTime, 
        float $memoryUsedMB,
        string $testType = 'standard'
    ): void {
        fwrite(STDOUT, "\n");
        fwrite(STDOUT, "=== Performance Metrics ({$testType}) ===\n");
        fwrite(STDOUT, "Fields processed: {$fieldCount}\n");
        fwrite(STDOUT, "Execution time: " . round($executionTime, 3) . " seconds\n");
        fwrite(STDOUT, "Memory used: " . round($memoryUsedMB, 2) . " MB\n");
        fwrite(STDOUT, "Fields per second: " . round($fieldCount / $executionTime, 2) . "\n");
        fwrite(STDOUT, "Memory per field: " . round($memoryUsedMB / $fieldCount * 1024, 2) . " KB\n");
        fwrite(STDOUT, "=====================================\n");
    }
}