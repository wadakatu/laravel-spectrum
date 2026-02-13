<?php

declare(strict_types=1);

namespace LaravelSpectrum\Cache;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * @phpstan-type DependencyList list<string>
 * @phpstan-type DependencyHashMap array<string, string>
 * @phpstan-type CacheMetadata array{key: string, created_at: string, dependencies: DependencyHashMap}
 * @phpstan-type CacheData array{version: int, metadata: CacheMetadata, data: mixed}
 */
class DocumentationCache
{
    /**
     * Cache format version.
     * Increment this when the cache data structure changes
     * (e.g., adding/removing/renaming keys in the cacheData array,
     * changing serialization format, or modifying metadata structure).
     */
    private const CACHE_VERSION = 1;

    private string $cacheDir;

    private bool $enabled;

    public function __construct()
    {
        // パッケージ開発環境と通常環境の両方に対応
        $defaultCacheDir = function_exists('storage_path')
            ? storage_path('app/spectrum/cache')
            : getcwd().'/storage/spectrum/cache';

        $this->cacheDir = config('spectrum.cache.directory', $defaultCacheDir);
        $this->enabled = config('spectrum.cache.enabled', true);

        if ($this->enabled) {
            File::ensureDirectoryExists($this->cacheDir);
        }
    }

    /**
     * キャッシュから取得、なければコールバックを実行
     *
     * @param  DependencyList  $dependencies
     */
    public function remember(string $key, \Closure $callback, array $dependencies = []): mixed
    {
        if (! $this->enabled) {
            return $callback();
        }

        // まずキャッシュの存在を確認
        $cached = $this->get($key);

        // キャッシュが存在する場合のみ依存関係をチェック
        if ($cached !== null) {
            // 依存ファイルが変更されていないかチェック
            if (! $this->isDependenciesChanged($key, $dependencies)) {
                return $cached;
            }
        }

        // キャッシュが存在しないか、依存関係が変更された場合は再生成
        $result = $callback();
        $this->put($key, $result, $dependencies);

        return $result;
    }

    /**
     * FormRequest解析結果をキャッシュ
     *
     * @return array<string, mixed>
     */
    public function rememberFormRequest(string $requestClass, \Closure $callback): array
    {
        if (! class_exists($requestClass)) {
            return $callback();
        }

        $reflection = new \ReflectionClass($requestClass);
        $filePath = $reflection->getFileName();

        $key = 'form_request:'.$requestClass;
        $dependencies = $filePath ? [$filePath] : [];

        return $this->remember($key, $callback, $dependencies);
    }

    /**
     * Resource解析結果をキャッシュ
     *
     * @return array<string, mixed>
     */
    public function rememberResource(string $resourceClass, \Closure $callback): array
    {
        if (! class_exists($resourceClass)) {
            return $callback();
        }

        $reflection = new \ReflectionClass($resourceClass);
        $filePath = $reflection->getFileName();

        $key = 'resource:'.$resourceClass;
        $dependencies = $filePath ? [$filePath] : [];

        // Resourceが使用する他のResourceも依存関係に追加
        if ($filePath) {
            $additionalDeps = $this->findResourceDependencies($filePath);
            $dependencies = array_merge($dependencies, $additionalDeps);
        }

        return $this->remember($key, $callback, $dependencies);
    }

    /**
     * ルート解析結果をキャッシュ
     *
     * @return array<int, array<string, mixed>>
     */
    public function rememberRoutes(\Closure $callback): array
    {
        $routeFiles = [
            base_path('routes/api.php'),
            base_path('routes/web.php'),
            base_path('config/spectrum.php'),
        ];

        // カスタムルートファイルも含める
        $customRoutes = config('spectrum.route_files', []);
        $routeFiles = array_merge($routeFiles, $customRoutes);

        $existingFiles = array_filter($routeFiles, 'file_exists');

        return $this->remember('routes:all', $callback, $existingFiles);
    }

    /**
     * 依存ファイルが変更されているかチェック
     *
     * @param  DependencyList  $dependencies
     */
    private function isDependenciesChanged(string $key, array $dependencies): bool
    {
        $metadata = $this->getMetadata($key);

        if (! $metadata) {
            return true; // メタデータがない = 初回
        }

        foreach ($dependencies as $file) {
            if (! file_exists($file)) {
                return true; // ファイルが削除された
            }

            $currentHash = $this->hashFile($file);
            $cachedHash = $metadata['dependencies'][$file] ?? null;

            if ($currentHash !== $cachedHash) {
                return true; // ファイルが変更された
            }
        }

        return false;
    }

