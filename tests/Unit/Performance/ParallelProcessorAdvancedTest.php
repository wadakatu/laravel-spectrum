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

    public function test_constructor_uses_defaults_when_null_passed(): void
    {
        $processor = new ParallelProcessor(null, null);

        $reflection = new \ReflectionClass($processor);
        $workersProperty = $reflection->getProperty('workers');
        $workersProperty->setAccessible(true);
        $enabledProperty = $reflection->getProperty('enabled');
        $enabledProperty->setAccessible(true);

        // Workers should be determined by the optimal workers method
        $workers = $workersProperty->getValue($processor);
        $this->assertGreaterThanOrEqual(2, $workers);
        $this->assertLessThanOrEqual(16, $workers);

        // Enabled should be determined by checkParallelProcessingSupport
        $enabled = $enabledProperty->getValue($processor);
        $this->assertIsBool($enabled);
    }

    public function test_process_with_large_dataset_enabled_but_no_fork(): void
    {
        // Test that large datasets still work when Fork is not available
        $processor = new ParallelProcessor(true, 4);

        // 50+ items to trigger parallel processing path
        $routes = array_map(fn ($i) => "route$i", range(1, 60));

        $results = $processor->process($routes, fn ($route) => strtoupper($route));

        $this->assertCount(60, $results);
        $this->assertContains('ROUTE1', $results);
        $this->assertContains('ROUTE60', $results);
    }

    public function test_set_workers_with_negative_value(): void
    {
        $processor = new ParallelProcessor(false, 4);

        $reflection = new \ReflectionClass($processor);
        $workersProperty = $reflection->getProperty('workers');
        $workersProperty->setAccessible(true);

        // Negative values should be clamped to 1
        $processor->setWorkers(-5);
        $this->assertEquals(1, $workersProperty->getValue($processor));
    }

    public function test_process_with_single_item(): void
    {
        $processor = new ParallelProcessor(true, 4);

        $results = $processor->process(['single'], fn ($x) => strtoupper($x));

        $this->assertCount(1, $results);
        $this->assertEquals('SINGLE', $results[0]);
    }

    public function test_process_with_progress_single_item(): void
    {
        $processor = new ParallelProcessor(false, 4);
        $progressCalls = [];

        $results = $processor->processWithProgress(
            ['single'],
            fn ($x) => strtoupper($x),
            function ($current, $total) use (&$progressCalls) {
                $progressCalls[] = ['current' => $current, 'total' => $total];
            }
        );

        $this->assertCount(1, $results);
        $this->assertEquals('SINGLE', $results[0]);

        // Single item should trigger final progress call
        $this->assertCount(1, $progressCalls);
        $this->assertEquals(1, $progressCalls[0]['current']);
        $this->assertEquals(1, $progressCalls[0]['total']);
    }

    public function test_process_with_null_results(): void
    {
        $processor = new ParallelProcessor(false, 4);

        $items = [1, 2, 3];
        $results = $processor->process($items, fn ($x) => null);

        $this->assertCount(3, $results);
        $this->assertNull($results[0]);
        $this->assertNull($results[1]);
        $this->assertNull($results[2]);
    }

    public function test_check_parallel_processing_support_with_config_enabled(): void
    {
        $this->app['config']->set('spectrum.performance.parallel_processing', true);

        $processor = new ParallelProcessor;

        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('checkParallelProcessingSupport');
        $method->setAccessible(true);

        $result = $method->invoke($processor);

        // Result depends on environment (PCNTL extension, OS)
        $this->assertIsBool($result);

        // On Windows it should always be false
        if (PHP_OS_FAMILY === 'Windows') {
            $this->assertFalse($result);
        }

        // Without PCNTL extension it should be false
        if (! extension_loaded('pcntl')) {
            $this->assertFalse($result);
        }
    }

    public function test_process_with_mixed_data_types(): void
    {
        $processor = new ParallelProcessor(false, 4);

        $items = [1, 'string', ['array'], (object) ['key' => 'value'], null, true];

        $results = $processor->process($items, fn ($x) => gettype($x));

        $this->assertCount(6, $results);
        $this->assertContains('integer', $results);
        $this->assertContains('string', $results);
        $this->assertContains('array', $results);
        $this->assertContains('object', $results);
        $this->assertContains('NULL', $results);
        $this->assertContains('boolean', $results);
    }
}
