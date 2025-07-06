<?php

namespace LaravelPrism\Tests\Unit\Console;

use LaravelPrism\Services\DocumentationCache;
use LaravelPrism\Services\FileWatcher;
use LaravelPrism\Services\LiveReloadServer;
use Mockery;
use Orchestra\Testbench\TestCase;
use React\EventLoop\Loop;

class WatchCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        Loop::stop();
    }

    public function test_watch_command_starts_services(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $liveReloadServer = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $this->app->instance(FileWatcher::class, $fileWatcher);
        $this->app->instance(LiveReloadServer::class, $liveReloadServer);
        $this->app->instance(DocumentationCache::class, $cache);

        // Expect server to start
        $liveReloadServer->shouldReceive('start')
            ->once()
            ->with('127.0.0.1', 8080);

        // Expect file watcher to watch the correct paths
        $fileWatcher->shouldReceive('watch')
            ->once()
            ->with(Mockery::type('array'), Mockery::type('callable'));

        // Mock the prism:generate command
        $this->artisan('prism:watch', ['--no-open' => true])
            ->expectsOutput('🚀 Starting Laravel Prism preview server...')
            ->expectsOutput('📡 Preview server running at http://127.0.0.1:8080')
            ->expectsOutput('👀 Watching for file changes...')
            ->expectsOutput('Press Ctrl+C to stop');

        // Stop the event loop to prevent hanging
        Loop::addTimer(0.1, function () {
            Loop::stop();
        });
    }

    public function test_watch_command_with_custom_port(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $liveReloadServer = Mockery::mock(LiveReloadServer::class);

        $this->app->instance(FileWatcher::class, $fileWatcher);
        $this->app->instance(LiveReloadServer::class, $liveReloadServer);

        // Expect server to start with custom port
        $liveReloadServer->shouldReceive('start')
            ->once()
            ->with('127.0.0.1', 3000);

        $fileWatcher->shouldReceive('watch')
            ->once();

        $this->artisan('prism:watch', ['--port' => 3000, '--no-open' => true])
            ->expectsOutput('📡 Preview server running at http://127.0.0.1:3000');

        Loop::addTimer(0.1, function () {
            Loop::stop();
        });
    }

    public function test_watch_command_with_custom_host(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $liveReloadServer = Mockery::mock(LiveReloadServer::class);

        $this->app->instance(FileWatcher::class, $fileWatcher);
        $this->app->instance(LiveReloadServer::class, $liveReloadServer);

        // Expect server to start with custom host
        $liveReloadServer->shouldReceive('start')
            ->once()
            ->with('0.0.0.0', 8080);

        $fileWatcher->shouldReceive('watch')
            ->once();

        $this->artisan('prism:watch', ['--host' => '0.0.0.0', '--no-open' => true])
            ->expectsOutput('📡 Preview server running at http://0.0.0.0:8080');

        Loop::addTimer(0.1, function () {
            Loop::stop();
        });
    }

    public function test_handles_file_change_events(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $liveReloadServer = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $this->app->instance(FileWatcher::class, $fileWatcher);
        $this->app->instance(LiveReloadServer::class, $liveReloadServer);
        $this->app->instance(DocumentationCache::class, $cache);

        $liveReloadServer->shouldReceive('start')->once();

        $watchCallback = null;
        $fileWatcher->shouldReceive('watch')
            ->once()
            ->with(Mockery::type('array'), Mockery::on(function ($callback) use (&$watchCallback) {
                $watchCallback = $callback;

                return true;
            }));

        // Start the command
        $command = $this->artisan('prism:watch', ['--no-open' => true]);

        // Simulate a file change
        if ($watchCallback) {
            // Test FormRequest file change
            $testPath = app_path('Http/Requests/TestRequest.php');
            $cache->shouldReceive('forget')
                ->once()
                ->with('form_request:App\\Http\\Requests\\TestRequest');

            $liveReloadServer->shouldReceive('notifyClients')
                ->once()
                ->with(Mockery::on(function ($data) use ($testPath) {
                    return $data['event'] === 'documentation-updated' &&
                           $data['path'] === $testPath;
                }));

            // Call the callback as if a file changed
            $watchCallback($testPath, 'modified');
        }

        Loop::addTimer(0.1, function () {
            Loop::stop();
        });
    }

    public function test_clears_correct_cache_for_different_file_types(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $liveReloadServer = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $this->app->instance(FileWatcher::class, $fileWatcher);
        $this->app->instance(LiveReloadServer::class, $liveReloadServer);
        $this->app->instance(DocumentationCache::class, $cache);

        $liveReloadServer->shouldReceive('start')->once();
        $liveReloadServer->shouldReceive('notifyClients')->times(3);

        $watchCallback = null;
        $fileWatcher->shouldReceive('watch')
            ->once()
            ->with(Mockery::type('array'), Mockery::on(function ($callback) use (&$watchCallback) {
                $watchCallback = $callback;

                return true;
            }));

        $this->artisan('prism:watch', ['--no-open' => true]);

        if ($watchCallback) {
            // Test Resource file
            $resourcePath = app_path('Http/Resources/UserResource.php');
            $cache->shouldReceive('forget')
                ->once()
                ->with('resource:App\\Http\\Resources\\UserResource');

            $watchCallback($resourcePath, 'modified');

            // Test routes file
            $routePath = base_path('routes/api.php');
            $cache->shouldReceive('forget')
                ->once()
                ->with('routes:all');

            $watchCallback($routePath, 'modified');

            // Test controller file (no specific cache clearing)
            $controllerPath = app_path('Http/Controllers/UserController.php');
            $cache->shouldReceive('forget')->never();

            $watchCallback($controllerPath, 'modified');
        }

        Loop::addTimer(0.1, function () {
            Loop::stop();
        });
    }

    protected function getPackageProviders($app): array
    {
        return ['LaravelPrism\\PrismServiceProvider'];
    }
}
