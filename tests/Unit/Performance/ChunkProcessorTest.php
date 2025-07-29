<?php

namespace Tests\Unit\Performance;

use LaravelSpectrum\Performance\ChunkProcessor;
use LaravelSpectrum\Performance\MemoryManager;
use PHPUnit\Framework\TestCase;

class ChunkProcessorTest extends TestCase
{
    private ChunkProcessor $processor;
    private MemoryManager $memoryManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->memoryManager = $this->createMock(MemoryManager::class);
        $this->processor = new ChunkProcessor(3, $this->memoryManager);
    }

    public function testProcessInChunksWithSmallDataset(): void
    {
        $routes = ['route1', 'route2', 'route3', 'route4', 'route5'];
        $processedItems = [];

        $this->memoryManager->expects($this->exactly(2))
            ->method('checkMemoryUsage');

        $this->memoryManager->expects($this->never())
            ->method('runGarbageCollection');

        $processor = function ($chunk) use (&$processedItems) {
            $processedItems = array_merge($processedItems, $chunk);
            return array_map('strtoupper', $chunk);
        };

        $results = [];
        foreach ($this->processor->processInChunks($routes, $processor) as $result) {
            $results[] = $result;
        }

        // 検証: 2つのチャンクが処理されたこと
        $this->assertCount(2, $results);

        // 検証: 最初のチャンク
        $this->assertEquals(['ROUTE1', 'ROUTE2', 'ROUTE3'], $results[0]['result']);
        $this->assertEquals([
            'current' => 1,
            'total' => 2,
            'percentage' => 50.0,
        ], $results[0]['progress']);

        // 検証: 2番目のチャンク
        $this->assertEquals(['ROUTE4', 'ROUTE5'], $results[1]['result']);
        $this->assertEquals([
            'current' => 2,
            'total' => 2,
            'percentage' => 100.0,
        ], $results[1]['progress']);

        // 検証: すべてのアイテムが処理されたこと
        $this->assertEquals($routes, $processedItems);
    }

    public function testProcessInChunksTriggersGarbageCollection(): void
    {
        // チャンクサイズ1で30個のアイテムを処理（10チャンクごとにGC実行）
        $processor = new ChunkProcessor(1, $this->memoryManager);
        $routes = array_fill(0, 30, 'route');

        $this->memoryManager->expects($this->exactly(30))
            ->method('checkMemoryUsage');

        // 10, 20, 30チャンク目でGCが実行される
        $this->memoryManager->expects($this->exactly(3))
            ->method('runGarbageCollection');

        $processorFunc = function ($chunk) {
            return $chunk;
        };

        $count = 0;
        foreach ($processor->processInChunks($routes, $processorFunc) as $result) {
            $count++;
        }

        $this->assertEquals(30, $count);
    }

    public function testProcessInChunksWithEmptyArray(): void
    {
        $routes = [];
        $processor = function ($chunk) {
            return $chunk;
        };

        $results = iterator_to_array($this->processor->processInChunks($routes, $processor));

        $this->assertEmpty($results);
    }

    public function testStreamToFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'chunk_test');

        // テストデータを生成
        $generator = (function () {
            yield ['result' => ['data' => 'first'], 'progress' => ['current' => 1, 'total' => 2]];
            yield ['result' => ['data' => 'second'], 'progress' => ['current' => 2, 'total' => 2]];
        })();

        $this->processor->streamToFile($tempFile, $generator);

        $content = file_get_contents($tempFile);
        
        // ストリーミングされたJSONの内容を確認
        $this->assertStringContainsString('"data": "first"', $content);
        $this->assertStringContainsString('"data": "second"', $content);
        $this->assertStringStartsWith("{
", $content);
        $this->assertStringEndsWith("
}", $content);

        // クリーンアップ
        unlink($tempFile);
    }

    public function testStreamToFileWithSingleItem(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'chunk_test_single');

        $generator = (function () {
            yield ['result' => ['single' => 'item'], 'progress' => ['current' => 1, 'total' => 1]];
        })();

        $this->processor->streamToFile($tempFile, $generator);

        $content = file_get_contents($tempFile);
        $this->assertStringContainsString('"single": "item"', $content);

        // クリーンアップ
        unlink($tempFile);
    }

    public function testCalculateOptimalChunkSize(): void
    {
        // 100MB利用可能な場合
        $this->memoryManager->expects($this->once())
            ->method('getAvailableMemory')
            ->willReturn(100 * 1024 * 1024); // 100MB

        // 50KB per item の推定で、100MB / 50KB = 2048 items
        // その50% = 1024、最大値1000に制限される
        $optimalSize = $this->processor->calculateOptimalChunkSize(5000);
        $this->assertEquals(1000, $optimalSize);
    }

    public function testCalculateOptimalChunkSizeWithLowMemory(): void
    {
        // 1MB利用可能な場合
        $this->memoryManager->expects($this->once())
            ->method('getAvailableMemory')
            ->willReturn(1 * 1024 * 1024); // 1MB

        // 50KB per item の推定で、1MB / 50KB = 20 items
        // その50% = 10（最小値）
        $optimalSize = $this->processor->calculateOptimalChunkSize(100);
        $this->assertEquals(10, $optimalSize);
    }

    public function testProcessInChunksPreservesOriginalData(): void
    {
        $routes = [
            ['id' => 1, 'path' => '/api/users'],
            ['id' => 2, 'path' => '/api/posts'],
            ['id' => 3, 'path' => '/api/comments'],
        ];

        $processor = function ($chunk) {
            return array_map(function ($route) {
                return $route['path'];
            }, $chunk);
        };

        $this->memoryManager->expects($this->once())
            ->method('checkMemoryUsage');

        $results = iterator_to_array($this->processor->processInChunks($routes, $processor));

        $this->assertCount(1, $results);
        $this->assertEquals(['/api/users', '/api/posts', '/api/comments'], $results[0]['result']);
    }

    public function testProcessInChunksHandlesExceptions(): void
    {
        $routes = ['route1', 'route2'];

        $processor = function ($chunk) {
            throw new \RuntimeException('Processing failed');
        };

        $this->memoryManager->expects($this->once())
            ->method('checkMemoryUsage');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Processing failed');

        foreach ($this->processor->processInChunks($routes, $processor) as $result) {
            // 例外がスローされるべき
        }
    }
}