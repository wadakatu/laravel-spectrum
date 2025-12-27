<?php

namespace Tests\Unit\Performance;

use LaravelSpectrum\Performance\ParallelProcessor;
use PHPUnit\Framework\TestCase;

class ParallelProcessorTest extends TestCase
{
    private ParallelProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        // 並列処理を無効にして、テスト時は常にシーケンシャル処理にする
        $this->processor = new ParallelProcessor(false, 4);
    }

    public function test_process_with_small_dataset(): void
    {
        $routes = ['route1', 'route2', 'route3'];

        $processor = function ($route) {
            return strtoupper($route);
        };

        $results = $this->processor->process($routes, $processor);

        // 結果が正しく処理されている
        $this->assertCount(3, $results);
        $this->assertContains('ROUTE1', $results);
        $this->assertContains('ROUTE2', $results);
        $this->assertContains('ROUTE3', $results);
    }

    public function test_process_with_large_dataset(): void
    {
        // 50個以上のデータで並列処理がトリガーされる
        $routes = array_map(fn ($i) => "route$i", range(1, 60));

        $processor = function ($route) {
            return str_replace('route', 'processed_', $route);
        };

        $results = $this->processor->process($routes, $processor);

        // すべてのルートが処理されている
        $this->assertCount(60, $results);
        $this->assertContains('processed_1', $results);
        $this->assertContains('processed_30', $results);
        $this->assertContains('processed_60', $results);
    }

    public function test_process_preserves_data_types(): void
    {
        $routes = [
            ['id' => 1, 'path' => '/api/users'],
            ['id' => 2, 'path' => '/api/posts'],
        ];

        $processor = function ($route) {
            return [
                'id' => $route['id'],
                'processed_path' => $route['path'].'_processed',
            ];
        };

        $results = $this->processor->process($routes, $processor);

        $this->assertCount(2, $results);
        $this->assertEquals(['id' => 1, 'processed_path' => '/api/users_processed'], $results[0]);
        $this->assertEquals(['id' => 2, 'processed_path' => '/api/posts_processed'], $results[1]);
    }

    public function test_process_with_progress_callback_small_dataset(): void
    {
        $items = range(1, 5);
        $progressCalls = [];

        $processor = function ($item) {
            return $item * 2;
        };

        $onProgress = function ($current, $total) use (&$progressCalls) {
            $progressCalls[] = ['current' => $current, 'total' => $total];
        };

        $results = $this->processor->processWithProgress($items, $processor, $onProgress);

        // 結果が正しい
        $this->assertEquals([2, 4, 6, 8, 10], $results);

        // 進捗コールバックが呼ばれた
        $this->assertNotEmpty($progressCalls);
        $lastCall = end($progressCalls);
        $this->assertEquals(5, $lastCall['current']);
        $this->assertEquals(5, $lastCall['total']);
    }

    public function test_process_with_progress_callback_large_dataset(): void
    {
        $items = range(1, 25);
        $progressCalls = [];

        $processor = function ($item) {
            return $item + 100;
        };

        $onProgress = function ($current, $total) use (&$progressCalls) {
            $progressCalls[] = $current;
        };

        $results = $this->processor->processWithProgress($items, $processor, $onProgress);

        // 結果が正しい
        $this->assertCount(25, $results);
        $this->assertEquals(101, $results[0]);
        $this->assertEquals(125, $results[24]);

        // 進捗が10アイテムごとに報告される
        $this->assertContains(10, $progressCalls);
        $this->assertContains(20, $progressCalls);
        $this->assertContains(25, $progressCalls);
    }

    public function test_set_workers(): void
    {
        // setWorkersが例外をスローしないことを確認
        $this->processor->setWorkers(4);
        $this->processor->setWorkers(1);
        $this->processor->setWorkers(32);

        // 範囲外の値でもクランプされる
        $this->processor->setWorkers(0);
        $this->processor->setWorkers(100);

        // 簡単な処理が正常に動作することを確認
        $result = $this->processor->process(['test'], fn ($x) => $x);
        $this->assertEquals(['test'], $result);
    }

    public function test_set_workers_clamps_values(): void
    {
        $reflection = new \ReflectionClass($this->processor);
        $workersProperty = $reflection->getProperty('workers');
        $workersProperty->setAccessible(true);

        // 最小値のテスト
        $this->processor->setWorkers(0);
        $this->assertEquals(1, $workersProperty->getValue($this->processor));

        // 最大値のテスト
        $this->processor->setWorkers(100);
        $this->assertEquals(32, $workersProperty->getValue($this->processor));

        // 通常の値
        $this->processor->setWorkers(8);
        $this->assertEquals(8, $workersProperty->getValue($this->processor));
    }

    public function test_process_handles_exceptions(): void
    {
        $routes = ['route1', 'route2', 'route3'];

        $processor = function ($route) {
            if ($route === 'route2') {
                throw new \RuntimeException('Test exception');
            }

            return $route;
        };

        // 例外がスローされる
        $this->expectException(\RuntimeException::class);
        $this->processor->process($routes, $processor);
    }

    public function test_process_with_empty_array(): void
    {
        $results = $this->processor->process([], fn ($x) => $x);
        $this->assertEmpty($results);
    }

    public function test_process_maintains_order(): void
    {
        // 順序が保持されることを確認
        $items = range(1, 100);

        $processor = function ($item) {
            return $item * 10;
        };

        $results = $this->processor->process($items, $processor);

        // 結果の数が正しい
        $this->assertCount(100, $results);

        // いくつかの値をチェック
        $this->assertEquals(10, $results[0]);
        $this->assertEquals(500, $results[49]);
        $this->assertEquals(1000, $results[99]);
    }

    public function test_process_with_complex_data_structures(): void
    {
        $routes = [
            [
                'method' => 'GET',
                'uri' => '/api/users',
                'middleware' => ['auth', 'throttle:60,1'],
                'meta' => ['version' => 'v1'],
            ],
            [
                'method' => 'POST',
                'uri' => '/api/posts',
                'middleware' => ['auth'],
                'meta' => ['version' => 'v2'],
            ],
        ];

        $processor = function ($route) {
            return [
                'signature' => $route['method'].' '.$route['uri'],
                'middlewareCount' => count($route['middleware']),
                'version' => $route['meta']['version'],
            ];
        };

        $results = $this->processor->process($routes, $processor);

        $this->assertCount(2, $results);
        $this->assertEquals('GET /api/users', $results[0]['signature']);
        $this->assertEquals(2, $results[0]['middlewareCount']);
        $this->assertEquals('v1', $results[0]['version']);
    }

    public function test_processor_uses_injected_worker_count_resolver(): void
    {
        // Create a mock for WorkerCountResolverInterface
        $mockResolver = new class implements \LaravelSpectrum\Contracts\Performance\WorkerCountResolverInterface
        {
            public function resolve(): int
            {
                return 8;
            }
        };

        $processor = new ParallelProcessor(
            enabled: false,
            workers: null,
            executor: null,
            workerCountResolver: $mockResolver
        );

        // Access the workers property via reflection
        $reflection = new \ReflectionClass($processor);
        $workersProperty = $reflection->getProperty('workers');
        $workersProperty->setAccessible(true);

        // Worker count should be 8 from the mock resolver
        $this->assertEquals(8, $workersProperty->getValue($processor));
    }

    public function test_process_without_fork_class(): void
    {
        // Fork クラスが存在しない場合のテスト（シーケンシャル処理にフォールバック）
        $processor = new ParallelProcessor(false, 4);

        // 少数のデータでテスト
        $routes = ['route1', 'route2', 'route3'];

        $processFunc = function ($route) {
            return strtoupper($route);
        };

        $results = $processor->process($routes, $processFunc);

        $this->assertCount(3, $results);
        $this->assertContains('ROUTE1', $results);
        $this->assertContains('ROUTE2', $results);
        $this->assertContains('ROUTE3', $results);
    }

    public function test_process_with_progress_empty_array(): void
    {
        $progressCalls = [];
        $results = $this->processor->processWithProgress(
            [],
            fn ($x) => $x,
            function ($current, $total) use (&$progressCalls) {
                $progressCalls[] = ['current' => $current, 'total' => $total];
            }
        );

        $this->assertEmpty($results);
        $this->assertEmpty($progressCalls);
    }

    public function test_process_with_progress_handles_exceptions(): void
    {
        $items = [1, 2, 3];
        $progressCalls = [];

        $processor = function ($item) {
            if ($item === 2) {
                throw new \RuntimeException('Test exception');
            }

            return $item;
        };

        $onProgress = function ($current, $total) use (&$progressCalls) {
            $progressCalls[] = $current;
        };

        $this->expectException(\RuntimeException::class);
        $this->processor->processWithProgress($items, $processor, $onProgress);
    }

    public function test_process_with_database_reconnection(): void
    {
        // このテストはLaravelのDB ファサードが利用可能な場合のみ意味がある
        if (! class_exists('\Illuminate\Support\Facades\DB')) {
            $this->markTestSkipped('Laravel DB facade not available');
        }

        // DB::reconnect() がエラーを起こさないことを確認するモックテスト
        $processor = new ParallelProcessor(false, 2);
        $routes = ['route1', 'route2'];

        // 実際のForkが利用できない環境でも動作することを確認
        $results = $processor->process($routes, fn ($route) => $route);
        $this->assertCount(2, $results);
    }

    public function test_process_sequential_with_progress_reports_correctly(): void
    {
        $processor = new ParallelProcessor(false, 4);
        $items = range(1, 25);
        $progressReports = [];

        $results = $processor->processWithProgress(
            $items,
            fn ($item) => $item * 2,
            function ($current, $total) use (&$progressReports) {
                $progressReports[] = "$current/$total";
            }
        );

        $this->assertCount(25, $results);
        $this->assertEquals(2, $results[0]);
        $this->assertEquals(50, $results[24]);

        // 進捗が10個ごとと最後に報告される
        $this->assertContains('10/25', $progressReports);
        $this->assertContains('20/25', $progressReports);
        $this->assertContains('25/25', $progressReports);
    }

    public function test_parallel_processing_with_enabled_flag(): void
    {
        // 並列処理を明示的に有効化
        $processor = new ParallelProcessor(true, 4);

        // 小さなデータセット（50未満）では並列処理されない
        $smallData = range(1, 30);
        $results = $processor->process($smallData, fn ($x) => $x * 2);
        $this->assertCount(30, $results);
        $this->assertEquals(2, $results[0]);
        $this->assertEquals(60, $results[29]);
    }

    public function test_is_enabled_returns_correct_value(): void
    {
        $enabledProcessor = new ParallelProcessor(true, 4);
        $disabledProcessor = new ParallelProcessor(false, 4);

        $this->assertTrue($enabledProcessor->isEnabled());
        $this->assertFalse($disabledProcessor->isEnabled());
    }

    public function test_get_workers_returns_correct_value(): void
    {
        $processor = new ParallelProcessor(false, 8);
        $this->assertEquals(8, $processor->getWorkers());

        $processor->setWorkers(16);
        $this->assertEquals(16, $processor->getWorkers());
    }

    public function test_processor_uses_injected_support_checker(): void
    {
        // Create a mock that always returns false
        $mockChecker = new class implements \LaravelSpectrum\Contracts\Performance\ParallelSupportCheckerInterface
        {
            public function isSupported(): bool
            {
                return false;
            }
        };

        $processor = new ParallelProcessor(
            enabled: null,  // Use the checker instead
            workers: 4,
            executor: null,
            workerCountResolver: null,
            supportChecker: $mockChecker
        );

        $this->assertFalse($processor->isEnabled());
    }

    public function test_processor_uses_injected_executor(): void
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

                // Execute tasks sequentially for testing
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

        // Process large dataset to trigger parallel processing
        $data = range(1, 60);
        $results = $processor->process($data, fn ($x) => $x * 2);

        $this->assertTrue($executorCalled);
        $this->assertCount(60, $results);
    }

    public function test_fallback_to_sequential_when_executor_unavailable(): void
    {
        $mockExecutor = new class implements \LaravelSpectrum\Contracts\Performance\ParallelExecutorInterface
        {
            public function execute(array $tasks, int $workers): array
            {
                throw new \RuntimeException('Should not be called');
            }

            public function isAvailable(): bool
            {
                return false;
            }
        };

        $processor = new ParallelProcessor(
            enabled: true,
            workers: 2,
            executor: $mockExecutor
        );

        // Process large dataset
        $data = range(1, 60);
        $results = $processor->process($data, fn ($x) => $x * 2);

        // Should fallback to sequential processing
        $this->assertCount(60, $results);
        $this->assertEquals(2, $results[0]);
        $this->assertEquals(120, $results[59]);
    }

    public function test_progress_fallback_when_executor_unavailable(): void
    {
        $mockExecutor = new class implements \LaravelSpectrum\Contracts\Performance\ParallelExecutorInterface
        {
            public function execute(array $tasks, int $workers): array
            {
                throw new \RuntimeException('Should not be called');
            }

            public function isAvailable(): bool
            {
                return false;
            }
        };

        $processor = new ParallelProcessor(
            enabled: true,
            workers: 2,
            executor: $mockExecutor
        );

        $progressCalls = [];
        $results = $processor->processWithProgress(
            range(1, 15),
            fn ($x) => $x * 2,
            function ($current, $total) use (&$progressCalls) {
                $progressCalls[] = $current;
            }
        );

        $this->assertCount(15, $results);
        $this->assertContains(10, $progressCalls);
        $this->assertContains(15, $progressCalls);
    }
}
