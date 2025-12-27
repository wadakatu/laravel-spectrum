<?php

declare(strict_types=1);

namespace Tests\Unit\Performance;

use LaravelSpectrum\Performance\MemoryManager;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function check_memory_usage_normal_conditions(): void
    {
        // 通常の条件下では例外がスローされない
        $this->assertNull($this->memoryManager->checkMemoryUsage());
    }

    #[Test]
    public function check_memory_usage_throws_exception_when_critical(): void
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

    #[Test]
    public function get_available_memory(): void
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

    #[Test]
    public function run_garbage_collection(): void
    {
        // ガベージコレクションが例外なく実行される
        $this->assertNull($this->memoryManager->runGarbageCollection());
    }

    #[Test]
    public function get_memory_stats(): void
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
            "Limit should be either a formatted size or 'unlimited', got: ".$stats['limit']
        );
        $this->assertIsFloat($stats['percentage']);
        $this->assertGreaterThanOrEqual(0, $stats['percentage']);
        $this->assertLessThanOrEqual(100, $stats['percentage']);
    }

    #[Test]
    public function memory_stats_reflect_current_state(): void
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

    #[Test]
    public function check_memory_usage_with_warning_threshold(): void
    {
        // メモリ制限を現在の使用量の約115%に設定（80%警告閾値を超える）
        $currentUsage = memory_get_usage(true);
        $newLimit = (int) ($currentUsage * 1.15);
        ini_set('memory_limit', $newLimit);

        $memoryManager = new MemoryManager;

        // 警告閾値では例外はスローされない
        $this->assertNull($memoryManager->checkMemoryUsage());
    }

    #[Test]
    public function memory_limit_parsing(): void
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
        if (! isset($matches[1]) || ! isset($matches[2])) {
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

    #[Test]
    public function memory_limit_parsing_with_gigabytes(): void
    {
        $originalLimit = ini_get('memory_limit');

        ini_set('memory_limit', '2G');
        $memoryManager = new MemoryManager;
        $stats = $memoryManager->getMemoryStats();

        $this->assertEquals('2 GB', $stats['limit']);

        ini_set('memory_limit', $originalLimit);
    }

    #[Test]
    public function memory_limit_parsing_with_kilobytes(): void
    {
        $originalLimit = ini_get('memory_limit');

        // Use a large KB value to ensure it exceeds current memory usage
        ini_set('memory_limit', '256000K');  // 250MB in KB

        // Check if the limit was actually set (some environments may ignore it)
        $newLimit = ini_get('memory_limit');
        if ($newLimit === '-1' || $newLimit === $originalLimit) {
            ini_set('memory_limit', $originalLimit);
            $this->markTestSkipped('Unable to set memory limit in this environment');
        }

        $memoryManager = new MemoryManager;
        $stats = $memoryManager->getMemoryStats();

        // The limit should be set to around 250 MB
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)? MB$/', $stats['limit']);

        ini_set('memory_limit', $originalLimit);
    }

    #[Test]
    public function memory_limit_parsing_with_bytes(): void
    {
        $originalLimit = ini_get('memory_limit');

        // Set memory limit to just bytes (no suffix)
        ini_set('memory_limit', '1073741824');  // 1GB in bytes
        $memoryManager = new MemoryManager;
        $stats = $memoryManager->getMemoryStats();

        $this->assertEquals('1 GB', $stats['limit']);

        ini_set('memory_limit', $originalLimit);
    }

    #[Test]
    public function memory_limit_parsing_unlimited(): void
    {
        $originalLimit = ini_get('memory_limit');

        ini_set('memory_limit', '-1');
        $memoryManager = new MemoryManager;
        $stats = $memoryManager->getMemoryStats();

        $this->assertEquals('unlimited', $stats['limit']);
        // Percentage should be 0 when memory is unlimited
        $this->assertEquals(0.0, $stats['percentage']);

        ini_set('memory_limit', $originalLimit);
    }

    #[Test]
    public function format_bytes_small_values(): void
    {
        $originalLimit = ini_get('memory_limit');

        // Set a reasonable limit first
        ini_set('memory_limit', '1G');
        $memoryManager = new MemoryManager;
        $stats = $memoryManager->getMemoryStats();

        // Current and peak memory should be formatted correctly
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)? (B|KB|MB|GB)$/', $stats['current']);
        $this->assertMatchesRegularExpression('/^\d+(\.\d+)? (B|KB|MB|GB)$/', $stats['peak']);

        ini_set('memory_limit', $originalLimit);
    }

    #[Test]
    public function get_available_memory_with_unlimited(): void
    {
        $originalLimit = ini_get('memory_limit');

        ini_set('memory_limit', '-1');
        $memoryManager = new MemoryManager;

        $available = $memoryManager->getAvailableMemory();

        // Available memory should be very large when unlimited
        $this->assertGreaterThan(1073741824, $available);  // > 1GB

        ini_set('memory_limit', $originalLimit);
    }

    #[Test]
    public function memory_stats_percentage_calculation(): void
    {
        $originalLimit = ini_get('memory_limit');

        // Set a memory limit that gives a reasonable percentage
        $currentUsage = memory_get_usage(true);
        $newLimit = $currentUsage * 2;  // 50% usage
        ini_set('memory_limit', (string) $newLimit);

        $memoryManager = new MemoryManager;
        $stats = $memoryManager->getMemoryStats();

        // Percentage should be around 50% (between 40-60% due to memory overhead)
        $this->assertGreaterThan(40, $stats['percentage']);
        $this->assertLessThan(60, $stats['percentage']);

        ini_set('memory_limit', $originalLimit);
    }

    #[Test]
    public function constructor_initializes_memory_limit(): void
    {
        $memoryManager = new MemoryManager;

        // Just verify the object was created without errors
        $this->assertInstanceOf(MemoryManager::class, $memoryManager);

        // And that we can get stats from it
        $stats = $memoryManager->getMemoryStats();
        $this->assertIsArray($stats);
    }

    #[Test]
    public function check_memory_usage_below_warning_threshold(): void
    {
        $originalLimit = ini_get('memory_limit');

        // Set memory limit high enough that we're well below warning threshold
        $currentUsage = memory_get_usage(true);
        $newLimit = $currentUsage * 10;  // 10% usage - well below 80% warning
        ini_set('memory_limit', (string) $newLimit);

        $memoryManager = new MemoryManager;

        // Should not throw exception and return null
        $result = $memoryManager->checkMemoryUsage();
        $this->assertNull($result);

        ini_set('memory_limit', $originalLimit);
    }

    #[Test]
    public function run_garbage_collection_completes_successfully(): void
    {
        $memoryManager = new MemoryManager;

        // Create some garbage
        for ($i = 0; $i < 100; $i++) {
            $obj = new \stdClass;
            $obj->data = str_repeat('x', 1000);
            unset($obj);
        }

        // Run garbage collection
        $memoryManager->runGarbageCollection();

        // If we get here without exception, the test passes
        $this->assertTrue(true);
    }

    #[Test]
    public function memory_limit_parsing_lowercase_suffix(): void
    {
        $originalLimit = ini_get('memory_limit');

        // PHP accepts lowercase suffixes too
        ini_set('memory_limit', '256m');
        $memoryManager = new MemoryManager;
        $stats = $memoryManager->getMemoryStats();

        $this->assertEquals('256 MB', $stats['limit']);

        ini_set('memory_limit', $originalLimit);
    }

    #[Test]
    public function get_memory_stats_returns_consistent_structure(): void
    {
        $memoryManager = new MemoryManager;

        // Call getMemoryStats multiple times
        $stats1 = $memoryManager->getMemoryStats();
        $stats2 = $memoryManager->getMemoryStats();

        // Both should have the same keys
        $this->assertEquals(array_keys($stats1), array_keys($stats2));

        // Expected keys
        $expectedKeys = ['current', 'peak', 'limit', 'percentage'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $stats1);
            $this->assertArrayHasKey($key, $stats2);
        }
    }
}
