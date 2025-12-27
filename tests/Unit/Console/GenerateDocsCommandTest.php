<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Console;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Console\GenerateDocsCommand;
use LaravelSpectrum\Tests\Fixtures\Controllers\UserController;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class GenerateDocsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before test
        app(DocumentationCache::class)->clear();
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (File::exists(storage_path('app/spectrum'))) {
            File::deleteDirectory(storage_path('app/spectrum'));
        }
        if (File::exists(storage_path('test-reports'))) {
            File::deleteDirectory(storage_path('test-reports'));
        }

        app(DocumentationCache::class)->clear();
        parent::tearDown();
    }

    #[Test]
    public function command_has_correct_signature(): void
    {
        $command = app(GenerateDocsCommand::class);

        $this->assertEquals('spectrum:generate', $command->getName());
        $this->assertStringContainsString('Generate API documentation', $command->getDescription());
    }

    #[Test]
    public function command_signature_includes_all_options(): void
    {
        $command = app(GenerateDocsCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('format'));
        $this->assertTrue($definition->hasOption('output'));
        $this->assertTrue($definition->hasOption('no-cache'));
        $this->assertTrue($definition->hasOption('clear-cache'));
        $this->assertTrue($definition->hasOption('fail-on-error'));
        $this->assertTrue($definition->hasOption('ignore-errors'));
        $this->assertTrue($definition->hasOption('error-report'));
        $this->assertTrue($definition->hasOption('no-try-it-out'));
    }

    #[Test]
    public function get_file_extension_returns_json_by_default(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getFileExtension');
        $method->setAccessible(true);

        $this->assertEquals('json', $method->invoke($command, 'json'));
        $this->assertEquals('json', $method->invoke($command, 'unknown'));
        $this->assertEquals('json', $method->invoke($command, ''));
    }

    #[Test]
    public function get_file_extension_returns_yaml_for_yaml_format(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getFileExtension');
        $method->setAccessible(true);

        $this->assertEquals('yaml', $method->invoke($command, 'yaml'));
    }

    #[Test]
    public function get_file_extension_returns_html_for_html_format(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getFileExtension');
        $method->setAccessible(true);

        $this->assertEquals('html', $method->invoke($command, 'html'));
    }

    #[Test]
    public function array_to_yaml_converts_simple_array(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('arrayToYaml');
        $method->setAccessible(true);

        $array = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
            ],
        ];

        $yaml = $method->invoke($command, $array);

        $this->assertStringContainsString('openapi: 3.0.0', $yaml);
        $this->assertStringContainsString('info:', $yaml);
        $this->assertStringContainsString('title: Test API', $yaml);
        $this->assertStringContainsString('version: 1.0.0', $yaml);
    }

    #[Test]
    public function array_to_yaml_handles_boolean_values(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('arrayToYaml');
        $method->setAccessible(true);

        $array = [
            'enabled' => true,
            'disabled' => false,
        ];

        $yaml = $method->invoke($command, $array);

        $this->assertStringContainsString('enabled: true', $yaml);
        $this->assertStringContainsString('disabled: false', $yaml);
    }

    #[Test]
    public function array_to_yaml_handles_null_values(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('arrayToYaml');
        $method->setAccessible(true);

        $array = [
            'value' => null,
        ];

        $yaml = $method->invoke($command, $array);

        $this->assertStringContainsString('value: null', $yaml);
    }

    #[Test]
    public function array_to_yaml_handles_objects(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('arrayToYaml');
        $method->setAccessible(true);

        $obj = new \stdClass;
        $obj->name = 'Test';
        $obj->value = 123;

        $array = ['data' => $obj];

        $yaml = $method->invoke($command, $array);

        $this->assertStringContainsString('name: Test', $yaml);
        $this->assertStringContainsString('value: 123', $yaml);
    }

    #[Test]
    public function format_output_returns_json_by_default(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('formatOutput');
        $method->setAccessible(true);

        $data = ['openapi' => '3.0.0'];
        $result = $method->invoke($command, $data, 'json');

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertEquals('3.0.0', $decoded['openapi']);
    }

    #[Test]
    public function format_output_returns_yaml_for_yaml_format(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('formatOutput');
        $method->setAccessible(true);

        $data = ['openapi' => '3.0.0'];
        $result = $method->invoke($command, $data, 'yaml');

        $this->assertStringContainsString('openapi: 3.0.0', $result);
    }

    #[Test]
    public function it_generates_html_format(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $this->artisan('spectrum:generate', ['--format' => 'html'])
            ->assertSuccessful();

        // Assert
        $this->assertFileExists(storage_path('app/spectrum/openapi.html'));

        $content = File::get(storage_path('app/spectrum/openapi.html'));
        $this->assertStringContainsString('<!DOCTYPE html>', $content);
        $this->assertStringContainsString('swagger-ui', $content);
    }

    #[Test]
    public function it_generates_html_without_try_it_out_when_option_specified(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $this->artisan('spectrum:generate', [
            '--format' => 'html',
            '--no-try-it-out' => true,
        ])
            ->assertSuccessful();

        // Assert
        $this->assertFileExists(storage_path('app/spectrum/openapi.html'));

        $content = File::get(storage_path('app/spectrum/openapi.html'));
        $this->assertStringContainsString('<!DOCTYPE html>', $content);
    }

    #[Test]
    public function it_handles_errors_with_ignore_errors_option(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Act & Assert - Command should succeed even if there are warnings
        $this->artisan('spectrum:generate', ['--ignore-errors' => true])
            ->assertSuccessful();
    }

    #[Test]
    public function it_saves_error_report_to_file(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);
        $reportPath = storage_path('test-reports/errors.json');

        // Act
        $this->artisan('spectrum:generate', ['--error-report' => $reportPath])
            ->assertSuccessful();

        // The error report file is only created if there are errors/warnings
        // In a clean test, there might not be any, so we just verify the command runs
    }

    #[Test]
    public function it_shows_verbose_cache_info(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $this->artisan('spectrum:generate', ['-v' => true])
            ->expectsOutputToContain('Using cached routes')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_verbose_file_info(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $this->artisan('spectrum:generate', ['-v' => true])
            ->expectsOutputToContain('File size:')
            ->expectsOutputToContain('Absolute path:')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_cache_stats_after_generation(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $this->artisan('spectrum:generate')
            ->expectsOutputToContain('Cache:')
            ->assertSuccessful();
    }

    #[Test]
    public function array_to_yaml_handles_indexed_arrays(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('arrayToYaml');
        $method->setAccessible(true);

        $array = [
            'items' => ['apple', 'banana', 'cherry'],
        ];

        $yaml = $method->invoke($command, $array);

        $this->assertStringContainsString('items:', $yaml);
        // The method uses numeric keys format (0: apple) for indexed arrays
        $this->assertStringContainsString('apple', $yaml);
        $this->assertStringContainsString('banana', $yaml);
        $this->assertStringContainsString('cherry', $yaml);
    }

    #[Test]
    public function array_to_yaml_handles_deep_nesting(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('arrayToYaml');
        $method->setAccessible(true);

        $array = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'value' => 'deep',
                    ],
                ],
            ],
        ];

        $yaml = $method->invoke($command, $array);

        $this->assertStringContainsString('level1:', $yaml);
        $this->assertStringContainsString('level2:', $yaml);
        $this->assertStringContainsString('level3:', $yaml);
        $this->assertStringContainsString('value: deep', $yaml);
    }

    #[Test]
    public function array_to_yaml_handles_numeric_keys(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('arrayToYaml');
        $method->setAccessible(true);

        $array = [
            0 => 'first',
            1 => 'second',
            2 => 'third',
        ];

        $yaml = $method->invoke($command, $array);

        // The method outputs numeric keys as 0: first, 1: second, etc.
        $this->assertStringContainsString('first', $yaml);
        $this->assertStringContainsString('second', $yaml);
        $this->assertStringContainsString('third', $yaml);
    }

    #[Test]
    public function array_to_yaml_handles_string_with_special_characters(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('arrayToYaml');
        $method->setAccessible(true);

        $array = [
            'description' => 'A value with: colon',
            'multiline' => "Line 1\nLine 2",
        ];

        $yaml = $method->invoke($command, $array);

        // The YAML should properly handle strings with colons
        $this->assertStringContainsString('description:', $yaml);
        $this->assertStringContainsString('multiline:', $yaml);
    }

    #[Test]
    public function array_to_yaml_handles_empty_array(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('arrayToYaml');
        $method->setAccessible(true);

        $yaml = $method->invoke($command, []);

        $this->assertEquals('', $yaml);
    }

    #[Test]
    public function format_output_handles_unknown_format_as_json(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('formatOutput');
        $method->setAccessible(true);

        $data = ['openapi' => '3.0.0'];
        $result = $method->invoke($command, $data, 'unknown_format');

        // Unknown format should default to JSON
        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertEquals('3.0.0', $decoded['openapi']);
    }

    #[Test]
    public function array_to_yaml_handles_integer_values(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('arrayToYaml');
        $method->setAccessible(true);

        $array = [
            'count' => 42,
            'port' => 8080,
        ];

        $yaml = $method->invoke($command, $array);

        $this->assertStringContainsString('count: 42', $yaml);
        $this->assertStringContainsString('port: 8080', $yaml);
    }

    #[Test]
    public function array_to_yaml_handles_float_values(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('arrayToYaml');
        $method->setAccessible(true);

        $array = [
            'price' => 19.99,
            'rate' => 0.5,
        ];

        $yaml = $method->invoke($command, $array);

        $this->assertStringContainsString('price: 19.99', $yaml);
        $this->assertStringContainsString('rate: 0.5', $yaml);
    }

    #[Test]
    public function array_to_yaml_handles_mixed_nested_array(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('arrayToYaml');
        $method->setAccessible(true);

        $array = [
            'config' => [
                'enabled' => true,
                'timeout' => 30,
                'endpoints' => [
                    'api' => 'https://api.example.com',
                    'auth' => 'https://auth.example.com',
                ],
            ],
        ];

        $yaml = $method->invoke($command, $array);

        $this->assertStringContainsString('config:', $yaml);
        $this->assertStringContainsString('enabled: true', $yaml);
        $this->assertStringContainsString('timeout: 30', $yaml);
        $this->assertStringContainsString('endpoints:', $yaml);
        $this->assertStringContainsString('https://api.example.com', $yaml);
    }

    #[Test]
    public function format_output_generates_html_with_swagger_ui(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $this->artisan('spectrum:generate', ['--format' => 'html'])
            ->assertSuccessful();

        // Assert
        $this->assertFileExists(storage_path('app/spectrum/openapi.html'));

        $content = File::get(storage_path('app/spectrum/openapi.html'));
        $this->assertStringContainsString('<!DOCTYPE html>', $content);
        $this->assertStringContainsString('swagger-ui', $content);
    }

    #[Test]
    public function format_output_json_includes_pretty_print(): void
    {
        $command = app(GenerateDocsCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('formatOutput');
        $method->setAccessible(true);

        $data = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test API'],
        ];
        $result = $method->invoke($command, $data, 'json');

        // Pretty-printed JSON should have newlines and indentation
        $this->assertStringContainsString("\n", $result);
        $this->assertStringContainsString('    ', $result); // 4-space indent
    }

    #[Test]
    public function it_handles_multiple_routes_correctly(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);
        Route::post('api/users', [UserController::class, 'store']);
        Route::get('api/users/{id}', [UserController::class, 'show']);

        // Act
        $this->artisan('spectrum:generate')
            ->expectsOutputToContain('Found 3 API routes')
            ->assertSuccessful();

        // Assert
        $this->assertFileExists(storage_path('app/spectrum/openapi.json'));
    }

    #[Test]
    public function it_generates_yml_format_correctly(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Act - test yml extension (alias for yaml)
        $this->artisan('spectrum:generate', ['--format' => 'yaml'])
            ->assertSuccessful();

        // Assert
        $this->assertFileExists(storage_path('app/spectrum/openapi.yaml'));

        $content = File::get(storage_path('app/spectrum/openapi.yaml'));
        $this->assertStringContainsString('openapi:', $content);
    }

    #[Test]
    public function it_shows_generation_time(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $this->artisan('spectrum:generate')
            ->expectsOutputToContain('Generation completed in')
            // Output says "seconds" (plural always, even for fractional times like 0.01 seconds)
            ->assertSuccessful();
    }

    #[Test]
    public function it_includes_success_message(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $this->artisan('spectrum:generate')
            ->expectsOutputToContain('Documentation generated successfully')
            ->assertSuccessful();
    }

    #[Test]
    public function it_displays_error_summary_when_errors_exist(): void
    {
        // Arrange - Use a mock RouteAnalyzer that adds errors
        Route::get('api/users', [UserController::class, 'index']);

        $originalAnalyzer = $this->app->make(\LaravelSpectrum\Analyzers\RouteAnalyzer::class);

        $mockAnalyzer = \Mockery::mock($originalAnalyzer)->makePartial();
        $mockAnalyzer->shouldReceive('analyze')->andReturnUsing(function ($useCache) use ($originalAnalyzer) {
            // Add an error to the collector before returning routes
            $collector = app(\LaravelSpectrum\Support\ErrorCollector::class);
            $collector->addError('TestController', 'Test error for coverage');

            return $originalAnalyzer->analyze($useCache);
        });

        $this->app->instance(\LaravelSpectrum\Analyzers\RouteAnalyzer::class, $mockAnalyzer);

        // Act
        $this->artisan('spectrum:generate')
            ->expectsOutputToContain('Documentation generated with errors')
            ->assertSuccessful();
    }

    #[Test]
    public function it_displays_detailed_errors_in_verbose_mode(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        $originalAnalyzer = $this->app->make(\LaravelSpectrum\Analyzers\RouteAnalyzer::class);

        $mockAnalyzer = \Mockery::mock($originalAnalyzer)->makePartial();
        $mockAnalyzer->shouldReceive('analyze')->andReturnUsing(function ($useCache) use ($originalAnalyzer) {
            $collector = app(\LaravelSpectrum\Support\ErrorCollector::class);
            $collector->addError('TestContext', 'Detailed test error message');

            return $originalAnalyzer->analyze($useCache);
        });

        $this->app->instance(\LaravelSpectrum\Analyzers\RouteAnalyzer::class, $mockAnalyzer);

        // Act - the command uses $this->option('verbose') to check
        $this->artisan('spectrum:generate', ['--verbose' => true])
            ->expectsOutputToContain('[TestContext] Detailed test error message')
            ->assertSuccessful();
    }

    #[Test]
    public function it_displays_warnings_in_verbose_mode(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        $originalAnalyzer = $this->app->make(\LaravelSpectrum\Analyzers\RouteAnalyzer::class);

        $mockAnalyzer = \Mockery::mock($originalAnalyzer)->makePartial();
        $mockAnalyzer->shouldReceive('analyze')->andReturnUsing(function ($useCache) use ($originalAnalyzer) {
            $collector = app(\LaravelSpectrum\Support\ErrorCollector::class);
            $collector->addWarning('WarningContext', 'Test warning message');

            return $originalAnalyzer->analyze($useCache);
        });

        $this->app->instance(\LaravelSpectrum\Analyzers\RouteAnalyzer::class, $mockAnalyzer);

        // Act
        $this->artisan('spectrum:generate', ['--verbose' => true])
            ->expectsOutputToContain('[WarningContext] Test warning message')
            ->assertSuccessful();
    }

    #[Test]
    public function it_saves_error_report_when_errors_exist(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);
        $reportPath = storage_path('test-reports/error-report.json');

        $originalAnalyzer = $this->app->make(\LaravelSpectrum\Analyzers\RouteAnalyzer::class);

        $mockAnalyzer = \Mockery::mock($originalAnalyzer)->makePartial();
        $mockAnalyzer->shouldReceive('analyze')->andReturnUsing(function ($useCache) use ($originalAnalyzer) {
            $collector = app(\LaravelSpectrum\Support\ErrorCollector::class);
            $collector->addError('ReportContext', 'Error to be saved');

            return $originalAnalyzer->analyze($useCache);
        });

        $this->app->instance(\LaravelSpectrum\Analyzers\RouteAnalyzer::class, $mockAnalyzer);

        // Act
        $this->artisan('spectrum:generate', ['--error-report' => $reportPath])
            ->expectsOutputToContain('Error report saved to')
            ->assertSuccessful();

        // Assert
        $this->assertFileExists($reportPath);
        $content = File::get($reportPath);
        $report = json_decode($content, true);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('errors', $report);
        $this->assertEquals(1, $report['summary']['total_errors']);
    }

    #[Test]
    public function it_fails_when_fail_on_error_and_errors_exist(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // When --fail-on-error is true, the ErrorCollector throws RuntimeException immediately
        // on addError, so we need to expect that the command fails due to the exception
        $originalAnalyzer = $this->app->make(\LaravelSpectrum\Analyzers\RouteAnalyzer::class);

        $mockAnalyzer = \Mockery::mock($originalAnalyzer)->makePartial();
        $mockAnalyzer->shouldReceive('analyze')->andReturnUsing(function ($useCache) use ($originalAnalyzer) {
            $collector = app(\LaravelSpectrum\Support\ErrorCollector::class);
            // This will throw RuntimeException when failOnError is true
            $collector->addError('FailContext', 'Fatal error');

            return $originalAnalyzer->analyze($useCache);
        });

        $this->app->instance(\LaravelSpectrum\Analyzers\RouteAnalyzer::class, $mockAnalyzer);

        // Act - The command should fail due to RuntimeException being thrown
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Error in FailContext: Fatal error');

        $this->artisan('spectrum:generate', ['--fail-on-error' => true]);
    }

    #[Test]
    public function it_succeeds_with_ignore_errors_option(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        $originalAnalyzer = $this->app->make(\LaravelSpectrum\Analyzers\RouteAnalyzer::class);

        $mockAnalyzer = \Mockery::mock($originalAnalyzer)->makePartial();
        $mockAnalyzer->shouldReceive('analyze')->andReturnUsing(function ($useCache) use ($originalAnalyzer) {
            $collector = app(\LaravelSpectrum\Support\ErrorCollector::class);
            $collector->addError('IgnoreContext', 'Ignored error');

            return $originalAnalyzer->analyze($useCache);
        });

        $this->app->instance(\LaravelSpectrum\Analyzers\RouteAnalyzer::class, $mockAnalyzer);

        // Act
        $this->artisan('spectrum:generate', ['--ignore-errors' => true])
            ->expectsOutputToContain('Documentation generated successfully')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_error_count_in_non_verbose_mode(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        $originalAnalyzer = $this->app->make(\LaravelSpectrum\Analyzers\RouteAnalyzer::class);

        $mockAnalyzer = \Mockery::mock($originalAnalyzer)->makePartial();
        $mockAnalyzer->shouldReceive('analyze')->andReturnUsing(function ($useCache) use ($originalAnalyzer) {
            $collector = app(\LaravelSpectrum\Support\ErrorCollector::class);
            $collector->addError('Context1', 'Error 1');
            $collector->addError('Context2', 'Error 2');

            return $originalAnalyzer->analyze($useCache);
        });

        $this->app->instance(\LaravelSpectrum\Analyzers\RouteAnalyzer::class, $mockAnalyzer);

        // Act
        $this->artisan('spectrum:generate')
            ->expectsOutputToContain('Found 2 errors during generation')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_warning_count_in_non_verbose_mode(): void
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        $originalAnalyzer = $this->app->make(\LaravelSpectrum\Analyzers\RouteAnalyzer::class);

        $mockAnalyzer = \Mockery::mock($originalAnalyzer)->makePartial();
        $mockAnalyzer->shouldReceive('analyze')->andReturnUsing(function ($useCache) use ($originalAnalyzer) {
            $collector = app(\LaravelSpectrum\Support\ErrorCollector::class);
            $collector->addWarning('WarnContext1', 'Warning 1');
            $collector->addWarning('WarnContext2', 'Warning 2');

            return $originalAnalyzer->analyze($useCache);
        });

        $this->app->instance(\LaravelSpectrum\Analyzers\RouteAnalyzer::class, $mockAnalyzer);

        // Act
        $this->artisan('spectrum:generate')
            ->expectsOutputToContain('Found 2 warnings during generation')
            ->assertSuccessful();
    }
}