    /**
     * ファイルのハッシュ値を計算
     */
    private function hashFile(string $path): string
    {
        return md5_file($path).':'.filemtime($path);
    }

    /**
     * キャッシュに保存
     *
     * @param  DependencyList  $dependencies
     */
    private function put(string $key, mixed $data, array $dependencies): void
    {
        $cacheFile = $this->getCachePath($key);

        $metadata = [
            'key' => $key,
            'created_at' => now()->toIso8601String(),
            'dependencies' => [],
        ];

        foreach ($dependencies as $file) {
            if (file_exists($file)) {
                $metadata['dependencies'][$file] = $this->hashFile($file);
            }
        }

        $cacheData = [
            'version' => self::CACHE_VERSION,
            'metadata' => $metadata,
            'data' => $data,
        ];

        File::put($cacheFile, serialize($cacheData));
    }

    /**
     * キャッシュから取得
     */
    private function get(string $key): mixed
    {
        $cacheFile = $this->getCachePath($key);

        if (! File::exists($cacheFile)) {
            return null;
        }

        try {
            $cacheData = unserialize(File::get($cacheFile));

            // バージョンが異なる場合は古いキャッシュとして削除
            $cachedVersion = $cacheData['version'] ?? 0;
            if ($cachedVersion !== self::CACHE_VERSION) {
                Log::debug('DocumentationCache: Invalidating cache due to version mismatch', [
                    'key' => $key,
                    'cached_version' => $cachedVersion,
                    'current_version' => self::CACHE_VERSION,
                ]);
                File::delete($cacheFile);

                return null;
            }

            return $cacheData['data'] ?? null;
        } catch (\Exception $e) {
            // 破損したキャッシュは削除
            File::delete($cacheFile);

            return null;
        }
    }

    /**
     * メタデータを取得
     *
     * @return CacheMetadata|null
     */
    private function getMetadata(string $key): ?array
    {
        $cacheFile = $this->getCachePath($key);

        if (! File::exists($cacheFile)) {
            return null;
        }

        try {
            $cacheData = unserialize(File::get($cacheFile));

            // バージョンが異なる場合は古いキャッシュとして削除（get()と同じ動作）
            $cachedVersion = $cacheData['version'] ?? 0;
            if ($cachedVersion !== self::CACHE_VERSION) {
                Log::debug('DocumentationCache: Invalidating cache metadata due to version mismatch', [
                    'key' => $key,
                    'cached_version' => $cachedVersion,
                    'current_version' => self::CACHE_VERSION,
                ]);
                File::delete($cacheFile);

                return null;
            }

            return $cacheData['metadata'] ?? null;
        } catch (\Exception $e) {
            Log::warning('DocumentationCache: Failed to read cache metadata', [
                'key' => $key,
                'file' => $cacheFile,
                'error' => $e->getMessage(),
            ]);
            File::delete($cacheFile);

            return null;
        }
    }

    /**
     * キャッシュファイルのパスを取得
     */
    private function getCachePath(string $key): string
    {
        $filename = sha1($key).'.cache';

        return $this->cacheDir.'/'.$filename;
    }

    /**
     * 特定のキャッシュエントリを削除
     */
    public function forget(string $key): bool
    {
        // キャッシュが無効な場合は何もしない
        if (! $this->enabled) {
            return false;
        }

        $cacheFile = $this->getCachePath($key);

        if (File::exists($cacheFile)) {
            $result = File::delete($cacheFile);

            // デバッグ用のログ出力（開発環境のみ）
            if (app()->environment('local') && config('app.debug')) {
                $status = $result ? 'deleted' : 'failed to delete';
                Log::debug("DocumentationCache::forget({$key}) - File: {$cacheFile} - Status: {$status}");
            }

            return $result;
        }

        // デバッグ用のログ出力（開発環境のみ）
        if (app()->environment('local') && config('app.debug')) {
            Log::debug("DocumentationCache::forget({$key}) - File not found: {$cacheFile}");
        }

        return false;
    }

