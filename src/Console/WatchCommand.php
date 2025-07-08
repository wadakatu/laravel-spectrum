<?php

namespace LaravelSpectrum\Console;

use Illuminate\Console\Command;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Services\FileWatcher;
use LaravelSpectrum\Services\LiveReloadServer;
use Workerman\Worker;

class WatchCommand extends Command
{
    protected $signature = 'spectrum:watch
                            {--port=8080 : Port for the preview server}
                            {--host=127.0.0.1 : Host for the preview server}
                            {--no-open : Don\'t open browser automatically}
                            {--verbose : Show detailed cache information}';

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

        $this->info('🚀 Starting Laravel Spectrum preview server...');

        // キャッシュ状態を確認
        $this->checkCacheStatus();

        // Initial generation (キャッシュ有効)
        $this->call('spectrum:generate', ['--quiet' => true]);

        // Set WorkerMan to daemon mode for development
        global $argv;
        $argv = ['spectrum:watch', 'start'];

        // Open browser
        if (! $this->option('no-open')) {
            $this->openBrowser("http://{$host}:{$port}");
        }

        $this->info("📡 Preview server running at http://{$host}:{$port}");
        $this->info('👀 Watching for file changes...');
        $this->info('Press Ctrl+C to stop');

        // Create a worker for file watching
        $watchWorker = new Worker;
        $watchWorker->name = 'FileWatcher';
        $watchWorker->onWorkerStart = function () {
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

        // 変更されたファイルに関連するキャッシュのみクリア
        $this->clearRelatedCache($path);

        // Regenerate (キャッシュ有効で差分更新)
        $startTime = microtime(true);
        $this->call('spectrum:generate', ['--quiet' => true]);
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
        return config('spectrum.watch.paths', [
            app_path('Http/Controllers'),
            app_path('Http/Requests'),
            app_path('Http/Resources'),
            base_path('routes'),
        ]) ?? [];
    }

    private function clearRelatedCache(string $path): void
    {
        $clearedCount = 0;

        // For FormRequests
        if (str_contains($path, 'Requests')) {
            $className = $this->getClassNameFromPath($path);
            $cacheKey = "form_request:{$className}";

            if ($this->cache->forget($cacheKey)) {
                $clearedCount++;
                $this->info("  🧹 Cleared cache for FormRequest: {$className}");
            } else {
                $this->info("  ℹ️  No cache found for FormRequest: {$className}");
            }
        }

        // For Resources
        elseif (str_contains($path, 'Resources')) {
            $className = $this->getClassNameFromPath($path);
            $cacheKey = "resource:{$className}";

            if ($this->cache->forget($cacheKey)) {
                $clearedCount++;
                $this->info("  🧹 Cleared cache for Resource: {$className}");
            } else {
                $this->info("  ℹ️  No cache found for Resource: {$className}");
            }

            // Resourceが他のResourceに依存している可能性があるため、
            // このResourceを使用している可能性のある他のResourceのキャッシュもクリア
            $relatedCount = $this->cache->forgetByPattern('resource:');
            if ($relatedCount > 0) {
                $clearedCount += $relatedCount;
                $this->info("  🧹 Cleared {$relatedCount} related Resource caches");
            }
        }

        // For route files
        elseif (str_contains($path, 'routes')) {
            if ($this->cache->forget('routes:all')) {
                $clearedCount++;
                $this->info('  🧹 Cleared routes cache');

                // 追加のデバッグ情報
                if ($this->option('verbose')) {
                    $this->checkCacheAfterClear();
                }
            } else {
                $this->info('  ℹ️  No routes cache found to clear');
            }
        }

        // For Controllers (コントローラーが変更された場合もルートキャッシュをクリア)
        elseif (str_contains($path, 'Controllers')) {
            if ($this->cache->forget('routes:all')) {
                $clearedCount++;
                $this->info('  🧹 Cleared routes cache (Controller changed)');

                // 追加のデバッグ情報
                if ($this->option('verbose')) {
                    $this->checkCacheAfterClear();
                }
            } else {
                $this->info('  ℹ️  No routes cache found to clear (Controller changed)');
            }
        }

        if ($clearedCount === 0) {
            $this->info('  ℹ️  No cache entries were cleared');
        } else {
            $this->info("  ✅ Total cleared: {$clearedCount} cache entries");
        }
    }

    private function checkCacheAfterClear(): void
    {
        try {
            $reflection = new \ReflectionProperty($this->cache, 'cacheDir');
            $reflection->setAccessible(true);
            $cacheDir = $reflection->getValue($this->cache);

            if (is_dir($cacheDir)) {
                $files = glob($cacheDir.'/*.cache');
                $count = count($files);
                $this->info("  📊 Remaining cache entries: {$count}");
            }
        } catch (\Exception $e) {
            // 無視
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

    private function checkCacheStatus(): void
    {
        $cacheEnabled = config('spectrum.cache.enabled', true);

        if (! $cacheEnabled) {
            $this->warn('⚠️  Cache is disabled. Enable it in config/spectrum.php for better performance.');

            return;
        }

        // DocumentationCacheのstatusを確認
        try {
            $reflection = new \ReflectionProperty($this->cache, 'enabled');
            $reflection->setAccessible(true);
            $isEnabled = $reflection->getValue($this->cache);

            $reflection = new \ReflectionProperty($this->cache, 'cacheDir');
            $reflection->setAccessible(true);
            $cacheDir = $reflection->getValue($this->cache);

            $this->info("📁 Cache directory: {$cacheDir}");
            $this->info('💾 Cache enabled: '.($isEnabled ? 'Yes' : 'No'));

            if (is_dir($cacheDir)) {
                $files = glob($cacheDir.'/*.cache');
                $count = count($files);
                $this->info("📊 Cached entries: {$count}");

                // 全てのキャッシュキーを表示
                if ($count > 0) {
                    $keys = $this->cache->getAllCacheKeys();
                    $this->info('📋 Cache keys:');
                    foreach ($keys as $key) {
                        $this->info("   - {$key}");
                    }
                }
            } else {
                $this->info('📊 Cache directory does not exist yet');
            }
        } catch (\Exception $e) {
            $this->error('Failed to check cache status: '.$e->getMessage());
        }
    }
}
