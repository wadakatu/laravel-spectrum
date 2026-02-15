<?php

declare(strict_types=1);

namespace LaravelSpectrum\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use LaravelSpectrum\Cache\DocumentationCache;
use LaravelSpectrum\Services\FileWatcher;
use LaravelSpectrum\Services\LiveReloadServer;
use Symfony\Component\Process\Process;
use Workerman\Worker;

class WatchCommand extends Command
{
    protected $signature = 'spectrum:watch
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

        $this->info('🚀 Starting Laravel Spectrum preview server...');

        // キャッシュ状態を確認
        $this->checkCacheStatus();

        // Initial generation (キャッシュ有効)
        $this->info('📄 Generating initial documentation...');
        $this->call('spectrum:generate');

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

        // キャッシュクリア後の確認
        if (str_contains($path, 'routes')) {
            $this->info('  🔍 Verifying routes cache was cleared...');
            $allKeys = $this->cache->getAllCacheKeys();
            $hasRoutesCache = in_array('routes:all', $allKeys);
            $this->info('  📊 Routes cache still exists: '.($hasRoutesCache ? 'Yes ⚠️' : 'No ✅'));

            if ($hasRoutesCache) {
                $this->warn('  ⚠️  Routes cache was not properly cleared!');
            }
        }

        // Regenerate (キャッシュ有効で差分更新)
        $startTime = microtime(true);
        $this->info('  🔄 Regenerating documentation...');

        // 強制的にキャッシュを無効化するオプションを追加
        if (str_contains($path, 'routes')) {
            $this->info('  💨 Forcing route cache refresh...');
            // ルートファイルが変更された場合は、念のためキャッシュディレクトリ全体をクリア
            $this->cache->clear();
            $this->info('  🧹 All caches cleared for route changes');

        }

        $exitCode = $this->runGenerateCommand(['--no-cache' => true]);
        $duration = round(microtime(true) - $startTime, 2);

        if ($exitCode !== 0) {
            $this->error('  ❌ Failed to regenerate documentation');
            $this->error('  💡 Try running the command with verbose mode: php artisan spectrum:watch -vvv');

            // ログファイルの最新エントリを確認
            $logFile = storage_path('logs/laravel.log');
            if (file_exists($logFile)) {
                $lastLines = array_slice(file($logFile), -10);
                if (! empty($lastLines)) {
                    $this->error('  📋 Recent log entries:');
                    foreach ($lastLines as $line) {
                        if (str_contains($line, 'ERROR') || str_contains($line, 'Failed')) {
                            $this->error('    '.trim($line));
                        }
                    }
                }
            }

            return;
        }

        $this->info("✅ Documentation updated in {$duration}s");

        // 生成されたファイルの確認
        $possiblePaths = [];
        if (function_exists('storage_path')) {
            $possiblePaths[] = storage_path('app/spectrum/openapi.json');
        }
        $possiblePaths[] = getcwd().'/storage/spectrum/openapi.json';

        $fileFound = false;
        foreach ($possiblePaths as $jsonPath) {
            if (file_exists($jsonPath)) {
                $fileSize = filesize($jsonPath);
                $this->info("  📄 File updated: {$jsonPath} (".number_format($fileSize).' bytes)');
                $fileFound = true;
                break;
            }
        }

        if (! $fileFound) {
            $this->error('  ⚠️  Warning: openapi.json file not found after generation');
        }

        // Notify via WebSocket
        $notificationData = [
            'event' => 'documentation-updated',
            'path' => $path,
            'timestamp' => now()->toIso8601String(),
        ];

        // ルートファイルが変更された場合は強制リロードフラグを追加
        if (str_contains($path, 'routes')) {
            $notificationData['forceReload'] = true;
        }

        $this->info('  📡 Sending WebSocket notification...');
        $this->server->notifyClients($notificationData);
        $this->info('  ✅ WebSocket notification sent');
    }

    protected function runGenerateCommand(array $options = []): int
    {
        // Build the command array
        $command = [PHP_BINARY, 'artisan', 'spectrum:generate'];

        // Add options
        foreach ($options as $key => $value) {
            if (is_bool($value) && $value) {
                $command[] = $key;
            } elseif (! is_bool($value)) {
                $command[] = $key;
                $command[] = $value;
            }
        }

        // Create the process
        $process = new Process($command, base_path());
        $process->setTimeout(null); // No timeout

        // Run the process with real-time output
        try {
            $process->run(function ($type, $buffer) {
                // Output directly to console
                $this->output->write($buffer);
            });

            return $process->getExitCode();
        } catch (\Exception $e) {
            $this->error('Failed to run generate command: '.$e->getMessage());

            return 1;
        }
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
            // キャッシュクリア前の状態を確認（デバッグ用）
            if ($this->output->isVerbose()) {
                $this->info('  🔍 Checking routes cache before clear...');
                $allKeys = $this->cache->getAllCacheKeys();
                $hasRoutesCache = in_array('routes:all', $allKeys);
                $this->info('  📊 Routes cache exists: '.($hasRoutesCache ? 'Yes' : 'No'));
            }

            if ($this->cache->forget('routes:all')) {
                $clearedCount++;
                $this->info('  🧹 Cleared routes cache');

                // 追加のデバッグ情報
                if ($this->output->isVerbose()) {
                    $this->checkCacheAfterClear();
                }
            } else {
                $this->info('  ℹ️  No routes cache found to clear');
            }
        }

        // For Controllers (コントローラーが変更された場合もルートキャッシュをクリア)
        elseif (str_contains($path, 'Controllers')) {
            // キャッシュクリア前の状態を確認（デバッグ用）
            if ($this->output->isVerbose()) {
                $this->info('  🔍 Checking routes cache before clear (Controller change)...');
                $allKeys = $this->cache->getAllCacheKeys();
                $hasRoutesCache = in_array('routes:all', $allKeys);
                $this->info('  📊 Routes cache exists: '.($hasRoutesCache ? 'Yes' : 'No'));
            }

            if ($this->cache->forget('routes:all')) {
                $clearedCount++;
                $this->info('  🧹 Cleared routes cache (Controller changed)');

                // 追加のデバッグ情報
                if ($this->output->isVerbose()) {
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

                // 全てのキャッシュキーを表示（verboseモード時のみ）
                if ($count > 0 && $this->output->isVerbose()) {
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
