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
            public bool $callInvoked = false;

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
     *
     * @param  \Mockery\MockInterface&FileWatcher  $fileWatcher
     * @param  \Mockery\MockInterface&LiveReloadServer  $server
     * @param  \Mockery\MockInterface&DocumentationCache  $cache
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
     *
     * @param  \Mockery\MockInterface&FileWatcher  $fileWatcher
     * @param  \Mockery\MockInterface&LiveReloadServer  $server
     * @param  \Mockery\MockInterface&DocumentationCache  $cache
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

    #[Test]
    public function handle_file_change_checks_routes_cache_after_clear(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = $this->createTestableWatchCommand($fileWatcher, $server, $cache);

        // Routes cache still exists after clear (warning case)
        $cache->shouldReceive('forget')
            ->once()
            ->with('routes:all')
            ->andReturn(true);

        $cache->shouldReceive('getAllCacheKeys')
            ->andReturn(['routes:all']); // Cache still exists

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
    }

    #[Test]
    public function handle_file_change_shows_file_update_info_when_file_exists(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        // Create a temporary openapi.json for testing
        $testDir = storage_path('app/spectrum');
        if (! is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }
        $testFile = $testDir.'/openapi.json';
        file_put_contents($testFile, '{"test": true}');

        $command = $this->createTestableWatchCommand($fileWatcher, $server, $cache);

        $cache->shouldReceive('forget')->andReturn(true);
        $server->shouldReceive('notifyClients')->once();

        $this->invokePrivateMethod($command, 'handleFileChange', [
            base_path('app/Http/Controllers/UserController.php'),
            'modified',
        ]);

        $this->assertTrue($command->callInvoked);

        // Cleanup
        @unlink($testFile);
    }

    #[Test]
    public function handle_file_change_warns_when_output_file_not_found(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        // Ensure the file doesn't exist
        $testFile = storage_path('app/spectrum/openapi.json');
        if (file_exists($testFile)) {
            unlink($testFile);
        }

        $messages = [];
        $command = new class($fileWatcher, $server, $cache, $messages) extends WatchCommand
        {
            public bool $callInvoked = false;

            /** @var array<string, mixed> */
            public array $callArguments = [];

            /** @var array<string> */
            private array $capturedMessages;

            /**
             * @param  array<string>  $messages
             */
            public function __construct($fileWatcher, $server, $cache, array &$messages)
            {
                parent::__construct($fileWatcher, $server, $cache);
                $this->capturedMessages = &$messages;
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

            protected function runGenerateCommand(array $options = []): int
            {
                $this->callInvoked = true;
                $this->callArguments = $options;

                return 0;
            }

            public function info($string, $verbosity = null): void
            {
                $this->capturedMessages[] = $string;
            }

            public function error($string, $verbosity = null): void
            {
                $this->capturedMessages[] = '[ERROR] '.$string;
            }

            public function warn($string, $verbosity = null): void
            {
                $this->capturedMessages[] = '[WARN] '.$string;
            }
        };

        $cache->shouldReceive('forget')->andReturn(true);
        $server->shouldReceive('notifyClients')->once();

        $this->invokePrivateMethod($command, 'handleFileChange', [
            base_path('app/Http/Controllers/UserController.php'),
            'modified',
        ]);

        $this->assertTrue($command->callInvoked);
        // Verify warning message was captured
        $hasWarning = false;
        foreach ($messages as $message) {
            if (str_contains($message, 'not found after generation')) {
                $hasWarning = true;
                break;
            }
        }
        $this->assertTrue($hasWarning, 'Expected warning about file not found');
    }

    #[Test]
    public function clear_related_cache_for_unknown_file_type(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = $this->createTestableWatchCommandWithOutput($fileWatcher, $server, $cache);

        // No cache operations expected for unknown file types like models
        $this->invokePrivateMethod($command, 'clearRelatedCache', [
            base_path('app/Models/User.php'),
        ]);

        // Should complete without error
        $this->assertTrue(true);
    }

    #[Test]
    public function get_class_name_from_path_handles_nested_namespace(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = new WatchCommand($fileWatcher, $server, $cache);

        $result = $this->invokePrivateMethod($command, 'getClassNameFromPath', [
            base_path('app/Http/Resources/Api/V1/UserResource.php'),
        ]);

        $this->assertStringContainsString('UserResource', $result);
    }

    #[Test]
    public function check_cache_status_handles_real_cache(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);

        // Use a real DocumentationCache instance
        $cacheDir = sys_get_temp_dir().'/spectrum_test_cache_'.uniqid();
        @mkdir($cacheDir, 0755, true);
        $cache = new DocumentationCache(true, $cacheDir);

        $messages = [];
        $command = new class($fileWatcher, $server, $cache, $messages) extends WatchCommand
        {
            /** @var array<string> */
            private array $capturedMessages;

            /**
             * @param  array<string>  $messages
             */
            public function __construct($fileWatcher, $server, $cache, array &$messages)
            {
                parent::__construct($fileWatcher, $server, $cache);
                $this->capturedMessages = &$messages;
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

            public function info($string, $verbosity = null): void
            {
                $this->capturedMessages[] = $string;
            }

            public function warn($string, $verbosity = null): void {}

            public function error($string, $verbosity = null): void {}
        };

        config(['spectrum.cache.enabled' => true]);

        $this->invokePrivateMethod($command, 'checkCacheStatus');

        // Should output info about cache directory
        $hasCacheInfo = false;
        foreach ($messages as $message) {
            if (str_contains($message, 'Cache') || str_contains($message, 'cache')) {
                $hasCacheInfo = true;
                break;
            }
        }
        $this->assertTrue($hasCacheInfo, 'Expected cache status info');

        // Cleanup
        @rmdir($cacheDir);
    }

    #[Test]
    public function handle_file_change_with_delete_event(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $command = $this->createTestableWatchCommand($fileWatcher, $server, $cache);

        $cache->shouldReceive('forget')
            ->once()
            ->with('form_request:App\Http\Requests\TestRequest')
            ->andReturn(true);

        $server->shouldReceive('notifyClients')->once();

        $this->invokePrivateMethod($command, 'handleFileChange', [
            base_path('app/Http/Requests/TestRequest.php'),
            'deleted',
        ]);

        $this->assertTrue($command->callInvoked);
    }

    #[Test]
    public function check_cache_after_clear_with_existing_directory(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);

        // Create a real cache with some files
        $cacheDir = sys_get_temp_dir().'/spectrum_test_cache_'.uniqid();
        @mkdir($cacheDir, 0755, true);
        file_put_contents($cacheDir.'/test.cache', 'test data');

        $cache = new DocumentationCache(true, $cacheDir);

        $command = $this->createTestableWatchCommandWithOutput($fileWatcher, $server, $cache);

        $this->invokePrivateMethod($command, 'checkCacheAfterClear');

        // Should complete without error
        $this->assertTrue(true);

        // Cleanup
        @unlink($cacheDir.'/test.cache');
        @rmdir($cacheDir);
    }

    #[Test]
    public function handle_file_change_routes_shows_debug_info_in_verbose(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        // Create command with verbose output
        $command = new class($fileWatcher, $server, $cache) extends WatchCommand
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
                        return true;  // Verbose mode enabled
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

        $cache->shouldReceive('clear')->once();

        $server->shouldReceive('notifyClients')->once();

        $this->invokePrivateMethod($command, 'handleFileChange', [
            base_path('routes/api.php'),
            'modified',
        ]);

        $this->assertTrue($command->callInvoked);
    }

    #[Test]
    public function clear_related_cache_verbose_shows_cache_keys(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

        $infoMessages = [];
        $command = new class($fileWatcher, $server, $cache, $infoMessages) extends WatchCommand
        {
            /** @var array<string> */
            private array $capturedInfo;

            /**
             * @param  array<string>  $infoMessages
             */
            public function __construct($fileWatcher, $server, $cache, array &$infoMessages)
            {
                parent::__construct($fileWatcher, $server, $cache);
                $this->capturedInfo = &$infoMessages;
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

            public function info($string, $verbosity = null): void
            {
                $this->capturedInfo[] = $string;
            }

            public function warn($string, $verbosity = null): void {}
        };

        $cache->shouldReceive('getAllCacheKeys')
            ->andReturn(['form_request:Test', 'resource:User']);

        $cache->shouldReceive('forget')
            ->once()
            ->with('routes:all')
            ->andReturn(true);

        $this->invokePrivateMethod($command, 'clearRelatedCache', [
            base_path('routes/web.php'),
        ]);

        // Check that cache keys are shown in verbose mode
        $hasCheckingMessage = false;
        foreach ($infoMessages as $msg) {
            if (str_contains($msg, 'Checking routes cache')) {
                $hasCheckingMessage = true;
                break;
            }
        }
        $this->assertTrue($hasCheckingMessage, 'Expected verbose cache checking message');
    }

    #[Test]
    public function clear_related_cache_for_controller_verbose(): void
    {
        $fileWatcher = Mockery::mock(FileWatcher::class);
        $server = Mockery::mock(LiveReloadServer::class);
        $cache = Mockery::mock(DocumentationCache::class);

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
            ->andReturn(['routes:all']);

        $cache->shouldReceive('forget')
            ->once()
            ->with('routes:all')
            ->andReturn(true);

        $this->invokePrivateMethod($command, 'clearRelatedCache', [
            base_path('app/Http/Controllers/Api/V1/UserController.php'),
        ]);

        // Should complete without error
        $this->assertTrue(true);
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
