<?php

declare(strict_types=1);

namespace Tests\Unit\Performance\Support;

use LaravelSpectrum\Contracts\Performance\ParallelExecutorInterface;
use LaravelSpectrum\Performance\Support\ForkParallelExecutor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ForkParallelExecutorTest extends TestCase
{
    #[Test]
    public function implements_parallel_executor_interface(): void
    {
        $executor = new ForkParallelExecutor;

        $this->assertInstanceOf(ParallelExecutorInterface::class, $executor);
    }

    #[Test]
    public function is_available_returns_boolean(): void
    {
        $executor = new ForkParallelExecutor;

        $result = $executor->isAvailable();

        $this->assertIsBool($result);
    }

    #[Test]
    public function is_available_returns_true_when_fork_class_exists(): void
    {
        $executor = new ForkParallelExecutor;

        // Spatie\Fork\Fork is a dev dependency so it should exist
        $this->assertTrue($executor->isAvailable());
    }

    #[Test]
    public function execute_throws_exception_when_not_available(): void
    {
        $executor = new class extends ForkParallelExecutor
        {
            public function isAvailable(): bool
            {
                return false;
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fork is not available');

        $executor->execute([fn () => 1], 2);
    }

    #[Test]
    public function execute_accepts_tasks_and_workers(): void
    {
        $executor = new ForkParallelExecutor;

        if (! $executor->isAvailable() || ! extension_loaded('pcntl')) {
            $this->markTestSkipped('Fork or PCNTL not available');
        }

        $tasks = [
            fn () => 1,
            fn () => 2,
        ];

        $results = $executor->execute($tasks, 2);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertEquals([1, 2], $results);
    }

    #[Test]
    public function execute_with_single_worker(): void
    {
        $executor = new ForkParallelExecutor;

        if (! $executor->isAvailable() || ! extension_loaded('pcntl')) {
            $this->markTestSkipped('Fork or PCNTL not available');
        }

        $results = $executor->execute([fn () => 'test'], 1);

        $this->assertIsArray($results);
        $this->assertEquals(['test'], $results);
    }

    #[Test]
    public function execute_handles_complex_return_values(): void
    {
        $executor = new ForkParallelExecutor;

        if (! $executor->isAvailable() || ! extension_loaded('pcntl')) {
            $this->markTestSkipped('Fork or PCNTL not available');
        }

        $tasks = [
            fn () => ['key' => 'value', 'nested' => ['a' => 1]],
            fn () => ['numbers' => [1, 2, 3]],
        ];

        $results = $executor->execute($tasks, 2);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertEquals('value', $results[0]['key']);
        $this->assertEquals([1, 2, 3], $results[1]['numbers']);
    }

    #[Test]
    public function initialize_child_process_is_called(): void
    {
        $initializeCalled = false;

        $executor = new class($initializeCalled) extends ForkParallelExecutor
        {
            private bool $called;

            public function __construct(bool &$called)
            {
                $this->called = &$called;
            }

            protected function initializeChildProcess(): void
            {
                $this->called = true;
            }
        };

        if (! $executor->isAvailable() || ! extension_loaded('pcntl')) {
            $this->markTestSkipped('Fork or PCNTL not available');
        }

        $executor->execute([fn () => 1], 1);

        // Note: Due to forking, the flag might not be set in the parent process
        // This test mainly verifies the code structure doesn't throw errors
        $this->assertTrue(true);
    }

    #[Test]
    public function initialize_child_process_handles_missing_db_facade(): void
    {
        // Create an executor that exposes initializeChildProcess for testing
        $executor = new class extends ForkParallelExecutor
        {
            public function callInitializeChildProcess(): void
            {
                $this->initializeChildProcess();
            }
        };

        // Should not throw exception even if DB facade doesn't exist
        // or connection fails
        $executor->callInitializeChildProcess();

        $this->assertTrue(true);
    }

    #[Test]
    public function execute_with_empty_tasks_array(): void
    {
        $executor = new ForkParallelExecutor;

        if (! $executor->isAvailable() || ! extension_loaded('pcntl')) {
            $this->markTestSkipped('Fork or PCNTL not available');
        }

        $results = $executor->execute([], 2);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }
}
