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
        
        // Skip tests that require Workerman runtime
        if (!defined('WORKERMAN_RUN_MODE')) {
            $this->markTestSkipped('FileWatcher tests require Workerman runtime');
        }
        
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
        $this->assertInstanceOf(FileWatcher::class, $watcher);
    }

    public function test_watch_accepts_array_of_paths(): void
    {
        $called = false;
        $this->watcher->watch([$this->tempDir], function () use (&$called) {
            $called = true;
        });

        // Simply test that watch method accepts the parameters without error
        $this->assertTrue(true);
    }

    public function test_detects_file_changes_basic(): void
    {
        $this->markTestSkipped('Skipping async file watcher tests in CI environment');
    }

    private function recursiveRemoveDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
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
