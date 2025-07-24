<?php

namespace LaravelSpectrum\Tests\Performance;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Tests\Fixtures\Controllers\UserController;
use LaravelSpectrum\Tests\TestCase;

class BenchmarkTest extends TestCase
{
    public function test_large_scale_performance()
    {
        // 2000ルートを生成
        for ($i = 0; $i < 2000; $i++) {
            Route::get("api/test{$i}", [UserController::class, 'index']);
        }

        // 通常の生成
        $normalStart = microtime(true);
        $this->artisan('spectrum:generate', ['--no-cache' => true]);
        $normalTime = microtime(true) - $normalStart;

        // 最適化版の生成
        $optimizedStart = microtime(true);
        $this->artisan('spectrum:generate:optimized', [
            '--parallel' => true,
            '--chunk-size' => 100,
        ]);
        $optimizedTime = microtime(true) - $optimizedStart;

        // 改善率を計算
        $improvement = (($normalTime - $optimizedTime) / $normalTime) * 100;

        $this->info("Normal generation: {$normalTime}s");
        $this->info("Optimized generation: {$optimizedTime}s");
        $this->info("Improvement: {$improvement}%");

        // 50%以上の改善を期待
        $this->assertGreaterThan(50, $improvement);
    }

    /**
     * Test memory efficiency with large datasets
     */
    public function test_memory_efficiency()
    {
        // 1000ルートを生成
        for ($i = 0; $i < 1000; $i++) {
            Route::get("api/memory-test{$i}", [UserController::class, 'index']);
        }

        $startMemory = memory_get_usage(true);

        $this->artisan('spectrum:generate:optimized', [
            '--parallel' => false, // シングルスレッドでメモリ効率をテスト
            '--chunk-size' => 50,
        ]);

        $peakMemory = memory_get_peak_usage(true) - $startMemory;
        $peakMemoryMB = $peakMemory / 1024 / 1024;

        $this->info("Peak memory usage: {$peakMemoryMB} MB");

        // メモリ使用量が500MB以下であることを確認
        $this->assertLessThan(500, $peakMemoryMB);
    }

    /**
     * Test incremental generation performance
     */
    public function test_incremental_generation()
    {
        // 500ルートを生成
        for ($i = 0; $i < 500; $i++) {
            Route::get("api/incremental{$i}", [UserController::class, 'index']);
        }

        // 初回生成
        $this->artisan('spectrum:generate:optimized');

        // ファイル変更をシミュレート
        touch(app_path('Http/Controllers/UserController.php'));

        // インクリメンタル生成
        $incrementalStart = microtime(true);
        $this->artisan('spectrum:generate:optimized', [
            '--incremental' => true,
        ]);
        $incrementalTime = microtime(true) - $incrementalStart;

        $this->info("Incremental generation: {$incrementalTime}s");

        // インクリメンタル生成が5秒以内に完了することを確認
        $this->assertLessThan(5, $incrementalTime);
    }

    /**
     * Helper method to output info during tests
     */
    protected function info(string $message): void
    {
        if ($this->getName() === null) {
            return;
        }

        fwrite(STDOUT, $message.PHP_EOL);
    }
}
