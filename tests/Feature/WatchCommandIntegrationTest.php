<?php

namespace LaravelSpectrum\Tests\Feature;

use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use React\EventLoop\Loop;

class WatchCommandIntegrationTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/spectrum_watch_test_'.uniqid();
        File::makeDirectory($this->tempDir, 0777, true, true);
        File::makeDirectory($this->tempDir.'/app/Http/Controllers', 0777, true, true);
        File::makeDirectory($this->tempDir.'/app/Http/Requests', 0777, true, true);
        File::makeDirectory($this->tempDir.'/routes', 0777, true, true);

        // Set up config
        config([
            'spectrum.watch.paths' => [
                $this->tempDir.'/app/Http/Controllers',
                $this->tempDir.'/app/Http/Requests',
                $this->tempDir.'/routes',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        File::deleteDirectory($this->tempDir);
        Loop::stop();
    }

    public function test_watch_command_detects_controller_changes(): void
    {
        $this->markTestSkipped('Skipping integration test that requires running event loop');
    }

    public function test_watch_command_detects_request_changes(): void
    {
        $this->markTestSkipped('Skipping integration test that requires running event loop');
    }

    public function test_watch_command_handles_multiple_file_changes(): void
    {
        $this->markTestSkipped('Skipping integration test that requires running event loop');
    }

    public function test_watch_command_ignores_non_php_files(): void
    {
        $this->markTestSkipped('Skipping integration test that requires running event loop');
    }

    protected function getPackageProviders($app): array
    {
        return ['LaravelPrism\\PrismServiceProvider'];
    }
}
