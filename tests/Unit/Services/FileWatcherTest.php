<?php

namespace LaravelPrism\Tests\Unit\Services;

use LaravelPrism\Services\FileWatcher;
use Orchestra\Testbench\TestCase;

class FileWatcherTest extends TestCase
{
    private FileWatcher $watcher;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watcher = new FileWatcher;
        $this->tempDir = sys_get_temp_dir().'/prism_watcher_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->recursiveRemoveDirectory($this->tempDir);
    }

    public function test_file_watcher_instantiation(): void
    {
        $this->assertInstanceOf(FileWatcher::class, $this->watcher);
    }

    public function test_custom_poll_interval(): void
    {
        $watcher = new FileWatcher(0.2);
        $reflection = new \ReflectionClass($watcher);
        $property = $reflection->getProperty('pollInterval');
        $property->setAccessible(true);
        
        $this->assertEquals(0.2, $property->getValue($watcher));
    }

    public function test_initialize_file_hashes(): void
    {
        // Create test files
        file_put_contents($this->tempDir.'/test1.php', '<?php echo "test1";');
        file_put_contents($this->tempDir.'/test2.php', '<?php echo "test2";');
        
        // Use reflection to access private methods
        $reflection = new \ReflectionClass($this->watcher);
        $initMethod = $reflection->getMethod('initializeFileHashes');
        $initMethod->setAccessible(true);
        $hashesProperty = $reflection->getProperty('fileHashes');
        $hashesProperty->setAccessible(true);
        
        // Initialize hashes
        $initMethod->invoke($this->watcher, [$this->tempDir]);
        $hashes = $hashesProperty->getValue($this->watcher);
        
        // Verify hashes were created
        $this->assertCount(2, $hashes);
        $this->assertArrayHasKey(realpath($this->tempDir.'/test1.php'), $hashes);
        $this->assertArrayHasKey(realpath($this->tempDir.'/test2.php'), $hashes);
    }

    public function test_detect_file_creation(): void
    {
        $reflection = new \ReflectionClass($this->watcher);
        $checkMethod = $reflection->getMethod('checkForChanges');
        $checkMethod->setAccessible(true);
        $initMethod = $reflection->getMethod('initializeFileHashes');
        $initMethod->setAccessible(true);
        
        // Initialize with empty directory
        $initMethod->invoke($this->watcher, [$this->tempDir]);
        
        // Create a new file
        file_put_contents($this->tempDir.'/new_file.php', '<?php echo "new";');
        
        // Track callback invocations
        $changes = [];
        $callback = function ($path, $event) use (&$changes) {
            $changes[] = ['path' => $path, 'event' => $event];
        };
        
        // Check for changes
        $checkMethod->invoke($this->watcher, [$this->tempDir], $callback);
        
        // Verify file creation was detected
        $this->assertCount(1, $changes);
        $this->assertEquals('created', $changes[0]['event']);
        $this->assertStringContainsString('new_file.php', $changes[0]['path']);
    }

    public function test_detect_file_modification(): void
    {
        // Create initial file
        $filePath = $this->tempDir.'/modify_test.php';
        file_put_contents($filePath, '<?php echo "original";');
        
        $reflection = new \ReflectionClass($this->watcher);
        $checkMethod = $reflection->getMethod('checkForChanges');
        $checkMethod->setAccessible(true);
        $initMethod = $reflection->getMethod('initializeFileHashes');
        $initMethod->setAccessible(true);
        
        // Initialize with the file
        $initMethod->invoke($this->watcher, [$this->tempDir]);
        
        // Wait a bit and modify the file
        sleep(1);
        file_put_contents($filePath, '<?php echo "modified";');
        
        // Track callback invocations
        $changes = [];
        $callback = function ($path, $event) use (&$changes) {
            $changes[] = ['path' => $path, 'event' => $event];
        };
        
        // Check for changes
        $checkMethod->invoke($this->watcher, [$this->tempDir], $callback);
        
        // Verify file modification was detected
        $this->assertCount(1, $changes);
        $this->assertEquals('modified', $changes[0]['event']);
        $this->assertStringContainsString('modify_test.php', $changes[0]['path']);
    }

    public function test_detect_file_deletion(): void
    {
        // Create initial file
        $filePath = $this->tempDir.'/delete_test.php';
        file_put_contents($filePath, '<?php echo "delete me";');
        
        $reflection = new \ReflectionClass($this->watcher);
        $checkMethod = $reflection->getMethod('checkForChanges');
        $checkMethod->setAccessible(true);
        $initMethod = $reflection->getMethod('initializeFileHashes');
        $initMethod->setAccessible(true);
        
        // Initialize with the file
        $initMethod->invoke($this->watcher, [$this->tempDir]);
        
        // Delete the file
        unlink($filePath);
        
        // Track callback invocations
        $changes = [];
        $callback = function ($path, $event) use (&$changes) {
            $changes[] = ['path' => $path, 'event' => $event];
        };
        
        // Check for changes
        $checkMethod->invoke($this->watcher, [$this->tempDir], $callback);
        
        // Verify file deletion was detected
        $this->assertCount(1, $changes);
        $this->assertEquals('deleted', $changes[0]['event']);
        $this->assertStringContainsString('delete_test.php', $changes[0]['path']);
    }

    public function test_hash_file_returns_empty_for_nonexistent(): void
    {
        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('hashFile');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->watcher, '/nonexistent/file.php');
        $this->assertEquals('', $result);
    }

    public function test_hash_file_includes_mtime(): void
    {
        $filePath = $this->tempDir.'/hash_test.php';
        file_put_contents($filePath, '<?php echo "test";');
        
        $reflection = new \ReflectionClass($this->watcher);
        $method = $reflection->getMethod('hashFile');
        $method->setAccessible(true);
        
        $hash = $method->invoke($this->watcher, $filePath);
        
        // Verify hash format (md5:mtime)
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}:\d+$/', $hash);
        
        // Verify hash changes with file content
        $originalHash = $hash;
        file_put_contents($filePath, '<?php echo "modified";');
        clearstatcache(true, $filePath); // Clear file stat cache
        $newHash = $method->invoke($this->watcher, $filePath);
        
        $this->assertNotEquals($originalHash, $newHash);
    }

    public function test_watch_single_file(): void
    {
        $filePath = $this->tempDir.'/single_file.php';
        file_put_contents($filePath, '<?php echo "single";');
        
        $reflection = new \ReflectionClass($this->watcher);
        $hashesMethod = $reflection->getMethod('getCurrentFileHashes');
        $hashesMethod->setAccessible(true);
        
        // Get hashes for single file
        $hashes = $hashesMethod->invoke($this->watcher, [$filePath]);
        
        $this->assertCount(1, $hashes);
        $this->assertArrayHasKey(realpath($filePath), $hashes);
    }

    public function test_excludes_vendor_and_node_modules(): void
    {
        // Create test structure
        mkdir($this->tempDir.'/vendor', 0777, true);
        mkdir($this->tempDir.'/node_modules', 0777, true);
        mkdir($this->tempDir.'/src', 0777, true);
        
        file_put_contents($this->tempDir.'/vendor/file.php', '<?php // vendor');
        file_put_contents($this->tempDir.'/node_modules/file.php', '<?php // node');
        file_put_contents($this->tempDir.'/src/file.php', '<?php // src');
        
        $reflection = new \ReflectionClass($this->watcher);
        $hashesMethod = $reflection->getMethod('getCurrentFileHashes');
        $hashesMethod->setAccessible(true);
        
        // Get hashes for directory
        $hashes = $hashesMethod->invoke($this->watcher, [$this->tempDir]);
        
        // Verify only src file is included
        $this->assertCount(1, $hashes);
        $this->assertArrayHasKey(realpath($this->tempDir.'/src/file.php'), $hashes);
    }

    private function recursiveRemoveDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = array_diff(scandir($directory), ['.', '..']);
        foreach ($files as $file) {
            $path = $directory.'/'.$file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}