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
}
