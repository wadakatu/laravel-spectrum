<?php

namespace Tests\Feature\Performance;

use LaravelSpectrum\Performance\ParallelProcessor;
use Orchestra\Testbench\TestCase;

class ParallelProcessorIntegrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        // Configure test environment
        $app['config']->set('spectrum.performance.parallel_processing', true);
    }

    public function test_constructor_with_default_parameters(): void
    {
        $processor = new ParallelProcessor;

        $reflection = new \ReflectionClass($processor);
        $workersProperty = $reflection->getProperty('workers');
        $workersProperty->setAccessible(true);
        $enabledProperty = $reflection->getProperty('enabled');
        $enabledProperty->setAccessible(true);

        // ワーカー数は環境により異なるが、有効な範囲内であることを確認
        $workers = $workersProperty->getValue($processor);
        $this->assertGreaterThanOrEqual(2, $workers);
        $this->assertLessThanOrEqual(16, $workers);

        // 並列処理のサポート状態を確認（環境依存）
        $enabled = $enabledProperty->getValue($processor);
        $this->assertIsBool($enabled);
    }

    public function test_check_parallel_processing_support_respects_config(): void
    {
        // 設定で並列処理を無効化
        $this->app['config']->set('spectrum.performance.parallel_processing', false);

        $processor = new ParallelProcessor;

        $reflection = new \ReflectionClass($processor);
        $enabledProperty = $reflection->getProperty('enabled');
        $enabledProperty->setAccessible(true);

        $this->assertFalse($enabledProperty->getValue($processor));
    }

    public function test_check_parallel_processing_support_when_enabled(): void
    {
        // 設定で並列処理を有効化
        $this->app['config']->set('spectrum.performance.parallel_processing', true);

        $processor = new ParallelProcessor;

        $supported = $processor->isEnabled();

        // Windows環境やPCNTL拡張の有無により結果が異なる
        if (PHP_OS_FAMILY === 'Windows') {
            $this->assertFalse($supported);
        } elseif (! extension_loaded('pcntl')) {
            $this->assertFalse($supported);
        } else {
            $this->assertTrue($supported);
        }
    }

    public function test_process_with_database_reconnection(): void
    {
        // データベース設定
        $this->app['config']->set('database.default', 'testing');
        $this->app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $processor = new ParallelProcessor(true, 2);
        $items = ['item1', 'item2', 'item3'];

        // DB::reconnect() がエラーなく実行されることを確認
        $results = $processor->process($items, fn ($item) => strtoupper($item));

        $this->assertCount(3, $results);
        $this->assertContains('ITEM1', $results);
        $this->assertContains('ITEM2', $results);
        $this->assertContains('ITEM3', $results);
    }

    public function test_process_with_large_dataset_in_parallel_mode(): void
    {
        // 並列処理を有効化
        $this->app['config']->set('spectrum.performance.parallel_processing', true);

        // Fork が利用可能でない場合はスキップ
        if (! class_exists('\Spatie\Fork\Fork')) {
            $this->markTestSkipped('Spatie\Fork is not available for parallel processing');
        }

        $processor = new ParallelProcessor(true, 4);

        // 50個以上のデータで並列処理がトリガーされる
        $routes = array_map(fn ($i) => ['id' => $i, 'path' => "/api/route$i"], range(1, 60));

        $processFunc = function ($route) {
            return [
                'id' => $route['id'],
                'processed_path' => strtoupper($route['path']),
            ];
        };

        $results = $processor->process($routes, $processFunc);

        // すべてのルートが処理されている
        $this->assertCount(60, $results);

        // 結果の検証（順序は保証されない可能性がある）
        $processedIds = array_column($results, 'id');
        sort($processedIds);
        $this->assertEquals(range(1, 60), $processedIds);

        // パスが正しく処理されている
        $hasUppercasedPath = false;
        foreach ($results as $result) {
            if (isset($result['processed_path']) && preg_match('/^\/API\/ROUTE\d+$/', $result['processed_path'])) {
                $hasUppercasedPath = true;
                break;
            }
        }
        $this->assertTrue($hasUppercasedPath);
    }

    public function test_process_with_progress_in_parallel_mode_with_config(): void
    {
        $this->app['config']->set('spectrum.performance.parallel_processing', true);

        $processor = new ParallelProcessor(true, 4);

        $items = range(1, 100);
        $progressCalls = [];

        $results = $processor->processWithProgress(
            $items,
            fn ($item) => $item * 2,
            function ($current, $total) use (&$progressCalls) {
                $progressCalls[] = ['current' => $current, 'total' => $total];
            }
        );

        $this->assertCount(100, $results);

        // 結果の検証
        $expected = array_map(fn ($i) => $i * 2, $items);
        sort($results);
        sort($expected);
        $this->assertEquals($expected, $results);

        // 進捗が報告されていることを確認
        if (! empty($progressCalls)) {
            $lastCall = end($progressCalls);
            $this->assertEquals(100, $lastCall['total']);

            // 進捗が増加していることを確認
            $currentValues = array_column($progressCalls, 'current');
            $this->assertEquals($currentValues, array_unique($currentValues), 'Progress should only increase');
        }
    }
}
