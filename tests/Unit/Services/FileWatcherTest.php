<?php

namespace LaravelPrism\Tests\Unit\Services;

use LaravelPrism\Services\FileWatcher;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;

class FileWatcherTest extends TestCase
{
    private FileWatcher $watcher;
    private string $tempDir;
    private static $testCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        self::$testCounter++;
        $this->watcher = new FileWatcher();
        $this->tempDir = sys_get_temp_dir() . '/prism_watcher_test_' . getmypid() . '_' . self::$testCounter;
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Loop::stop();
        // Add a small delay to ensure all operations complete
        usleep(100000);
        $this->recursiveRemoveDirectory($this->tempDir);
    }

    public function test_detects_new_file_creation(): void
    {
        $detectedChanges = [];
        $testFile = $this->tempDir . '/test.php';

        // Start watching
        $this->watcher->watch([$this->tempDir], function ($path, $event) use (&$detectedChanges) {
            $detectedChanges[] = ['path' => $path, 'event' => $event];
        });

        // Create file after initial polling cycle
        Loop::addTimer(0.7, function () use ($testFile) {
            if (is_dir($this->tempDir)) {
                file_put_contents($testFile, '<?php echo "test";');
            }
        });

        // Stop the loop after checking
        Loop::addTimer(1.5, function () {
            Loop::stop();
        });

        Loop::run();

        $this->assertCount(1, $detectedChanges);
        $this->assertStringEndsWith('/test.php', $detectedChanges[0]['path']);
        $this->assertEquals('created', $detectedChanges[0]['event']);
    }

    public function test_detects_file_modification(): void
    {
        $detectedChanges = [];
        $testFile = $this->tempDir . '/test.php';
        
        // Create initial file
        file_put_contents($testFile, '<?php echo "initial";');
        clearstatcache(true, $testFile);

        // Start watching
        $this->watcher->watch([$this->tempDir], function ($path, $event) use (&$detectedChanges) {
            $detectedChanges[] = ['path' => $path, 'event' => $event];
        });

        // Modify the file after a short delay
        Loop::addTimer(0.7, function () use ($testFile) {
            if (file_exists($testFile)) {
                // Ensure the file modification is detected
                file_put_contents($testFile, '<?php echo "modified";');
                touch($testFile, time() + 1);
                clearstatcache(true, $testFile);
            }
        });

        // Stop the loop after checking
        Loop::addTimer(1.5, function () {
            Loop::stop();
        });

        Loop::run();

        $this->assertCount(1, $detectedChanges);
        $this->assertStringEndsWith('/test.php', $detectedChanges[0]['path']);
        $this->assertEquals('modified', $detectedChanges[0]['event']);
    }

    public function test_detects_file_deletion(): void
    {
        $detectedChanges = [];
        $testFile = $this->tempDir . '/test.php';
        
        // Create initial file
        file_put_contents($testFile, '<?php echo "test";');
        $expectedPath = realpath($testFile);

        // Start watching
        $this->watcher->watch([$this->tempDir], function ($path, $event) use (&$detectedChanges) {
            $detectedChanges[] = ['path' => $path, 'event' => $event];
        });

        // Delete the file after a short delay
        Loop::addTimer(0.7, function () use ($testFile) {
            if (file_exists($testFile)) {
                unlink($testFile);
                clearstatcache(true, $testFile);
            }
        });

        // Stop the loop after checking
        Loop::addTimer(1.5, function () {
            Loop::stop();
        });

        Loop::run();

        $this->assertCount(1, $detectedChanges);
        $this->assertEquals($expectedPath, $detectedChanges[0]['path']);
        $this->assertEquals('deleted', $detectedChanges[0]['event']);
    }

    public function test_watches_multiple_directories(): void
    {
        $detectedChanges = [];
        $subDir = $this->tempDir . '/subdir';
        if (!is_dir($subDir)) {
            mkdir($subDir, 0777, true);
        }

        $testFile1 = $this->tempDir . '/test1.php';
        $testFile2 = $subDir . '/test2.php';

        // Start watching multiple directories
        $this->watcher->watch([$this->tempDir, $subDir], function ($path, $event) use (&$detectedChanges) {
            $detectedChanges[] = ['path' => $path, 'event' => $event];
        });

        // Create files in both directories
        Loop::addTimer(0.7, function () use ($testFile1, $testFile2, $subDir) {
            if (is_dir($this->tempDir)) {
                file_put_contents($testFile1, '<?php echo "test1";');
            }
            if (is_dir($subDir)) {
                file_put_contents($testFile2, '<?php echo "test2";');
            }
        });

        // Stop the loop after checking
        Loop::addTimer(1.5, function () {
            Loop::stop();
        });

        Loop::run();

        $this->assertCount(2, $detectedChanges);
        
        $paths = array_column($detectedChanges, 'path');
        $this->assertCount(1, array_filter($paths, fn($p) => str_ends_with($p, '/test1.php')));
        $this->assertCount(1, array_filter($paths, fn($p) => str_ends_with($p, '/test2.php')));
    }

    public function test_only_watches_php_files(): void
    {
        $detectedChanges = [];
        $phpFile = $this->tempDir . '/test.php';
        $txtFile = $this->tempDir . '/test.txt';

        // Start watching
        $this->watcher->watch([$this->tempDir], function ($path, $event) use (&$detectedChanges) {
            $detectedChanges[] = ['path' => $path, 'event' => $event];
        });

        // Create both PHP and non-PHP files
        Loop::addTimer(0.7, function () use ($phpFile, $txtFile) {
            if (is_dir($this->tempDir)) {
                file_put_contents($phpFile, '<?php echo "test";');
                file_put_contents($txtFile, 'This is a text file');
            }
        });

        // Stop the loop after checking
        Loop::addTimer(1.5, function () {
            Loop::stop();
        });

        Loop::run();

        // Should only detect the PHP file
        $this->assertCount(1, $detectedChanges);
        $this->assertStringEndsWith('/test.php', $detectedChanges[0]['path']);
    }

    public function test_custom_poll_interval(): void
    {
        $watcher = new FileWatcher(0.2); // 200ms poll interval
        $detectedChanges = [];
        $testFile = $this->tempDir . '/test.php';

        // Start watching
        $watcher->watch([$this->tempDir], function ($path, $event) use (&$detectedChanges) {
            $detectedChanges[] = ['path' => $path, 'event' => $event];
        });

        // Create a file
        Loop::addTimer(0.3, function () use ($testFile) {
            if (is_dir($this->tempDir)) {
                file_put_contents($testFile, '<?php echo "test";');
            }
        });

        // Stop the loop after sufficient time for polling
        Loop::addTimer(0.7, function () {
            Loop::stop();
        });

        Loop::run();

        $this->assertCount(1, $detectedChanges);
        $this->assertStringEndsWith('/test.php', $detectedChanges[0]['path']);
    }

    private function recursiveRemoveDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = array_diff(scandir($directory), ['.', '..']);
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            if (is_dir($path)) {
                $this->recursiveRemoveDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}