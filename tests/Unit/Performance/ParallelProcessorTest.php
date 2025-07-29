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

    public function testProcessWithSmallDataset(): void
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

    public function testProcessWithLargeDataset(): void
    {
        // 50個以上のデータで並列処理がトリガーされる
        $routes = array_map(fn($i) => "route$i", range(1, 60));
        
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

    public function testProcessPreservesDataTypes(): void
    {
        $routes = [
            ['id' => 1, 'path' => '/api/users'],
            ['id' => 2, 'path' => '/api/posts'],
        ];
        
        $processor = function ($route) {
            return [
                'id' => $route['id'],
                'processed_path' => $route['path'] . '_processed',
            ];
        };

        $results = $this->processor->process($routes, $processor);

        $this->assertCount(2, $results);
        $this->assertEquals(['id' => 1, 'processed_path' => '/api/users_processed'], $results[0]);
        $this->assertEquals(['id' => 2, 'processed_path' => '/api/posts_processed'], $results[1]);
    }

    public function testProcessWithProgressCallbackSmallDataset(): void
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

    public function testProcessWithProgressCallbackLargeDataset(): void
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

    public function testSetWorkers(): void
    {
        // setWorkersが例外をスローしないことを確認
        $this->processor->setWorkers(4);
        $this->processor->setWorkers(1);
        $this->processor->setWorkers(32);
        
        // 範囲外の値でもクランプされる
        $this->processor->setWorkers(0);
        $this->processor->setWorkers(100);
        
        // 簡単な処理が正常に動作することを確認
        $result = $this->processor->process(['test'], fn($x) => $x);
        $this->assertEquals(['test'], $result);
    }

    public function testProcessHandlesExceptions(): void
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

    public function testProcessWithEmptyArray(): void
    {
        $results = $this->processor->process([], fn($x) => $x);
        $this->assertEmpty($results);
    }

    public function testProcessMaintainsOrder(): void
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

    public function testProcessWithComplexDataStructures(): void
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
                'signature' => $route['method'] . ' ' . $route['uri'],
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
}