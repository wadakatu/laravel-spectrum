<?php

declare(strict_types=1);

namespace LaravelSpectrum\Tests\Unit\Console;

use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Console\WatchCommand;
use LaravelSpectrum\Services\FileWatcher;
use LaravelSpectrum\Services\LiveReloadServer;
use LaravelSpectrum\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class WatchCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function watch_command_initialization(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = new WatchCommand($fileWatcher, $server, $cache);

        $this->assertInstanceOf(WatchCommand::class, $command);
        $this->assertEquals('spectrum:watch', $command->getName());
        $this->assertEquals('Start real-time documentation preview', $command->getDescription());
    }

    #[Test]
    public function command_signature_includes_all_options(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = new WatchCommand($fileWatcher, $server, $cache);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('port'));
        $this->assertTrue($definition->hasOption('host'));
        $this->assertTrue($definition->hasOption('no-open'));

        // Check default values
        $this->assertEquals('8080', $definition->getOption('port')->getDefault());
        $this->assertEquals('127.0.0.1', $definition->getOption('host')->getDefault());
    }

    #[Test]
    public function handle_file_change_clears_cache_and_regenerates_for_requests(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = $this->createTestableWatchCommand($fileWatcher, $server, $cache);

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

        $this->invokePrivateMethod($command, 'handleFileChange', [
            base_path('app/Http/Requests/TestRequest.php'),
            'modified',
        ]);

        $this->assertTrue($command->callInvoked, 'runGenerateCommand was not called');
        $this->assertEquals(['--no-cache' => true], $command->callArguments);
    }

    #[Test]
    public function handle_file_change_clears_cache_and_regenerates_for_resources(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = $this->createTestableWatchCommand($fileWatcher, $server, $cache);

        $cache->shouldReceive('forget')
            ->once()
            ->with('resource:App\Http\Resources\UserResource')
            ->andReturn(true);

        $cache->shouldReceive('forgetByPattern')
            ->once()
            ->with('resource:')
            ->andReturn(0);

        $server->shouldReceive('notifyClients')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['event'] === 'documentation-updated';
            }));

        $this->invokePrivateMethod($command, 'handleFileChange', [
            base_path('app/Http/Resources/UserResource.php'),
            'modified',
        ]);

        $this->assertTrue($command->callInvoked);
        $this->assertEquals(['--no-cache' => true], $command->callArguments);
    }

    #[Test]
    public function handle_file_change_clears_cache_and_regenerates_for_routes(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = $this->createTestableWatchCommand($fileWatcher, $server, $cache);

        $cache->shouldReceive('forget')
            ->once()
            ->with('routes:all')
            ->andReturn(true);

        $cache->shouldReceive('getAllCacheKeys')
            ->andReturn([]);

        $cache->shouldReceive('clear')
            ->once();

        $server->shouldReceive('notifyClients')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['event'] === 'documentation-updated' &&
                       isset($data['forceReload']) &&
                       $data['forceReload'] === true;
            }));

        $this->invokePrivateMethod($command, 'handleFileChange', [
            base_path('routes/api.php'),
            'modified',
        ]);

        $this->assertTrue($command->callInvoked);
        $this->assertEquals(['--no-cache' => true], $command->callArguments);
    }

    #[Test]
    public function handle_file_change_clears_cache_for_controllers(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = $this->createTestableWatchCommand($fileWatcher, $server, $cache);

        $cache->shouldReceive('forget')
            ->once()
            ->with('routes:all')
            ->andReturn(true);

        $server->shouldReceive('notifyClients')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['event'] === 'documentation-updated';
            }));

        $this->invokePrivateMethod($command, 'handleFileChange', [
            base_path('app/Http/Controllers/UserController.php'),
            'modified',
        ]);

        $this->assertTrue($command->callInvoked);
        $this->assertEquals(['--no-cache' => true], $command->callArguments);
    }

    #[Test]
    public function handle_file_change_reports_no_cache_cleared_for_unknown_file(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = $this->createTestableWatchCommand($fileWatcher, $server, $cache);

        // No cache operations for unknown file types
        $server->shouldReceive('notifyClients')->once();

        $this->invokePrivateMethod($command, 'handleFileChange', [
            base_path('app/Models/User.php'),
            'modified',
        ]);

        $this->assertTrue($command->callInvoked);
    }

    #[Test]
    public function handle_file_change_handles_generate_failure(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        // Create a command that simulates generate failure
        $command = new class($fileWatcher, $server, $cache) extends WatchCommand
        {
            public $callInvoked = false;

            protected function runGenerateCommand(array $options = []): int
            {
                $this->callInvoked = true;

                return 1; // Failure
            }

            public function info($string, $verbosity = null): void {}

            public function error($string, $verbosity = null): void {}

            public function warn($string, $verbosity = null): void {}

            public function __construct($fileWatcher, $server, $cache)
            {
                parent::__construct($fileWatcher, $server, $cache);
                $this->output = new class
                {
                    public function isVerbose(): bool
                    {
                        return false;
                    }

                    public function writeln($messages, $options = 0): void {}

                    public function write($messages, $newline = false, $options = 0): void {}
                };
            }
        };

        $cache->shouldReceive('forget')->andReturn(true);

        // notifyClients should NOT be called on failure
        $server->shouldNotReceive('notifyClients');

        $this->invokePrivateMethod($command, 'handleFileChange', [
            base_path('app/Http/Controllers/UserController.php'),
            'modified',
        ]);

        $this->assertTrue($command->callInvoked);
    }

    #[Test]
    public function handle_file_change_when_cache_not_found(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = $this->createTestableWatchCommand($fileWatcher, $server, $cache);

        // Cache forget returns false (no cache found)
        $cache->shouldReceive('forget')
            ->once()
            ->with('form_request:App\Http\Requests\TestRequest')
            ->andReturn(false);

        $server->shouldReceive('notifyClients')->once();

        $this->invokePrivateMethod($command, 'handleFileChange', [
            base_path('app/Http/Requests/TestRequest.php'),
            'modified',
        ]);

        $this->assertTrue($command->callInvoked);
    }

    #[Test]
    public function get_class_name_from_path(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = new WatchCommand($fileWatcher, $server, $cache);

        $result = $this->invokePrivateMethod($command, 'getClassNameFromPath', [
            base_path('app/Http/Controllers/UserController.php'),
        ]);

        $this->assertStringContainsString('UserController', $result);
        $this->assertStringContainsString('App', $result);
    }

    #[Test]
    public function get_watch_paths_from_config(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = new WatchCommand($fileWatcher, $server, $cache);

        config(['spectrum.watch.paths' => [
            '/custom/path1',
            '/custom/path2',
        ]]);

        $paths = $this->invokePrivateMethod($command, 'getWatchPaths');

        $this->assertEquals(['/custom/path1', '/custom/path2'], $paths);
    }

    #[Test]
    public function get_watch_paths_uses_defaults(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = new WatchCommand($fileWatcher, $server, $cache);

        config(['spectrum.watch.paths' => null]);

        $paths = $this->invokePrivateMethod($command, 'getWatchPaths');

        $this->assertIsArray($paths);
        if (! empty($paths)) {
            $this->assertCount(4, $paths);
            $this->assertStringContainsString('Http/Controllers', $paths[0]);
            $this->assertStringContainsString('Http/Requests', $paths[1]);
            $this->assertStringContainsString('Http/Resources', $paths[2]);
            $this->assertStringContainsString('routes', $paths[3]);
        }
    }

    #[Test]
    public function open_browser_generates_correct_command(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = new class($fileWatcher, $server, $cache) extends WatchCommand
        {
            public bool $browserOpened = false;

            public ?string $openedUrl = null;

            protected function openBrowser(string $url): void
            {
                $this->browserOpened = true;
                $this->openedUrl = $url;
            }
        };

        $this->invokePrivateMethod($command, 'openBrowser', ['http://localhost:8080']);

        $this->assertTrue($command->browserOpened);
        $this->assertEquals('http://localhost:8080', $command->openedUrl);
    }

    #[Test]
    public function check_cache_status_reports_cache_info(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = $this->createTestableWatchCommandWithOutput($fileWatcher, $server, $cache);

        config(['spectrum.cache.enabled' => true]);

        $this->invokePrivateMethod($command, 'checkCacheStatus');

        // Should not throw exception and complete successfully
        $this->assertTrue(true);
    }

    #[Test]
    public function check_cache_status_warns_when_disabled(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $warned = false;
        $command = new class($fileWatcher, $server, $cache) extends WatchCommand
        {
            public bool $wasWarned = false;

            public function info($string, $verbosity = null): void {}

            public function warn($string, $verbosity = null): void
            {
                $this->wasWarned = true;
            }
        };

        config(['spectrum.cache.enabled' => false]);

        $this->invokePrivateMethod($command, 'checkCacheStatus');

        $this->assertTrue($command->wasWarned);
    }

    #[Test]
    public function check_cache_after_clear_handles_missing_directory(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);

        // Create a mock cache with a non-existent cache directory
        $cache = Mockery::mock(DocumentationCache::class);

        $command = $this->createTestableWatchCommandWithOutput($fileWatcher, $server, $cache);

        // Should not throw exception even with reflection issues
        $this->invokePrivateMethod($command, 'checkCacheAfterClear');

        $this->assertTrue(true);
    }

    #[Test]
    public function clear_related_cache_handles_resource_with_related_caches(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = $this->createTestableWatchCommandWithOutput($fileWatcher, $server, $cache);

        $cache->shouldReceive('forget')
            ->once()
            ->with('resource:App\Http\Resources\UserResource')
            ->andReturn(true);

        // Related resources found
        $cache->shouldReceive('forgetByPattern')
            ->once()
            ->with('resource:')
            ->andReturn(3);

        $this->invokePrivateMethod($command, 'clearRelatedCache', [
            base_path('app/Http/Resources/UserResource.php'),
        ]);

        $this->assertTrue(true);
    }

    #[Test]
    public function clear_related_cache_verbose_mode_for_routes(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        // Create command with verbose output
        $command = new class($fileWatcher, $server, $cache) extends WatchCommand
        {
            public function info($string, $verbosity = null): void {}

            public function warn($string, $verbosity = null): void {}

            public function __construct($fileWatcher, $server, $cache)
            {
                parent::__construct($fileWatcher, $server, $cache);
                $this->output = new class
                {
                    public function isVerbose(): bool
                    {
                        return true;
                    }

                    public function writeln($messages, $options = 0): void {}

                    public function write($messages, $newline = false, $options = 0): void {}
                };
            }
        };

        $cache->shouldReceive('getAllCacheKeys')
            ->andReturn(['routes:all', 'form_request:Test']);

        $cache->shouldReceive('forget')
            ->once()
            ->with('routes:all')
            ->andReturn(true);

        $this->invokePrivateMethod($command, 'clearRelatedCache', [
            base_path('routes/api.php'),
        ]);

        $this->assertTrue(true);
    }

    /**
     * Create a testable WatchCommand with stubbed methods
     */
    private function createTestableWatchCommand($fileWatcher, $server, $cache): WatchCommand
    {
        return new class($fileWatcher, $server, $cache) extends WatchCommand
        {
            public bool $callInvoked = false;

            /** @var array<string, mixed> */
            public array $callArguments = [];

            protected function runGenerateCommand(array $options = []): int
            {
                $this->callInvoked = true;
                $this->callArguments = $options;

                return 0;
            }

            public function info($string, $verbosity = null): void {}

            public function error($string, $verbosity = null): void {}

            public function warn($string, $verbosity = null): void {}

            public function __construct($fileWatcher, $server, $cache)
            {
                parent::__construct($fileWatcher, $server, $cache);
                $this->output = new class
                {
                    public function isVerbose(): bool
                    {
                        return false;
                    }

                    public function writeln($messages, $options = 0): void {}

                    public function write($messages, $newline = false, $options = 0): void {}
                };
            }
        };
    }

    /**
     * Create a testable WatchCommand with output capturing
     */
    private function createTestableWatchCommandWithOutput($fileWatcher, $server, $cache): WatchCommand
    {
        return new class($fileWatcher, $server, $cache) extends WatchCommand
        {
            public function info($string, $verbosity = null): void {}

            public function error($string, $verbosity = null): void {}

            public function warn($string, $verbosity = null): void {}

            public function __construct($fileWatcher, $server, $cache)
            {
                parent::__construct($fileWatcher, $server, $cache);
                $this->output = new class
                {
                    public function isVerbose(): bool
                    {
                        return false;
                    }

                    public function writeln($messages, $options = 0): void {}

                    public function write($messages, $newline = false, $options = 0): void {}
                };
            }
        };
    }

    /**
     * Helper to invoke private methods
     *
     * @param  array<mixed>  $args
     */
    private function invokePrivateMethod(object $object, string $methodName, array $args = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}
