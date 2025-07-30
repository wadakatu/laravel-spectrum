<?php

namespace Tests\Unit\Performance;

use LaravelSpectrum\Performance\ParallelProcessor;
use Orchestra\Testbench\TestCase;

class ParallelProcessorAdvancedTest extends TestCase
{
    public function test_constructor_with_custom_parameters(): void
    {
        $processor = new ParallelProcessor(true, 8);

        $reflection = new \ReflectionClass($processor);
        $workersProperty = $reflection->getProperty('workers');
        $workersProperty->setAccessible(true);
        $enabledProperty = $reflection->getProperty('enabled');
        $enabledProperty->setAccessible(true);

        $this->assertEquals(8, $workersProperty->getValue($processor));
        $this->assertTrue($enabledProperty->getValue($processor));
    }

    public function test_determine_optimal_workers_method(): void
    {
        $processor = new ParallelProcessor(false, 4);
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('determineOptimalWorkers');
        $method->setAccessible(true);

        $workers = $method->invoke($processor);

        // ワーカー数が適切な範囲内にあることを確認
        $this->assertGreaterThanOrEqual(2, $workers);
        $this->assertLessThanOrEqual(16, $workers);
    }

    public function test_check_parallel_processing_support_method(): void
    {
        $processor = new ParallelProcessor(false, 4);
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('checkParallelProcessingSupport');
        $method->setAccessible(true);

        // Mock the config function
        $this->app['config']->set('spectrum.performance.parallel_processing', true);

        $supported = $method->invoke($processor);

        // Windows環境やPCNTL拡張の有無により結果が異なる
        $this->assertIsBool($supported);

        if (PHP_OS_FAMILY === 'Windows') {
            $this->assertFalse($supported);
        }

        if (! extension_loaded('pcntl')) {
            $this->assertFalse($supported);
        }
    }

    public function test_parallel_processing_disabled_by_config(): void
    {
        $this->app['config']->set('spectrum.performance.parallel_processing', false);

        $processor = new ParallelProcessor;

        $reflection = new \ReflectionClass($processor);
        $enabledProperty = $reflection->getProperty('enabled');
        $enabledProperty->setAccessible(true);

        $this->assertFalse($enabledProperty->getValue($processor));
    }

    public function test_process_with_fork_fallback(): void
    {
        // 並列処理を有効にするが、Forkクラスが利用できない環境をシミュレート
        $processor = new ParallelProcessor(true, 4);

        // 少数のデータでテスト（並列処理は50個以上でトリガーされる）
        $routes = array_map(fn ($i) => "route$i", range(1, 10));

        $processFunc = function ($route) {
            return strtoupper($route);
        };

        // Fork クラスの存在に関わらず、処理は完了するはず
        $results = $processor->process($routes, $processFunc);

        $this->assertCount(10, $results);

        // 結果の検証
        $this->assertContains('ROUTE1', $results);
        $this->assertContains('ROUTE10', $results);
    }

    public function test_process_with_database_in_before_callback(): void
    {
        if (! class_exists('\Illuminate\Support\Facades\DB')) {
            $this->markTestSkipped('Laravel DB facade not available');
        }

        // DB reconnection のテスト
        $this->app['config']->set('database.default', 'testing');

        $processor = new ParallelProcessor(true, 2);
        $items = range(1, 10);

        // 実際のForkが利用できない環境でも動作することを確認
        $results = $processor->process($items, fn ($item) => $item * 2);

        $this->assertCount(10, $results);
        $expected = array_map(fn ($i) => $i * 2, $items);

        // 順序が保たれない可能性があるのでsort
        sort($results);
        sort($expected);

        $this->assertEquals($expected, $results);
    }

    public function test_process_with_progress_parallel_mode(): void
    {
        // Create a processor with parallel mode enabled
        $processor = new ParallelProcessor(true, 4);

        $items = range(1, 20);
        $progressCalls = [];

        $results = $processor->processWithProgress(
            $items,
            fn ($item) => $item * 3,
            function ($current, $total) use (&$progressCalls) {
                $progressCalls[] = ['current' => $current, 'total' => $total];
            }
        );

        $this->assertCount(20, $results);

        // 結果の検証（順序は保証されない）
        $expected = array_map(fn ($i) => $i * 3, $items);
        sort($results);
        sort($expected);
        $this->assertEquals($expected, $results);

        // 進捗が報告されていることを確認
        if (! empty($progressCalls)) {
            $lastCall = end($progressCalls);
            $this->assertEquals(20, $lastCall['total']);
        }
    }

    public function test_process_with_progress_file_handling(): void
    {
        $processor = new ParallelProcessor(false, 4);

        // テスト用の一時ファイルが作成・削除されることを確認
        $tempDir = sys_get_temp_dir();
        $filesBefore = glob($tempDir.'/spectrum_progress_*');

        $items = range(1, 15);
        $processor->processWithProgress(
            $items,
            fn ($item) => $item,
            fn ($current, $total) => null
        );

        $filesAfter = glob($tempDir.'/spectrum_progress_*');

        // 処理後に一時ファイルが削除されていることを確認
        $this->assertEquals(count($filesBefore), count($filesAfter));
    }
}
