<?php

namespace LaravelPrism\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use LaravelPrism\Tests\Fixtures\Controllers\UserController;
use LaravelPrism\Tests\TestCase;

class GenerateDocsCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        // テスト後のクリーンアップ
        if (File::exists(storage_path('app/prism'))) {
            File::deleteDirectory(storage_path('app/prism'));
        }

        parent::tearDown();
    }

    /** @test */
    public function it_generates_openapi_documentation()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);
        Route::post('api/users', [UserController::class, 'store']);

        // Act
        $this->artisan('prism:generate')
            ->expectsOutput('🔍 Analyzing routes...')
            ->expectsOutput('Found 2 API routes')
            ->expectsOutput('📝 Generating OpenAPI specification...')
            ->assertSuccessful();

        // Assert
        $this->assertFileExists(storage_path('app/prism/openapi.json'));

        $content = File::get(storage_path('app/prism/openapi.json'));
        $openapi = json_decode($content, true);

        $this->assertEquals('3.0.0', $openapi['openapi']);
        $this->assertArrayHasKey('/api/users', $openapi['paths']);
    }

    /** @test */
    public function it_generates_yaml_format_when_specified()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $this->artisan('prism:generate', ['--format' => 'yaml'])
            ->assertSuccessful();

        // Assert
        $this->assertFileExists(storage_path('app/prism/openapi.yaml'));

        $content = File::get(storage_path('app/prism/openapi.yaml'));
        $this->assertStringContainsString('openapi: 3.0.0', $content);
    }

    /** @test */
    public function it_uses_custom_output_path_when_specified()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);
        $customPath = storage_path('custom/api-docs.json');

        // Act
        $this->artisan('prism:generate', ['--output' => $customPath])
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
        $this->artisan('prism:generate')
            ->expectsOutput('No API routes found. Make sure your routes match the patterns in config/prism.php')
            ->assertFailed();
    }
}
