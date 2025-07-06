<?php

namespace LaravelPrism\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelPrism\Cache\DocumentationCache;
use LaravelPrism\Tests\Fixtures\Controllers\UserController;
use LaravelPrism\Tests\TestCase;

class CacheCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before test
        app(DocumentationCache::class)->clear();
    }

    protected function tearDown(): void
    {
        // Clear cache after test
        app(DocumentationCache::class)->clear();

        parent::tearDown();
    }

    /** @test */
    public function it_can_clear_cache()
    {
        // Arrange - Create some cache
        $cache = app(DocumentationCache::class);
        $cache->remember('test_key', fn () => 'test_data');

        // Act
        $this->artisan('prism:cache clear')
            ->expectsOutput('🧹 Clearing cache...')
            ->expectsOutput('✅ Cache cleared successfully')
            ->assertSuccessful();

        // Assert
        $stats = $cache->getStats();
        $this->assertEquals(0, $stats['total_files']);
    }

    /** @test */
    public function it_can_show_cache_statistics()
    {
        // Arrange - Create some cache
        $cache = app(DocumentationCache::class);
        $cache->remember('key1', fn () => 'data1');
        $cache->remember('key2', fn () => 'data2');

        // Act
        $this->artisan('prism:cache stats')
            ->expectsOutput('📊 Cache Statistics')
            ->expectsOutput('==================')
            ->expectsOutput('Status: Enabled')
            ->expectsOutput('Files: 2')
            ->assertSuccessful();
    }

    /** @test */
    public function it_can_warm_cache()
    {
        // Arrange
        Route::get('api/users', [UserController::class, 'index']);

        // Act
        $this->artisan('prism:cache warm')
            ->expectsOutput('🔥 Warming cache...')
            ->expectsOutputToContain('✅ Cache warmed:')
            ->assertSuccessful();

        // Assert
        $stats = app(DocumentationCache::class)->getStats();
        $this->assertGreaterThan(0, $stats['total_files']);
    }

    /** @test */
    public function it_shows_error_for_invalid_action()
    {
        $this->artisan('prism:cache invalid')
            ->expectsOutput('Invalid action. Use: clear, stats, or warm')
            ->assertFailed();
    }
}
