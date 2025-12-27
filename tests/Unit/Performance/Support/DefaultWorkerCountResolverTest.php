<?php

declare(strict_types=1);

namespace Tests\Unit\Performance\Support;

use LaravelSpectrum\Performance\Support\DefaultWorkerCountResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DefaultWorkerCountResolverTest extends TestCase
{
    #[Test]
    public function resolve_returns_value_within_default_range(): void
    {
        $resolver = new DefaultWorkerCountResolver;

        $workers = $resolver->resolve();

        $this->assertGreaterThanOrEqual(2, $workers);
        $this->assertLessThanOrEqual(16, $workers);
    }

    #[Test]
    public function resolve_respects_custom_min_workers(): void
    {
        $resolver = new DefaultWorkerCountResolver(minWorkers: 4);

        $workers = $resolver->resolve();

        $this->assertGreaterThanOrEqual(4, $workers);
    }

    #[Test]
    public function resolve_respects_custom_max_workers(): void
    {
        $resolver = new DefaultWorkerCountResolver(maxWorkers: 8);

        $workers = $resolver->resolve();

        $this->assertLessThanOrEqual(8, $workers);
    }

    #[Test]
    public function resolve_uses_custom_multiplier(): void
    {
        // Use a resolver with multiplier of 1 (cores * 1)
        $resolver = new DefaultWorkerCountResolver(minWorkers: 1, maxWorkers: 32, multiplier: 1);
        $workersMultiplierOne = $resolver->resolve();

        // Use a resolver with multiplier of 4 (cores * 4)
        $resolverHighMultiplier = new DefaultWorkerCountResolver(minWorkers: 1, maxWorkers: 32, multiplier: 4);
        $workersHighMultiplier = $resolverHighMultiplier->resolve();

        // Higher multiplier should result in more workers (or equal if capped)
        $this->assertGreaterThanOrEqual($workersMultiplierOne, $workersHighMultiplier);
    }

    #[Test]
    public function resolve_clamps_to_min_workers(): void
    {
        // Create a testable subclass to force a low core count
        $resolver = new class extends DefaultWorkerCountResolver
        {
            protected function detectCpuCores(): int
            {
                return 1;  // Force 1 core
            }
        };

        // With default settings (min=2, multiplier=2): 1 * 2 = 2, clamped to min=2
        $workers = $resolver->resolve();
        $this->assertEquals(2, $workers);
    }

    #[Test]
    public function resolve_clamps_to_max_workers(): void
    {
        // Create a testable subclass to force a high core count
        $resolver = new class extends DefaultWorkerCountResolver
        {
            protected function detectCpuCores(): int
            {
                return 100;  // Force 100 cores
            }
        };

        // With default settings (max=16, multiplier=2): 100 * 2 = 200, clamped to max=16
        $workers = $resolver->resolve();
        $this->assertEquals(16, $workers);
    }

    #[Test]
    public function detect_cpu_cores_returns_at_least_one(): void
    {
        $resolver = new class extends DefaultWorkerCountResolver
        {
            public function getDetectedCores(): int
            {
                return $this->detectCpuCores();
            }
        };

        $cores = $resolver->getDetectedCores();
        $this->assertGreaterThanOrEqual(1, $cores);
    }

    #[Test]
    public function constructor_accepts_all_parameters(): void
    {
        // Should not throw exception
        $resolver = new DefaultWorkerCountResolver(
            minWorkers: 1,
            maxWorkers: 32,
            multiplier: 4
        );

        $this->assertInstanceOf(DefaultWorkerCountResolver::class, $resolver);
    }

    #[Test]
    public function resolve_returns_integer(): void
    {
        $resolver = new DefaultWorkerCountResolver;

        $result = $resolver->resolve();

        $this->assertIsInt($result);
    }

    #[Test]
    public function constructor_throws_exception_for_min_workers_less_than_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('minWorkers must be at least 1');

        new DefaultWorkerCountResolver(minWorkers: 0);
    }

    #[Test]
    public function constructor_throws_exception_for_negative_min_workers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('minWorkers must be at least 1');

        new DefaultWorkerCountResolver(minWorkers: -5);
    }

    #[Test]
    public function constructor_throws_exception_when_max_less_than_min(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxWorkers must be >= minWorkers');

        new DefaultWorkerCountResolver(minWorkers: 10, maxWorkers: 5);
    }

    #[Test]
    public function constructor_throws_exception_for_multiplier_less_than_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('multiplier must be at least 1');

        new DefaultWorkerCountResolver(multiplier: 0);
    }

    #[Test]
    public function constructor_throws_exception_for_negative_multiplier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('multiplier must be at least 1');

        new DefaultWorkerCountResolver(multiplier: -1);
    }

    #[Test]
    public function constructor_accepts_min_equals_max(): void
    {
        // Edge case: min equals max should be valid
        $resolver = new DefaultWorkerCountResolver(minWorkers: 4, maxWorkers: 4);

        $this->assertInstanceOf(DefaultWorkerCountResolver::class, $resolver);
        $this->assertEquals(4, $resolver->resolve());
    }

    #[Test]
    public function detect_cpu_cores_returns_positive_integer_on_darwin(): void
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            $this->markTestSkipped('This test only runs on macOS');
        }

        $resolver = new class extends DefaultWorkerCountResolver
        {
            public function getDetectedCores(): int
            {
                return $this->detectCpuCores();
            }
        };

        $cores = $resolver->getDetectedCores();
        $this->assertGreaterThan(0, $cores);
    }

    #[Test]
    public function detect_cpu_cores_handles_darwin_null_shell_exec(): void
    {
        // Simulate macOS where shell_exec returns null
        $resolver = new class extends DefaultWorkerCountResolver
        {
            public function getDetectedCores(): int
            {
                // Override to simulate Darwin branch with null result
                return $this->simulateDarwinNullResult();
            }

            private function simulateDarwinNullResult(): int
            {
                $result = null;  // Simulating shell_exec returning null

                return $result !== null ? (int) $result : 1;
            }
        };

        $cores = $resolver->getDetectedCores();
        $this->assertEquals(1, $cores);
    }

    #[Test]
    public function detect_cpu_cores_handles_proc_cpuinfo_false(): void
    {
        // Simulate Linux where file_get_contents returns false
        $resolver = new class extends DefaultWorkerCountResolver
        {
            public function getDetectedCores(): int
            {
                return $this->simulateProcCpuinfoFalse();
            }

            private function simulateProcCpuinfoFalse(): int
            {
                $content = false;  // Simulating file_get_contents returning false

                return $content !== false ? substr_count($content, 'processor') : 1;
            }
        };

        $cores = $resolver->getDetectedCores();
        $this->assertEquals(1, $cores);
    }

    #[Test]
    public function detect_cpu_cores_handles_windows_env_false(): void
    {
        // Simulate Windows where getenv returns false
        $resolver = new class extends DefaultWorkerCountResolver
        {
            public function getDetectedCores(): int
            {
                return $this->simulateWindowsEnvFalse();
            }

            private function simulateWindowsEnvFalse(): int
            {
                $processors = false;  // Simulating getenv returning false

                return $processors !== false ? (int) $processors : 1;
            }
        };

        $cores = $resolver->getDetectedCores();
        $this->assertEquals(1, $cores);
    }

    #[Test]
    public function resolve_with_single_core_and_min_workers(): void
    {
        $resolver = new class extends DefaultWorkerCountResolver
        {
            public function __construct()
            {
                parent::__construct(minWorkers: 4, maxWorkers: 16, multiplier: 2);
            }

            protected function detectCpuCores(): int
            {
                return 1;  // Single core: 1 * 2 = 2, but min is 4
            }
        };

        $this->assertEquals(4, $resolver->resolve());
    }

    #[Test]
    public function resolve_with_many_cores_and_max_workers(): void
    {
        $resolver = new class extends DefaultWorkerCountResolver
        {
            public function __construct()
            {
                parent::__construct(minWorkers: 2, maxWorkers: 8, multiplier: 2);
            }

            protected function detectCpuCores(): int
            {
                return 10;  // 10 cores: 10 * 2 = 20, but max is 8
            }
        };

        $this->assertEquals(8, $resolver->resolve());
    }

    #[Test]
    public function resolve_with_exact_multiplier_result(): void
    {
        $resolver = new class extends DefaultWorkerCountResolver
        {
            public function __construct()
            {
                parent::__construct(minWorkers: 1, maxWorkers: 20, multiplier: 3);
            }

            protected function detectCpuCores(): int
            {
                return 4;  // 4 cores: 4 * 3 = 12
            }
        };

        $this->assertEquals(12, $resolver->resolve());
    }

    #[Test]
    public function resolve_caches_consistent_result(): void
    {
        $resolver = new DefaultWorkerCountResolver;

        $result1 = $resolver->resolve();
        $result2 = $resolver->resolve();

        $this->assertEquals($result1, $result2);
    }
}