    /**
     * パターンに一致するキャッシュエントリを削除
     */
    public function forgetByPattern(string $pattern): int
    {
        if (! File::isDirectory($this->cacheDir)) {
            return 0;
        }

        $count = 0;
        $files = File::files($this->cacheDir);

        foreach ($files as $file) {
            try {
                $cacheData = unserialize(File::get($file->getPathname()));
                $key = $cacheData['metadata']['key'] ?? '';

                if (str_contains($key, $pattern)) {
                    File::delete($file->getPathname());
                    $count++;
                }
            } catch (\Exception $e) {
                // 破損したキャッシュは削除
                File::delete($file->getPathname());
            }
        }

        return $count;
    }

    /**
     * キャッシュをクリア
     */
    public function clear(): void
    {
        if (File::isDirectory($this->cacheDir)) {
            File::deleteDirectory($this->cacheDir);
        }
        File::ensureDirectoryExists($this->cacheDir);
    }

    /**
     * 統計情報を取得
     *
     * @return array{
     *     enabled: bool,
     *     cache_directory: string,
     *     cache_version: int,
     *     total_files: int,
     *     total_size: int,
     *     total_size_human: string,
     *     oldest_file: string|null,
     *     newest_file: string|null
     * }
     */
    public function getStats(): array
    {
        if (! File::isDirectory($this->cacheDir)) {
            return [
                'enabled' => $this->enabled,
                'cache_directory' => $this->cacheDir,
                'cache_version' => self::CACHE_VERSION,
                'total_files' => 0,
                'total_size' => 0,
                'total_size_human' => '0 B',
                'oldest_file' => null,
                'newest_file' => null,
            ];
        }

        $files = File::files($this->cacheDir);
        $totalSize = 0;
        $oldestFile = null;
        $newestFile = null;

        foreach ($files as $file) {
            $totalSize += $file->getSize();
            $mtime = $file->getMTime();

            if (! $oldestFile || $mtime < $oldestFile) {
                $oldestFile = $mtime;
            }

            if (! $newestFile || $mtime > $newestFile) {
                $newestFile = $mtime;
            }
        }

        return [
            'enabled' => $this->enabled,
            'cache_directory' => $this->cacheDir,
            'cache_version' => self::CACHE_VERSION,
            'total_files' => count($files),
            'total_size' => $totalSize,
            'total_size_human' => $this->humanFilesize($totalSize),
            'oldest_file' => $oldestFile ? date('Y-m-d H:i:s', $oldestFile) : null,
            'newest_file' => $newestFile ? date('Y-m-d H:i:s', $newestFile) : null,
        ];
    }

    private function humanFilesize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = (int) floor((strlen((string) $bytes) - 1) / 3);

        return sprintf('%.2f', $bytes / pow(1024, $factor)).' '.$units[$factor];
    }

    /**
     * Resourceの依存関係を検出
     *
     * @return DependencyList
     */
    private function findResourceDependencies(string $filePath): array
    {
        if (! file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        $dependencies = [];

        // 他のResourceクラスの使用を検出
        if (preg_match_all('/new\s+(\w+Resource)\s*\(/', $content, $matches)) {
            foreach ($matches[1] as $resourceClass) {
                // フルクラス名を推測
                $possibleClasses = [
                    $resourceClass,
                    'App\\Http\\Resources\\'.$resourceClass,
                ];

                foreach ($possibleClasses as $className) {
                    try {
                        if (class_exists($className)) {
                            $reflection = new \ReflectionClass($className);
                            if ($depPath = $reflection->getFileName()) {
                                $dependencies[] = $depPath;
                                break;
                            }
                        }
                    } catch (\Exception $e) {
                        // クラスが見つからない場合は無視
                    }
                }
            }
        }

        return array_unique($dependencies);
    }

    /**
     * 全てのキャッシュエントリのキーを取得
     *
     * @return list<string>
     */
    public function getAllCacheKeys(): array
    {
        if (! File::isDirectory($this->cacheDir)) {
            return [];
        }

        $keys = [];
        $files = File::files($this->cacheDir);

        foreach ($files as $file) {
            try {
                $cacheData = unserialize(File::get($file->getPathname()));
                $key = $cacheData['metadata']['key'] ?? null;
                if ($key) {
                    $keys[] = $key;
                }
            } catch (\Exception $e) {
                // 無視
            }
        }

        return $keys;
    }

    /**
     * キャッシュが有効かどうかを確認
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * キャッシュを一時的に無効化
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * キャッシュを有効化
     */
    public function enable(): void
    {
        $this->enabled = config('spectrum.cache.enabled', true);
    }
}
