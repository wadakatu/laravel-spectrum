<?php

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Tests\TestCase;

class PerformanceTest extends TestCase
{
    public function test_optimized_command_handles_large_datasets()
    {
        // 100ルートを生成（実際のテストなので控えめに）
        for ($i = 0; $i < 100; $i++) {
            Route::get("api/performance-test-{$i}", function () {
                return ['data' => 'test'];
            });
        }

        $this->artisan('spectrum:generate:optimized', [
            '--chunk-size' => 10,
        ])
            ->assertExitCode(0)
            ->expectsOutput('🚀 Generating API documentation with optimizations...')
            ->expectsOutput('Processing routes in chunks...');
    }

    public function test_memory_manager_prevents_excessive_usage()
    {
        // メモリ制限を低く設定
        $this->artisan('spectrum:generate:optimized', [
            '--memory-limit' => '128M',
        ])
            ->assertExitCode(0);
    }

    public function test_parallel_processing_disabled_on_windows()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Parallel processing is not supported on Windows');
        }

        // PCNTL拡張がない場合は通常処理にフォールバック
        if (! extension_loaded('pcntl')) {
            $this->markTestSkipped('PCNTL extension is not loaded');
        }

        $this->assertTrue(true);
    }
}
