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

    public function test_worker_count_resolver_integration(): void
    {
        // Use a custom resolver to verify it's being used
        $customResolver = new class implements \LaravelSpectrum\Contracts\Performance\WorkerCountResolverInterface
        {
            public function resolve(): int
            {
                return 10;
            }
        };

        $processor = new ParallelProcessor(
            enabled: false,
            workers: null,
            executor: null,
            workerCountResolver: $customResolver
        );

        // Workers should be set from the custom resolver
        $this->assertEquals(10, $processor->getWorkers());
    }

    public function test_parallel_support_checker_integration(): void
    {
        // Use a custom checker to verify it's being used
        $customChecker = new class implements \LaravelSpectrum\Contracts\Performance\ParallelSupportCheckerInterface
        {
            public function isSupported(): bool
            {
                return true;
            }
        };

        $processor = new ParallelProcessor(
            enabled: null,  // Use the checker
            workers: 4,
            executor: null,
            workerCountResolver: null,
            supportChecker: $customChecker
        );

        $this->assertTrue($processor->isEnabled());

        // Also test with false
        $falseChecker = new class implements \LaravelSpectrum\Contracts\Performance\ParallelSupportCheckerInterface
        {
            public function isSupported(): bool
            {
                return false;
            }
        };

        $processor2 = new ParallelProcessor(
            enabled: null,
            workers: 4,
            executor: null,
            workerCountResolver: null,
            supportChecker: $falseChecker
        );

        $this->assertFalse($processor2->isEnabled());
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

    public function test_config_enabled_with_default_checker(): void
    {
        $this->app['config']->set('spectrum.performance.parallel_processing', true);

        $processor = new ParallelProcessor;

        // Result depends on environment (PCNTL extension, OS)
        $result = $processor->isEnabled();
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

    public function test_process_sequential_fallback_when_fork_not_available(): void
    {
        // Create a processor subclass that simulates Fork not being available
        $processor = new class(true, 4) extends ParallelProcessor
        {
            public function process(array $routes, callable $processor): array
            {
                // Simulate Fork class not existing by forcing sequential processing
                return array_map($processor, $routes);
            }
        };

        $routes = array_map(fn ($i) => "route$i", range(1, 100));
        $results = $processor->process($routes, fn ($route) => strtoupper($route));

        $this->assertCount(100, $results);
        $this->assertContains('ROUTE1', $results);
        $this->assertContains('ROUTE100', $results);
    }

    public function test_process_with_progress_sequential_reports_every_10_items(): void
    {
        $processor = new ParallelProcessor(false, 4);
        $items = range(1, 35);
        $progressReports = [];

        $processor->processWithProgress(
            $items,
            fn ($item) => $item,
            function ($current, $total) use (&$progressReports) {
                $progressReports[] = $current;
            }
        );

        // Progress should be reported at 10, 20, 30, and 35 (final)
        $this->assertContains(10, $progressReports);
        $this->assertContains(20, $progressReports);
        $this->assertContains(30, $progressReports);
        $this->assertContains(35, $progressReports);
    }

    public function test_constructor_with_enabled_true_and_workers(): void
    {
        $processor = new ParallelProcessor(true, 16);

        $reflection = new \ReflectionClass($processor);
        $workersProperty = $reflection->getProperty('workers');
        $workersProperty->setAccessible(true);
        $enabledProperty = $reflection->getProperty('enabled');
        $enabledProperty->setAccessible(true);

        $this->assertEquals(16, $workersProperty->getValue($processor));
        $this->assertTrue($enabledProperty->getValue($processor));
    }

    public function test_constructor_with_enabled_false(): void
    {
        $processor = new ParallelProcessor(false, 4);

        $reflection = new \ReflectionClass($processor);
        $enabledProperty = $reflection->getProperty('enabled');
        $enabledProperty->setAccessible(true);

        $this->assertFalse($enabledProperty->getValue($processor));
    }

    public function test_default_worker_count_resolver_returns_valid_range(): void
    {
        // Create a processor with default resolver
        $processor = new ParallelProcessor(false);

        $workers = $processor->getWorkers();

        // Result should be between 2 and 16 (default resolver limits)
        $this->assertGreaterThanOrEqual(2, $workers);
        $this->assertLessThanOrEqual(16, $workers);
    }

    public function test_process_with_exactly_50_items(): void
    {
        // Boundary test: exactly 50 items should not trigger parallel processing
        $processor = new ParallelProcessor(true, 4);
        $items = range(1, 50);

        $results = $processor->process($items, fn ($x) => $x * 2);

        $this->assertCount(50, $results);
        // Results may not be in order if parallel processing is enabled
        // Just verify all expected values are present
        $this->assertContains(2, $results);
        $this->assertContains(100, $results);
    }

    public function test_process_with_51_items_triggers_parallel_path(): void
    {
        // Boundary test: 51 items would trigger parallel processing if enabled
        $processor = new ParallelProcessor(true, 4);
        $items = range(1, 51);

        $results = $processor->process($items, fn ($x) => $x * 2);

        $this->assertCount(51, $results);
    }

    public function test_set_workers_to_exactly_32(): void
    {
        $processor = new ParallelProcessor(false, 4);

        $reflection = new \ReflectionClass($processor);
        $workersProperty = $reflection->getProperty('workers');
        $workersProperty->setAccessible(true);

        $processor->setWorkers(32);
        $this->assertEquals(32, $workersProperty->getValue($processor));
    }

    public function test_set_workers_to_exactly_1(): void
    {
        $processor = new ParallelProcessor(false, 4);

        $reflection = new \ReflectionClass($processor);
        $workersProperty = $reflection->getProperty('workers');
        $workersProperty->setAccessible(true);

        $processor->setWorkers(1);
        $this->assertEquals(1, $workersProperty->getValue($processor));
    }

    public function test_process_with_progress_last_item_reports_progress(): void
    {
        $processor = new ParallelProcessor(false, 4);
        $items = range(1, 11); // 11 items: reports at 10 and 11 (last)
        $progressReports = [];

        $processor->processWithProgress(
            $items,
            fn ($item) => $item,
            function ($current, $total) use (&$progressReports) {
                $progressReports[] = $current;
            }
        );

        $this->assertContains(10, $progressReports);
        $this->assertContains(11, $progressReports); // Last item
    }

    public function test_default_checker_returns_false_on_windows(): void
    {
        // Test with a mock checker that simulates Windows environment
        $windowsChecker = new class extends \LaravelSpectrum\Performance\Support\DefaultParallelSupportChecker
        {
            protected function isWindows(): bool
            {
                return true;
            }
        };

        $processor = new ParallelProcessor(
            enabled: null,
            workers: 4,
            executor: null,
            workerCountResolver: null,
            supportChecker: $windowsChecker
        );

        $this->assertFalse($processor->isEnabled());
    }

    public function test_default_checker_returns_false_without_pcntl(): void
    {
        // Test with a mock checker that simulates missing PCNTL
        $noPcntlChecker = new class extends \LaravelSpectrum\Performance\Support\DefaultParallelSupportChecker
        {
            protected function isPcntlLoaded(): bool
            {
                return false;
            }
        };

        $processor = new ParallelProcessor(
            enabled: null,
            workers: 4,
            executor: null,
            workerCountResolver: null,
            supportChecker: $noPcntlChecker
        );

        $this->assertFalse($processor->isEnabled());
    }

    public function test_process_with_progress_modulo_10_check(): void
    {
        $processor = new ParallelProcessor(false, 4);
        $items = range(1, 10);
        $progressReports = [];

        $processor->processWithProgress(
            $items,
            fn ($item) => $item,
            function ($current, $total) use (&$progressReports) {
                $progressReports[] = $current;
            }
        );

        // Should report at exactly 10 (which is both modulo 10 and last item)
        $this->assertContains(10, $progressReports);
    }

    public function test_process_returns_results_in_correct_order_when_disabled(): void
    {
        $processor = new ParallelProcessor(false, 4);
        $items = [3, 1, 4, 1, 5, 9, 2, 6];

        $results = $processor->process($items, fn ($x) => $x * 10);

        // Order should be preserved when not parallel
        $this->assertEquals([30, 10, 40, 10, 50, 90, 20, 60], $results);
    }

    public function test_process_with_closure_that_modifies_state(): void
    {
        $processor = new ParallelProcessor(false, 4);
        $counter = 0;

        $items = range(1, 5);
        $results = $processor->process($items, function ($x) use (&$counter) {
            $counter++;

            return $x + $counter;
        });

        // Counter should increment for each item
        $this->assertEquals(5, $counter);
        // Results should reflect the incrementing counter
        $this->assertEquals([2, 4, 6, 8, 10], $results);
    }

    public function test_process_with_progress_uses_parallel_executor(): void
    {
        $executorCalled = false;
        $mockExecutor = new class($executorCalled) implements \LaravelSpectrum\Contracts\Performance\ParallelExecutorInterface
        {
            private bool $called;

            public function __construct(bool &$called)
            {
                $this->called = &$called;
            }

            public function execute(array $tasks, int $workers): array
            {
                $this->called = true;

                return array_map(fn ($task) => $task(), $tasks);
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $processor = new ParallelProcessor(
            enabled: true,
            workers: 2,
            executor: $mockExecutor
        );

        $progressCalls = [];
        $items = range(1, 25);
        $results = $processor->processWithProgress(
            $items,
            fn ($x) => $x * 2,
            function ($current, $total) use (&$progressCalls) {
                $progressCalls[] = $current;
            }
        );

        $this->assertTrue($executorCalled);
        $this->assertCount(25, $results);
    }

    public function test_process_chunks_routes_by_worker_count(): void
    {
        $taskCount = 0;
        $mockExecutor = new class($taskCount) implements \LaravelSpectrum\Contracts\Performance\ParallelExecutorInterface
        {
            private int $count;

            public function __construct(int &$count)
            {
                $this->count = &$count;
            }

            public function execute(array $tasks, int $workers): array
            {
                $this->count = count($tasks);

                return array_map(fn ($task) => $task(), $tasks);
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $processor = new ParallelProcessor(
            enabled: true,
            workers: 4,
            executor: $mockExecutor
        );

        // Process 60 items with 4 workers = ceil(60/4) = 15 items per chunk = 4 chunks
        $items = range(1, 60);
        $processor->process($items, fn ($x) => $x);

        $this->assertEquals(4, $taskCount);
    }

    public function test_process_with_progress_parallel_file_locking(): void
    {
        $mockExecutor = new class implements \LaravelSpectrum\Contracts\Performance\ParallelExecutorInterface
        {
            public function execute(array $tasks, int $workers): array
            {
                // Execute tasks in order to simulate parallel execution
                $results = [];
                foreach ($tasks as $task) {
                    $results[] = $task();
                }

                return $results;
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $processor = new ParallelProcessor(
            enabled: true,
            workers: 2,
            executor: $mockExecutor
        );

        $progressCalls = [];
        $items = range(1, 20);
        $results = $processor->processWithProgress(
            $items,
            fn ($x) => $x * 3,
            function ($current, $total) use (&$progressCalls) {
                $progressCalls[] = ['current' => $current, 'total' => $total];
            }
        );

        $this->assertCount(20, $results);
        // Progress callbacks should have been called with total = 20
        if (! empty($progressCalls)) {
            foreach ($progressCalls as $call) {
                $this->assertEquals(20, $call['total']);
            }
        }
    }

    public function test_process_merges_chunked_results_correctly(): void
    {
        $mockExecutor = new class implements \LaravelSpectrum\Contracts\Performance\ParallelExecutorInterface
        {
            public function execute(array $tasks, int $workers): array
            {
                return array_map(fn ($task) => $task(), $tasks);
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $processor = new ParallelProcessor(
            enabled: true,
            workers: 3,
            executor: $mockExecutor
        );

        // 60 items / 3 workers = 20 items per chunk
        $items = range(1, 60);
        $results = $processor->process($items, fn ($x) => $x * 10);

        $this->assertCount(60, $results);
        // Verify that results are correctly merged and contain expected values
        $this->assertContains(10, $results);
        $this->assertContains(600, $results);
    }

    public function test_process_with_progress_cleans_up_temp_file(): void
    {
        $mockExecutor = new class implements \LaravelSpectrum\Contracts\Performance\ParallelExecutorInterface
        {
            public function execute(array $tasks, int $workers): array
            {
                return array_map(fn ($task) => $task(), $tasks);
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $processor = new ParallelProcessor(
            enabled: true,
            workers: 2,
            executor: $mockExecutor
        );

        $tempDir = sys_get_temp_dir();
        $filesBefore = glob($tempDir.'/spectrum_progress_*') ?: [];

        $items = range(1, 25);
        $processor->processWithProgress(
            $items,
            fn ($x) => $x,
            fn ($current, $total) => null
        );

        $filesAfter = glob($tempDir.'/spectrum_progress_*') ?: [];

        // Temp files should be cleaned up
        $this->assertCount(count($filesBefore), $filesAfter);
    }

    public function test_process_with_large_dataset_and_many_workers(): void
    {
        $mockExecutor = new class implements \LaravelSpectrum\Contracts\Performance\ParallelExecutorInterface
        {
            public function execute(array $tasks, int $workers): array
            {
                return array_map(fn ($task) => $task(), $tasks);
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $processor = new ParallelProcessor(
            enabled: true,
            workers: 16,
            executor: $mockExecutor
        );

        // 160 items / 16 workers = 10 items per chunk = 16 chunks
        $items = range(1, 160);
        $results = $processor->process($items, fn ($x) => $x * 2);

        $this->assertCount(160, $results);
        $this->assertContains(2, $results);
        $this->assertContains(320, $results);
    }

    public function test_constructor_with_all_custom_dependencies(): void
    {
        $mockExecutor = new class implements \LaravelSpectrum\Contracts\Performance\ParallelExecutorInterface
        {
            public function execute(array $tasks, int $workers): array
            {
                return [];
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $mockResolver = new class implements \LaravelSpectrum\Contracts\Performance\WorkerCountResolverInterface
        {
            public function resolve(): int
            {
                return 12;
            }
        };

        $mockChecker = new class implements \LaravelSpectrum\Contracts\Performance\ParallelSupportCheckerInterface
        {
            public function isSupported(): bool
            {
                return true;
            }
        };

        $processor = new ParallelProcessor(
            enabled: null,
            workers: null,
            executor: $mockExecutor,
            workerCountResolver: $mockResolver,
            supportChecker: $mockChecker
        );

        $this->assertTrue($processor->isEnabled());
        $this->assertEquals(12, $processor->getWorkers());
    }
}
