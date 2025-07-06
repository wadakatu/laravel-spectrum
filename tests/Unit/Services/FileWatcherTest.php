<?php

namespace LaravelPrism\Tests\Unit\Services;

use LaravelPrism\Services\FileWatcher;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;

class FileWatcherTest extends TestCase
{
    private FileWatcher $watcher;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watcher = new FileWatcher();
        $this->tempDir = realpath(sys_get_temp_dir()) . '/prism_watcher_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->recursiveRemoveDirectory($this->tempDir);
        // Clear the event loop to prevent interference between tests
        Loop::stop();
        Loop::get()->futureTick(function () {});
    }

    public function test_detects_new_file_creation(): void
    {
        $detectedChanges = [];
        $testFile = $this->tempDir . '/test.php';

        // Create file before watching starts
        Loop::futureTick(function () use ($testFile) {
            // Wait a moment to ensure watcher is ready
            usleep(700000); // 700ms
            file_put_contents($testFile, '<?php echo "test";');
        });

        // Start watching
        $this->watcher->watch([$this->tempDir], function ($path, $event) use (&$detectedChanges, $testFile) {
            if ($path === realpath($testFile)) {
                $detectedChanges[] = ['path' => $path, 'event' => $event];
            }
        });

        // Stop the loop after checking
        Loop::addTimer(2.0, function () {
            Loop::stop();
        });

        Loop::run();

        $this->assertCount(1, $detectedChanges);
        $this->assertEquals(realpath($testFile), $detectedChanges[0]['path']);
        $this->assertEquals('created', $detectedChanges[0]['event']);
    }

    public function test_detects_file_modification(): void
    {
        $detectedChanges = [];
        $testFile = $this->tempDir . '/test.php';
        
        // Create initial file
        file_put_contents($testFile, '<?php echo "initial";');

        // Start watching
        $this->watcher->watch([$this->tempDir], function ($path, $event) use (&$detectedChanges) {
            $detectedChanges[] = ['path' => $path, 'event' => $event];
        });

        // Modify the file after a short delay
        Loop::addTimer(0.6, function () use ($testFile) {
            touch($testFile, time() + 1); // Change mtime
            file_put_contents($testFile, '<?php echo "modified";');
        });

        // Stop the loop after checking
        Loop::addTimer(1.5, function () {
            Loop::stop();
        });

        Loop::run();

        $this->assertCount(1, $detectedChanges);
        $this->assertEquals(realpath($testFile), $detectedChanges[0]['path']);
        $this->assertEquals('modified', $detectedChanges[0]['event']);
    }

    public function test_detects_file_deletion(): void
    {
        $detectedChanges = [];
        $testFile = $this->tempDir . '/test.php';
        
        // Create initial file
        file_put_contents($testFile, '<?php echo "test";');
        $testFilePath = realpath($testFile); // Get the real path before deletion

        // Start watching
        $this->watcher->watch([$this->tempDir], function ($path, $event) use (&$detectedChanges) {
            $detectedChanges[] = ['path' => $path, 'event' => $event];
        });

        // Delete the file after a short delay
        Loop::futureTick(function () use ($testFile) {
            usleep(700000); // 700ms
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        });

        // Stop the loop after checking
        Loop::addTimer(2.0, function () {
            Loop::stop();
        });

        Loop::run();

        $this->assertCount(1, $detectedChanges);
        $this->assertEquals($testFilePath, $detectedChanges[0]['path']);
        $this->assertEquals('deleted', $detectedChanges[0]['event']);
    }

    public function test_watches_multiple_directories(): void
    {
        $detectedChanges = [];
        $subDir = $this->tempDir . '/subdir';
        mkdir($subDir);

        $testFile1 = $this->tempDir . '/test1.php';
        $testFile2 = $subDir . '/test2.php';

        // Start watching multiple directories
        $this->watcher->watch([$this->tempDir, $subDir], function ($path, $event) use (&$detectedChanges) {
            $detectedChanges[] = ['path' => $path, 'event' => $event];
        });

        // Create files in both directories
        Loop::addTimer(0.6, function () use ($testFile1, $testFile2) {
            file_put_contents($testFile1, '<?php echo "test1";');
            file_put_contents($testFile2, '<?php echo "test2";');
        });

        // Stop the loop after checking
        Loop::addTimer(1.5, function () {
            Loop::stop();
        });

        Loop::run();

        $this->assertCount(2, $detectedChanges);
        
        $paths = array_column($detectedChanges, 'path');
        $this->assertContains(realpath($testFile1), $paths);
        $this->assertContains(realpath($testFile2), $paths);
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
        Loop::futureTick(function () use ($phpFile, $txtFile) {
            usleep(700000); // 700ms
            file_put_contents($phpFile, '<?php echo "test";');
            file_put_contents($txtFile, 'This is a text file');
        });

        // Stop the loop after checking
        Loop::addTimer(2.0, function () {
            Loop::stop();
        });

        Loop::run();

        // Should only detect the PHP file
        $this->assertCount(1, $detectedChanges);
        $this->assertEquals(realpath($phpFile), $detectedChanges[0]['path']);
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
            file_put_contents($testFile, '<?php echo "test";');
        });

        // Stop the loop after sufficient time for polling
        Loop::addTimer(0.8, function () {
            Loop::stop();
        });

        Loop::run();

        $this->assertCount(1, $detectedChanges);
        $this->assertEquals(realpath($testFile), $detectedChanges[0]['path']);
    }

    private function recursiveRemoveDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = array_diff(scandir($directory), ['.', '..']);
        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}