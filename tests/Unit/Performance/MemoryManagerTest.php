<?php

namespace Tests\Unit\Performance;

use LaravelSpectrum\Performance\MemoryManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MemoryManagerTest extends TestCase
{
    private MemoryManager $memoryManager;

    private string $originalMemoryLimit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalMemoryLimit = ini_get('memory_limit');
        $this->memoryManager = new MemoryManager;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // メモリ制限を元に戻す
        ini_set('memory_limit', $this->originalMemoryLimit);
    }

    public function test_check_memory_usage_normal_conditions(): void
    {
        // 通常の条件下では例外がスローされない
        $this->assertNull($this->memoryManager->checkMemoryUsage());
    }

    public function test_check_memory_usage_throws_exception_when_critical(): void
    {
        // メモリ制限を現在の使用量より少し多い値に設定
        $currentUsage = memory_get_usage(true);
        $newLimit = (int) ($currentUsage * 1.05); // 現在の使用量の105%
        ini_set('memory_limit', $newLimit);

        $memoryManager = new MemoryManager;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Memory usage critical: .* of .* \(9[0-9]\.[0-9]+%\)/');

        $memoryManager->checkMemoryUsage();
    }

    public function test_get_available_memory(): void
    {
        $available = $this->memoryManager->getAvailableMemory();

        // 利用可能なメモリは正の値であるべき
        $this->assertGreaterThan(0, $available);

        // 利用可能なメモリは現在のメモリ制限より少ないべき（無制限の場合を除く）
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        if ($memoryLimit !== PHP_INT_MAX) {
            $this->assertLessThan($memoryLimit, $available);
        }
    }

    public function test_run_garbage_collection(): void
    {
        // ガベージコレクションが例外なく実行される
        $this->assertNull($this->memoryManager->runGarbageCollection());
    }

    public function test_get_memory_stats(): void
    {
        $stats = $this->memoryManager->getMemoryStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('current', $stats);
        $this->assertArrayHasKey('peak', $stats);
        $this->assertArrayHasKey('limit', $stats);
        $this->assertArrayHasKey('percentage', $stats);

        // 値の形式を確認
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)? (B|KB|MB|GB)$/', $stats['current']);
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)? (B|KB|MB|GB)$/', $stats['peak']);
        // limit can be either a size format or "unlimited"
        $this->assertTrue(
            preg_match('/^\d+(\.\d+)? (B|KB|MB|GB)$/', $stats['limit']) === 1 ||
            $stats['limit'] === 'unlimited',
            "Limit should be either a formatted size or 'unlimited', got: " . $stats['limit']
        );
        $this->assertIsFloat($stats['percentage']);
        $this->assertGreaterThanOrEqual(0, $stats['percentage']);
        $this->assertLessThanOrEqual(100, $stats['percentage']);
    }

    public function test_memory_stats_reflect_current_state(): void
    {
        $statsBefore = $this->memoryManager->getMemoryStats();

        // メモリを意図的に使用
        $largeArray = range(1, 100000);

        $statsAfter = $this->memoryManager->getMemoryStats();

        // current使用量が増加しているはず
        $beforeBytes = $this->parseFormattedBytes($statsBefore['current']);
        $afterBytes = $this->parseFormattedBytes($statsAfter['current']);

        $this->assertGreaterThanOrEqual($beforeBytes, $afterBytes);
    }

    public function test_check_memory_usage_with_warning_threshold(): void
    {
        // メモリ制限を現在の使用量の約115%に設定（80%警告閾値を超える）
        $currentUsage = memory_get_usage(true);
        $newLimit = (int) ($currentUsage * 1.15);
        ini_set('memory_limit', $newLimit);

        $memoryManager = new MemoryManager;

        // 警告閾値では例外はスローされない
        $this->assertNull($memoryManager->checkMemoryUsage());
    }

    public function test_memory_limit_parsing(): void
    {
        // 現在のメモリ制限を保存
        $originalLimit = ini_get('memory_limit');

        // 512Mに設定してテスト
        ini_set('memory_limit', '512M');
        $memoryManager = new MemoryManager;
        $stats = $memoryManager->getMemoryStats();

        $this->assertEquals('512 MB', $stats['limit']);

        // 元に戻す
        ini_set('memory_limit', $originalLimit);
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        
        // -1 means unlimited memory
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }
        
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

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

    private function parseFormattedBytes(string $formatted): int
    {
        // Handle unlimited case
        if ($formatted === 'unlimited') {
            return PHP_INT_MAX;
        }
        
        preg_match('/^([\d.]+)\s*(B|KB|MB|GB)$/', $formatted, $matches);
        if (!isset($matches[1]) || !isset($matches[2])) {
            return 0;
        }
        
        $value = (float) $matches[1];
        $unit = $matches[2];

        switch ($unit) {
            case 'KB':
                return (int) ($value * 1024);
            case 'MB':
                return (int) ($value * 1024 * 1024);
            case 'GB':
                return (int) ($value * 1024 * 1024 * 1024);
            default:
                return (int) $value;
        }
    }
}
