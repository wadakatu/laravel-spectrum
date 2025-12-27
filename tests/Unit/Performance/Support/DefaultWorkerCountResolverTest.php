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
}
