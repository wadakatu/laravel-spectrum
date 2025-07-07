<?php

namespace LaravelPrism\Console;

use Illuminate\Console\Command;
use LaravelPrism\Services\DocumentationCache;
use LaravelPrism\Services\FileWatcher;
use LaravelPrism\Services\LiveReloadServer;
use Workerman\Worker;

class WatchCommand extends Command
{
    protected $signature = 'prism:watch
                            {--port=8080 : Port for the preview server}
                            {--host=127.0.0.1 : Host for the preview server}
                            {--no-open : Don\'t open browser automatically}';

    protected $description = 'Start real-time documentation preview';

    private FileWatcher $watcher;

    private LiveReloadServer $server;

    private DocumentationCache $cache;

    public function __construct(FileWatcher $watcher, LiveReloadServer $server, DocumentationCache $cache)
    {
        parent::__construct();
        $this->watcher = $watcher;
        $this->server = $server;
        $this->cache = $cache;
    }

    public function handle(): int
    {
        $host = (string) $this->option('host');
        $port = (int) $this->option('port');

        $this->info('🚀 Starting Laravel Prism preview server...');

        // Initial generation
        $this->call('prism:generate', ['--quiet' => true]);

        // Open browser
        if (! $this->option('no-open')) {
            $this->openBrowser("http://{$host}:{$port}");
        }

        $this->info("📡 Preview server running at http://{$host}:{$port}");
        $this->info('👀 Watching for file changes...');
        $this->info('Press Ctrl+C to stop');

        // Create a worker for file watching
        $watchWorker = new Worker();
        $watchWorker->name = 'FileWatcher';
        $watchWorker->onWorkerStart = function() {
            // Start file watching
            $this->watcher->watch($this->getWatchPaths(), function ($path, $event) {
                $this->handleFileChange($path, $event);
            });
        };

        // Start server and workers
        // This will block and run the event loop
        $this->server->start($host, $port);

        return 0;
    }

    private function handleFileChange(string $path, string $event): void
    {
        $this->info("📝 File {$event}: {$path}");

        // Clear related cache
        $this->clearRelatedCache($path);

        // Regenerate (incremental)
        $startTime = microtime(true);
        $this->call('prism:generate', ['--quiet' => true]);
        $duration = round(microtime(true) - $startTime, 2);

        $this->info("✅ Documentation updated in {$duration}s");

        // Notify via WebSocket
        $this->server->notifyClients([
            'event' => 'documentation-updated',
            'path' => $path,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    private function getWatchPaths(): array
    {
        return config('prism.watch.paths', [
            app_path('Http/Controllers'),
            app_path('Http/Requests'),
            app_path('Http/Resources'),
            base_path('routes'),
        ]);
    }

    private function clearRelatedCache(string $path): void
    {
        // For FormRequests
        if (str_contains($path, 'Requests')) {
            $className = $this->getClassNameFromPath($path);
            $this->cache->forget("form_request:{$className}");
        }

        // For Resources
        elseif (str_contains($path, 'Resources')) {
            $className = $this->getClassNameFromPath($path);
            $this->cache->forget("resource:{$className}");
        }

        // For route files
        elseif (str_contains($path, 'routes')) {
            $this->cache->forget('routes:all');
        }
    }

    private function getClassNameFromPath(string $path): string
    {
        // Convert file path to class name
        $relativePath = str_replace(base_path().'/', '', $path);
        $relativePath = str_replace('.php', '', $relativePath);
        $relativePath = str_replace('/', '\\', $relativePath);

        // Convert to proper namespace
        if (str_starts_with($relativePath, 'app\\')) {
            $relativePath = 'App\\'.substr($relativePath, 4);
        }

        return $relativePath;
    }

    private function openBrowser(string $url): void
    {
        $command = match (PHP_OS_FAMILY) {
            'Darwin' => "open {$url}",
            'Windows' => "start {$url}",
            'Linux' => "xdg-open {$url}",
            default => null,
        };

        if ($command) {
            exec($command);
        }
    }
}