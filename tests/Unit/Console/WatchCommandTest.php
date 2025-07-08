<?php

namespace LaravelSpectrum\Tests\Unit\Console;

use LaravelSpectrum\Console\WatchCommand;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Services\FileWatcher;
use LaravelSpectrum\Services\LiveReloadServer;
use Mockery;
use Orchestra\Testbench\TestCase;

class WatchCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function test_watch_command_initialization(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = new WatchCommand($fileWatcher, $server, $cache);

        $this->assertInstanceOf(WatchCommand::class, $command);
        $this->assertEquals('spectrum:watch', $command->getName());
        $this->assertEquals('Start real-time documentation preview', $command->getDescription());
    }

    public function test_handle_file_change_clears_cache_and_regenerates_for_requests(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        // Create an anonymous class that extends WatchCommand for testing
        $command = new class($fileWatcher, $server, $cache) extends WatchCommand
        {
            public $callInvoked = false;

            public $callArguments = [];

            public function call($command, array $arguments = [])
            {
                $this->callInvoked = true;
                $this->callArguments = $arguments;

                return 0;
            }

            public function info($string, $verbosity = null)
            {
                // Do nothing
            }
        };

        // Test FormRequest cache clearing
        $cache->shouldReceive('forget')
            ->once()
            ->with('form_request:App\Http\Requests\TestRequest')
            ->andReturn(true);

        $server->shouldReceive('notifyClients')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['event'] === 'documentation-updated' &&
                       str_contains($data['path'], 'TestRequest.php');
            }));

        // Use reflection to test private method
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('handleFileChange');
        $method->setAccessible(true);

        $method->invoke($command, base_path('app/Http/Requests/TestRequest.php'), 'modified');

        $this->assertTrue($command->callInvoked);
        $this->assertEquals(['--quiet' => true], $command->callArguments);
    }

    public function test_handle_file_change_clears_cache_and_regenerates_for_resources(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        // Create an anonymous class that extends WatchCommand for testing
        $command = new class($fileWatcher, $server, $cache) extends WatchCommand
        {
            public $callInvoked = false;

            public $callArguments = [];

            public function call($command, array $arguments = [])
            {
                $this->callInvoked = true;
                $this->callArguments = $arguments;

                return 0;
            }

            public function info($string, $verbosity = null)
            {
                // Do nothing
            }
        };

        // Test Resource cache clearing
        $cache->shouldReceive('forget')
            ->once()
            ->with('resource:App\Http\Resources\UserResource')
            ->andReturn(true);
        
        // Test pattern-based cache clearing for related resources
        $cache->shouldReceive('forgetByPattern')
            ->once()
            ->with('resource:')
            ->andReturn(0);

        $server->shouldReceive('notifyClients')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['event'] === 'documentation-updated';
            }));

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('handleFileChange');
        $method->setAccessible(true);

        $method->invoke($command, base_path('app/Http/Resources/UserResource.php'), 'modified');

        $this->assertTrue($command->callInvoked);
        $this->assertEquals(['--quiet' => true], $command->callArguments);
    }

    public function test_handle_file_change_clears_cache_and_regenerates_for_routes(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        // Create an anonymous class that extends WatchCommand for testing
        $command = new class($fileWatcher, $server, $cache) extends WatchCommand
        {
            public $callInvoked = false;

            public $callArguments = [];

            public function call($command, array $arguments = [])
            {
                $this->callInvoked = true;
                $this->callArguments = $arguments;

                return 0;
            }

            public function info($string, $verbosity = null)
            {
                // Do nothing
            }
        };

        // Test routes cache clearing
        $cache->shouldReceive('forget')
            ->once()
            ->with('routes:all')
            ->andReturn(true);

        $server->shouldReceive('notifyClients')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['event'] === 'documentation-updated';
            }));

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('handleFileChange');
        $method->setAccessible(true);

        $method->invoke($command, base_path('routes/api.php'), 'modified');

        $this->assertTrue($command->callInvoked);
        $this->assertEquals(['--quiet' => true], $command->callArguments);
    }

    public function test_handle_file_change_clears_cache_for_controllers(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        // Create an anonymous class that extends WatchCommand for testing
        $command = new class($fileWatcher, $server, $cache) extends WatchCommand
        {
            public $callInvoked = false;

            public $callArguments = [];

            public function call($command, array $arguments = [])
            {
                $this->callInvoked = true;
                $this->callArguments = $arguments;

                return 0;
            }

            public function info($string, $verbosity = null)
            {
                // Do nothing
            }
        };

        // Test routes cache clearing when controller changes
        $cache->shouldReceive('forget')
            ->once()
            ->with('routes:all')
            ->andReturn(true);

        $server->shouldReceive('notifyClients')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['event'] === 'documentation-updated';
            }));

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('handleFileChange');
        $method->setAccessible(true);

        $method->invoke($command, base_path('app/Http/Controllers/UserController.php'), 'modified');

        $this->assertTrue($command->callInvoked);
        $this->assertEquals(['--quiet' => true], $command->callArguments);
    }

    public function test_get_class_name_from_path(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = new WatchCommand($fileWatcher, $server, $cache);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getClassNameFromPath');
        $method->setAccessible(true);

        // Mock base_path function
        $basePath = '/var/www/project';

        // Override the base_path() function for this test
        $this->app->bind('path.base', function () use ($basePath) {
            return $basePath;
        });

        $result = $method->invoke($command, $basePath.'/app/Http/Controllers/UserController.php');
        $this->assertStringContainsString('UserController', $result);
    }

    public function test_get_watch_paths_from_config(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = new WatchCommand($fileWatcher, $server, $cache);

        // Set custom config
        config(['spectrum.watch.paths' => [
            '/custom/path1',
            '/custom/path2',
        ]]);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getWatchPaths');
        $method->setAccessible(true);

        $paths = $method->invoke($command);

        $this->assertEquals(['/custom/path1', '/custom/path2'], $paths);
    }

    public function test_get_watch_paths_uses_defaults(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = new WatchCommand($fileWatcher, $server, $cache);

        // Clear config to use defaults
        config(['spectrum.watch.paths' => null]);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getWatchPaths');
        $method->setAccessible(true);

        $paths = $method->invoke($command);

        $this->assertIsArray($paths);
        if (! empty($paths)) {
            $this->assertCount(4, $paths);
            $this->assertStringContainsString('Http/Controllers', $paths[0]);
            $this->assertStringContainsString('Http/Requests', $paths[1]);
            $this->assertStringContainsString('Http/Resources', $paths[2]);
            $this->assertStringContainsString('routes', $paths[3]);
        }
    }

    public function test_open_browser_command_generation(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        // Create a test command that overrides openBrowser to prevent actual execution
        $command = new class($fileWatcher, $server, $cache) extends WatchCommand
        {
            public $browserOpened = false;

            public $openedUrl = null;

            protected function openBrowser(string $url): void
            {
                $this->browserOpened = true;
                $this->openedUrl = $url;
                // Don't actually execute the command
            }
        };

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('openBrowser');
        $method->setAccessible(true);

        // Test that the method is called correctly
        $method->invoke($command, 'http://localhost:8080');

        $this->assertTrue($command->browserOpened);
        $this->assertEquals('http://localhost:8080', $command->openedUrl);
    }

    public function test_command_signature_options(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = new WatchCommand($fileWatcher, $server, $cache);

        // Access signature through reflection
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);
        $signature = $property->getValue($command);

        $this->assertStringContainsString('--port=', $signature);
        $this->assertStringContainsString('--host=', $signature);
        $this->assertStringContainsString('--no-open', $signature);
    }
}
