<?php

declare(strict_types=1);

namespace LaravelSpectrum\Performance;

use LaravelSpectrum\Contracts\Performance\ParallelExecutorInterface;
use LaravelSpectrum\Contracts\Performance\ParallelSupportCheckerInterface;
use LaravelSpectrum\Contracts\Performance\WorkerCountResolverInterface;
use LaravelSpectrum\Performance\Support\DefaultParallelSupportChecker;
use LaravelSpectrum\Performance\Support\DefaultWorkerCountResolver;
use LaravelSpectrum\Performance\Support\ForkParallelExecutor;

class ParallelProcessor
{
    private int $workers;

    private bool $enabled;

    private ParallelExecutorInterface $executor;

    private WorkerCountResolverInterface $workerCountResolver;

    private ParallelSupportCheckerInterface $supportChecker;

    public function __construct(
        ?bool $enabled = null,
        ?int $workers = null,
        ?ParallelExecutorInterface $executor = null,
        ?WorkerCountResolverInterface $workerCountResolver = null,
        ?ParallelSupportCheckerInterface $supportChecker = null
    ) {
        $this->workerCountResolver = $workerCountResolver ?? new DefaultWorkerCountResolver;
        $this->supportChecker = $supportChecker ?? new DefaultParallelSupportChecker;
        $this->executor = $executor ?? new ForkParallelExecutor;

        $this->workers = $workers ?? $this->workerCountResolver->resolve();
        $this->enabled = $enabled ?? $this->supportChecker->isSupported();
    }

    /**
     * Process routes in parallel using multiple workers
     */
    public function process(array $routes, callable $processor): array
    {
        if (! $this->enabled || count($routes) < 50) {
            // 小規模な場合は通常処理
            return array_map($processor, $routes);
        }

        // Check if executor is available
        if (! $this->executor->isAvailable()) {
            // Fallback to sequential processing
            return array_map($processor, $routes);
        }

        // ルートをワーカー数で分割
        $chunks = array_chunk($routes, (int) ceil(count($routes) / $this->workers));

        $tasks = [];
        foreach ($chunks as $chunk) {
            $tasks[] = function () use ($chunk, $processor) {
                return array_map($processor, $chunk);
            };
        }

        $results = $this->executor->execute($tasks, $this->workers);

        // 結果をマージ
        return collect($results)->flatten(1)->toArray();
    }

    /**
     * Process with progress callback
     */
    public function processWithProgress(array $items, callable $processor, callable $onProgress): array
    {
        if (! $this->enabled || ! $this->executor->isAvailable()) {
            return $this->processSequentialWithProgress($items, $processor, $onProgress);
        }

        $totalItems = count($items);
        $chunks = array_chunk($items, (int) ceil($totalItems / $this->workers));

        // Create temporary file for progress tracking
        $progressFile = tempnam(sys_get_temp_dir(), 'spectrum_progress_');
        file_put_contents($progressFile, '0');

        $tasks = [];
        foreach ($chunks as $chunk) {
            $tasks[] = function () use ($chunk, $processor, $progressFile, $onProgress, $totalItems) {
                $results = [];
                foreach ($chunk as $item) {
                    $result = $processor($item);
                    $results[] = $result;

                    // Update progress in file
                    $fp = fopen($progressFile, 'c+');
                    if ($fp && flock($fp, LOCK_EX)) {
                        $fileSize = filesize($progressFile);
                        $current = 0;
                        if ($fileSize > 0) {
                            $current = (int) fread($fp, $fileSize);
                        }
                        $current++;
                        ftruncate($fp, 0);
                        rewind($fp);
                        fwrite($fp, (string) $current);
                        flock($fp, LOCK_UN);

                        // Call progress callback periodically
                        if ($current % 10 === 0) {
                            $onProgress($current, $totalItems);
                        }
                    }
                    if ($fp) {
                        fclose($fp);
                    }
                }

                return $results;
            };
        }

        $chunkResults = $this->executor->execute($tasks, $this->workers);

        // Clean up
        if (file_exists($progressFile)) {
            unlink($progressFile);
        }

        return collect($chunkResults)->flatten(1)->toArray();
    }

    private function processSequentialWithProgress(array $items, callable $processor, callable $onProgress): array
    {
        $total = count($items);
        $results = [];

        foreach ($items as $index => $item) {
            $results[] = $processor($item);

            if (($index + 1) % 10 === 0 || $index === $total - 1) {
                $onProgress($index + 1, $total);
            }
        }

        return $results;
    }

    /**
     * Set the number of workers
     */
    public function setWorkers(int $workers): void
    {
        $this->workers = max(1, min(32, $workers));
    }

    /**
     * Check if parallel processing is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get the number of workers.
     */
    public function getWorkers(): int
    {
        return $this->workers;
    }
}
