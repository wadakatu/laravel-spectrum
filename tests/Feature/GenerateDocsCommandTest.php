<?php

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Tests\Fixtures\Controllers\UserController;
use LaravelSpectrum\Tests\TestCase;

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
        // テスト後のクリーンアップ
        if (File::exists(storage_path('app/spectrum'))) {
            File::deleteDirectory(storage_path('app/spectrum'));
        }

        // Clear cache after test
        app(DocumentationCache::class)->clear();

        parent::tearDown();
    }

    /** @test */
    public function it_generates_openapi_documentation()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);
        Route::post('api/users', [UserController::class, 'store']);

        // Act
        $this->artisan('spectrum:generate')
            ->expectsOutput('🔍 Analyzing routes...')
            ->expectsOutput('Found 2 API routes')
            ->expectsOutput('📝 Generating OpenAPI specification...')
            ->assertSuccessful();

        // Assert
        $this->assertFileExists(storage_path('app/spectrum/openapi.json'));

        $content = File::get(storage_path('app/spectrum/openapi.json'));
        $openapi = json_decode($content, true);

        $this->assertEquals('3.0.0', $openapi['openapi']);
        $this->assertArrayHasKey('/api/users', $openapi['paths']);
    }

    /** @test */
    public function it_clears_cache_when_clear_cache_option_is_specified()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Create some cache
        app(DocumentationCache::class)->remember('test_key', fn () => 'test_data');

        // Act
        $this->artisan('spectrum:generate --clear-cache')
            ->expectsOutput('🧹 Clearing cache...')
            ->expectsOutput('🔍 Analyzing routes...')
            ->assertSuccessful();

        // Assert
        $stats = app(DocumentationCache::class)->getStats();
        $this->assertEquals(1, $stats['total_files']); // Only the newly created cache
    }

    /** @test */
    public function it_disables_cache_when_no_cache_option_is_specified()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $this->artisan('spectrum:generate --no-cache')
            ->expectsOutput('🔍 Analyzing routes...')
            ->doesntExpectOutput('💾 Cache:')
            ->assertSuccessful();
    }

    /** @test */
    public function it_generates_yaml_format_when_specified()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $this->artisan('spectrum:generate', ['--format' => 'yaml'])
            ->assertSuccessful();

        // Assert
        $this->assertFileExists(storage_path('app/spectrum/openapi.yaml'));

        $content = File::get(storage_path('app/spectrum/openapi.yaml'));
        $this->assertStringContainsString('openapi: 3.0.0', $content);
    }

    /** @test */
    public function it_uses_custom_output_path_when_specified()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);
        $customPath = storage_path('custom/api-docs.json');

        // Act
        $this->artisan('spectrum:generate', ['--output' => $customPath])
            ->assertSuccessful();

        // Assert
        $this->assertFileExists($customPath);

        // Cleanup
        File::delete($customPath);
        File::deleteDirectory(storage_path('custom'));
    }

    /** @test */
    public function it_shows_warning_when_no_routes_found()
    {
        // Arrange - No routes configured

        // Act
        $this->artisan('spectrum:generate')
            ->expectsOutput('No API routes found. Make sure your routes match the patterns in config/spectrum.php')
            ->assertFailed();
    }
}
