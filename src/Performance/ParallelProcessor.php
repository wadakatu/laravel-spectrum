<?php

namespace LaravelSpectrum\Performance;

use Spatie\Fork\Fork;

class ParallelProcessor
{
    private int $workers;

    private bool $enabled;

    public function __construct(?bool $enabled = null, ?int $workers = null)
    {
        $this->workers = $workers ?? $this->determineOptimalWorkers();
        $this->enabled = $enabled ?? $this->checkParallelProcessingSupport();
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

        // Fork is only used when parallel processing is enabled
        if (! class_exists('\Spatie\Fork\Fork')) {
            // Fork not available, fallback to sequential processing
            return array_map($processor, $routes);
        }

        // ルートをワーカー数で分割
        $chunks = array_chunk($routes, (int) ceil(count($routes) / $this->workers));

        $fork = Fork::new()
            ->concurrent($this->workers)
            ->before(function () {
                // 子プロセスの初期化
                // データベース接続のリセットなど
                if (class_exists('\Illuminate\Support\Facades\DB')) {
                    try {
                        \Illuminate\Support\Facades\DB::reconnect();
                    } catch (\Exception $e) {
                        // 接続がない場合は無視
                    }
                }
            });

        $results = $fork->run(function () use ($chunks, $processor) {
            $results = [];
            foreach ($chunks as $index => $chunk) {
                $results[$index] = array_map($processor, $chunk);
            }

            return $results;
        });

        // 結果をマージ
        return collect($results)->flatten(1)->toArray();
    }

    /**
     * Process with progress callback
     */
    public function processWithProgress(array $items, callable $processor, callable $onProgress): array
    {
        if (! $this->enabled || ! class_exists('\Spatie\Fork\Fork')) {
            return $this->processSequentialWithProgress($items, $processor, $onProgress);
        }

        $totalItems = count($items);
        $chunks = array_chunk($items, (int) ceil($totalItems / $this->workers));

        // Create temporary file for progress tracking
        $progressFile = tempnam(sys_get_temp_dir(), 'spectrum_progress_');
        file_put_contents($progressFile, '0');

        $fork = Fork::new()->concurrent($this->workers);

        $chunkResults = $fork->run(function () use ($chunks, $processor, $progressFile, $onProgress, $totalItems) {
            $results = [];

            foreach ($chunks as $index => $chunk) {
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
            }

            return $results;
        });

        // Clean up
        unlink($progressFile);

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

    private function determineOptimalWorkers(): int
    {
        // CPU コア数を取得
        $cores = 1;

        if (function_exists('swoole_cpu_num')) {
            $cores = swoole_cpu_num();
        } elseif (is_file('/proc/cpuinfo')) {
            $cores = substr_count(file_get_contents('/proc/cpuinfo'), 'processor');
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $cores = (int) shell_exec('sysctl -n hw.ncpu');
        } elseif (PHP_OS_FAMILY === 'Windows') {
            $cores = (int) getenv('NUMBER_OF_PROCESSORS');
        }

        // 最大でCPUコア数の2倍、最小2、最大16
        return max(2, min(16, $cores * 2));
    }

    private function checkParallelProcessingSupport(): bool
    {
        // PCNTL拡張が必要
        if (! extension_loaded('pcntl')) {
            return false;
        }

        // Windows環境では無効
        if (PHP_OS_FAMILY === 'Windows') {
            return false;
        }

        // 設定で無効化されている場合
        if (function_exists('config') && config('spectrum.performance.parallel_processing', true) === false) {
            return false;
        }

        return true;
    }
}
