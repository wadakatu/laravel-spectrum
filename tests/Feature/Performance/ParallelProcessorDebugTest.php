<?php

namespace Tests\Feature\Performance;

use LaravelSpectrum\Performance\ParallelProcessor;
use Orchestra\Testbench\TestCase;

class ParallelProcessorDebugTest extends TestCase
{
    public function test_debug_parallel_processing(): void
    {
        $processor = new ParallelProcessor(true, 2);
        
        // 少ないデータ数でテスト
        $items = ['A', 'B', 'C', 'D'];
        
        $results = $processor->process($items, function ($item) {
            return strtolower($item);
        });
        
        $this->assertCount(4, $results);
        $this->assertContains('a', $results);
        $this->assertContains('b', $results);
        $this->assertContains('c', $results);
        $this->assertContains('d', $results);
    }
    
    public function test_fork_class_availability(): void
    {
        $this->assertTrue(class_exists('\Spatie\Fork\Fork'), 'Fork class should exist');
        $this->assertTrue(extension_loaded('pcntl'), 'PCNTL extension should be loaded');
    }
    
    public function test_parallel_processing_with_exact_50_items(): void
    {
        $processor = new ParallelProcessor(true, 2);
        
        // ちょうど50個のデータ
        $items = range(1, 50);
        
        $results = $processor->process($items, fn($x) => $x * 2);
        
        $this->assertCount(50, $results);
        
        // 結果を検証
        $expected = array_map(fn($x) => $x * 2, $items);
        sort($results);
        sort($expected);
        
        $this->assertEquals($expected, $results);
    }
}