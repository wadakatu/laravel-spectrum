<?php

namespace LaravelSpectrum\Tests\Performance;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Analyzers\RouteAnalyzer;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Tests\Fixtures\Controllers\UserController;
use LaravelSpectrum\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CachePerformanceTest extends TestCase
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

    #[Test]
    public function it_improves_generation_performance()
    {
        // 大量のルートを作成
        for ($i = 0; $i < 50; $i++) {
            Route::get("api/test{$i}", [UserController::class, 'index']);
            Route::post("api/test{$i}", [UserController::class, 'store']);
        }

        // キャッシュなしの時間を計測
        config(['spectrum.cache.enabled' => false]);
        $analyzer = app(RouteAnalyzer::class);

        $startTime = microtime(true);
        $analyzer->analyze();
        $withoutCache = microtime(true) - $startTime;

        // キャッシュありの時間を計測
        config(['spectrum.cache.enabled' => true]);
        app()->forgetInstance(RouteAnalyzer::class);
        $analyzer = app(RouteAnalyzer::class);

        $analyzer->analyze(); // 1回目（キャッシュ作成）

        $startTime = microtime(true);
        $analyzer->analyze(); // 2回目（キャッシュ使用）
        $withCache = microtime(true) - $startTime;

        // キャッシュありの方が高速であることを確認
        $this->assertLessThan($withoutCache, $withCache);

        // 50%以上の高速化を確認
        $improvement = round((1 - $withCache / $withoutCache) * 100, 2);
        $this->assertGreaterThan(50, $improvement);

        $this->info("Performance improvement: {$improvement}%");
        $this->info("Without cache: {$withoutCache}s");
        $this->info("With cache: {$withCache}s");
    }

    private function info(string $message): void
    {
        fwrite(STDOUT, $message.PHP_EOL);
    }
}
