<?php

namespace LaravelPrism\Tests\Unit\Console;

use LaravelPrism\Console\WatchCommand;
use LaravelPrism\Services\DocumentationCache;
use LaravelPrism\Services\FileWatcher;
use LaravelPrism\Services\LiveReloadServer;
use Mockery;
use Orchestra\Testbench\TestCase;
use React\EventLoop\Loop;

class WatchCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Prevent the actual event loop from running
        $loop = Loop::get();
        $reflection = new \ReflectionClass($loop);
        if ($reflection->hasMethod('run')) {
            $runMethod = $reflection->getMethod('run');
            $runMethod->setAccessible(true);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        Loop::stop();
    }

    public function test_watch_command_initializes_services(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $liveReloadServer = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $this->app->instance(FileWatcher::class, $fileWatcher);
        $this->app->instance(LiveReloadServer::class, $liveReloadServer);
        $this->app->instance(DocumentationCache::class, $cache);

        // Expect server to start with default options
        $liveReloadServer->shouldReceive('start')
            ->once()
            ->with('127.0.0.1', 8080);

        // Expect file watcher to be initialized
        $fileWatcher->shouldReceive('watch')
            ->once()
            ->with(Mockery::type('array'), Mockery::type('callable'));

        // Mock the loop to prevent actual running
        Loop::set($this->createMockLoop());

        $this->artisan('prism:watch', ['--no-open' => true])
            ->expectsOutput('🚀 Starting Laravel Prism preview server...')
            ->expectsOutput('📡 Preview server running at http://127.0.0.1:8080')
            ->expectsOutput('👀 Watching for file changes...')
            ->assertExitCode(0);
    }

    public function test_watch_command_with_custom_port_and_host(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $liveReloadServer = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $this->app->instance(FileWatcher::class, $fileWatcher);
        $this->app->instance(LiveReloadServer::class, $liveReloadServer);
        $this->app->instance(DocumentationCache::class, $cache);

        // Expect server to start with custom options
        $liveReloadServer->shouldReceive('start')
            ->once()
            ->with('0.0.0.0', 3000);

        $fileWatcher->shouldReceive('watch')
            ->once();

        Loop::set($this->createMockLoop());

        $this->artisan('prism:watch', [
            '--port' => 3000,
            '--host' => '0.0.0.0',
            '--no-open' => true,
        ])
            ->expectsOutput('📡 Preview server running at http://0.0.0.0:3000')
            ->assertExitCode(0);
    }

    public function test_file_change_handler(): void
    {
        // Test that file changes are properly handled through file watcher callback
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $liveReloadServer = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $this->app->instance(FileWatcher::class, $fileWatcher);
        $this->app->instance(LiveReloadServer::class, $liveReloadServer);
        $this->app->instance(DocumentationCache::class, $cache);

        $watchCallback = null;

        // Capture the callback that will be passed to watch()
        $fileWatcher->shouldReceive('watch')
            ->once()
            ->with(Mockery::type('array'), Mockery::on(function ($callback) use (&$watchCallback) {
                $watchCallback = $callback;

                return true;
            }));

        $liveReloadServer->shouldReceive('start')->once();

        // Test FormRequest file change
        $formRequestPath = app_path('Http/Requests/TestRequest.php');

        $cache->shouldReceive('forget')
            ->once()
            ->with('form_request:App\\Http\\Requests\\TestRequest');

        $liveReloadServer->shouldReceive('notifyClients')
            ->once()
            ->with(Mockery::on(function ($data) use ($formRequestPath) {
                return $data['event'] === 'documentation-updated' &&
                       $data['path'] === $formRequestPath &&
                       isset($data['timestamp']);
            }));

        // Mock the loop to prevent actual running
        Loop::set($this->createMockLoop());

        // Start the command
        $this->artisan('prism:watch', ['--no-open' => true]);

        // Now simulate a file change by calling the captured callback
        if ($watchCallback) {
            $watchCallback($formRequestPath, 'modified');
        }
    }

    public function test_cache_clearing_for_different_file_types(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $liveReloadServer = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $this->app->instance(FileWatcher::class, $fileWatcher);
        $this->app->instance(LiveReloadServer::class, $liveReloadServer);
        $this->app->instance(DocumentationCache::class, $cache);

        $watchCallback = null;

        // Capture the callback
        $fileWatcher->shouldReceive('watch')
            ->once()
            ->with(Mockery::type('array'), Mockery::on(function ($callback) use (&$watchCallback) {
                $watchCallback = $callback;

                return true;
            }));

        $liveReloadServer->shouldReceive('start')->once();

        // Mock the loop
        Loop::set($this->createMockLoop());

        // Test Resource file
        $cache->shouldReceive('forget')
            ->once()
            ->with('resource:App\\Http\\Resources\\UserResource');

        $liveReloadServer->shouldReceive('notifyClients')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['event'] === 'documentation-updated';
            }));

        // Start the command
        $this->artisan('prism:watch', ['--no-open' => true]);

        // Simulate file change
        if ($watchCallback) {
            $watchCallback(app_path('Http/Resources/UserResource.php'), 'modified');
        }

        // Test routes file
        $cache->shouldReceive('forget')
            ->once()
            ->with('routes:all');

        $liveReloadServer->shouldReceive('notifyClients')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['event'] === 'documentation-updated';
            }));

        if ($watchCallback) {
            $watchCallback(base_path('routes/api.php'), 'modified');
        }

        // Test controller file (no cache clearing expected)
        $liveReloadServer->shouldReceive('notifyClients')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['event'] === 'documentation-updated';
            }));

        if ($watchCallback) {
            $watchCallback(app_path('Http/Controllers/UserController.php'), 'modified');
        }
    }

    public function test_watch_paths_from_config(): void
    {
        config([
            'prism.watch.paths' => [
                '/custom/path1',
                '/custom/path2',
            ],
        ]);

        $command = new WatchCommand(
            Mockery::mock(FileWatcher::class),
            Mockery::mock(LiveReloadServer::class),
            Mockery::mock(DocumentationCache::class)
        );

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getWatchPaths');
        $method->setAccessible(true);

        $paths = $method->invoke($command);

        $this->assertEquals(['/custom/path1', '/custom/path2'], $paths);
    }

    private function createMockLoop()
    {
        $loop = Mockery::mock(\React\EventLoop\LoopInterface::class);
        $loop->shouldReceive('run')->andReturnNull();
        $loop->shouldReceive('stop')->andReturnNull();
        $loop->shouldReceive('addTimer')->andReturnNull();
        $loop->shouldReceive('addPeriodicTimer')->andReturnNull();
        $loop->shouldReceive('futureTick')->andReturnNull();

        return $loop;
    }

    protected function getPackageProviders($app): array
    {
        return ['LaravelPrism\\PrismServiceProvider'];
    }
}
