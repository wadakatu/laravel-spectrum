<?php

namespace LaravelSpectrum\Performance;

use RuntimeException;

class MemoryManager
{
    private int $memoryLimit;

    private float $warningThreshold = 0.8; // 80%で警告

    private float $criticalThreshold = 0.9; // 90%でクリティカル

    public function __construct()
    {
        $this->memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
    }

    public function checkMemoryUsage(): void
    {
        $usage = memory_get_usage(true);
        $percentage = $usage / $this->memoryLimit;

        if ($percentage > $this->criticalThreshold) {
            throw new RuntimeException(
                sprintf(
                    'Memory usage critical: %s of %s (%.2f%%)',
                    $this->formatBytes($usage),
                    $this->formatBytes($this->memoryLimit),
                    $percentage * 100
                )
            );
        }

        if ($percentage > $this->warningThreshold) {
            // ログに警告を記録
            if (function_exists('app') && app()->has('log')) {
                app('log')->warning('High memory usage detected', [
                    'usage' => $this->formatBytes($usage),
                    'limit' => $this->formatBytes($this->memoryLimit),
                    'percentage' => $percentage * 100,
                ]);
            }

            // ガベージコレクションを実行
            $this->runGarbageCollection();
        }
    }

    public function getAvailableMemory(): int
    {
        return $this->memoryLimit - memory_get_usage(true);
    }

    public function runGarbageCollection(): void
    {
        gc_collect_cycles();
    }

    public function getMemoryStats(): array
    {
        $usage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);
        
        // Calculate percentage, handling unlimited memory case
        $percentage = 0.0;
        if ($this->memoryLimit !== PHP_INT_MAX && $this->memoryLimit > 0) {
            $percentage = round(($usage / $this->memoryLimit) * 100, 2);
        }

        return [
            'current' => $this->formatBytes($usage),
            'peak' => $this->formatBytes($peakUsage),
            'limit' => $this->formatBytes($this->memoryLimit),
            'percentage' => $percentage,
        ];
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        
        // -1 means unlimited memory
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }
        
        $last = strtolower($limit[strlen($limit) - 1]);

        // 単位が含まれる場合は数値部分のみを抽出
        if (in_array($last, ['g', 'm', 'k'])) {
            $value = (int) substr($limit, 0, -1);
        } else {
            $value = (int) $limit;
        }

        switch ($last) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }

        return $value;
    }

    private function formatBytes(int $bytes): string
    {
        // Handle unlimited memory case
        if ($bytes === PHP_INT_MAX) {
            return 'unlimited';
        }
        
        // Handle negative values
        if ($bytes < 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
