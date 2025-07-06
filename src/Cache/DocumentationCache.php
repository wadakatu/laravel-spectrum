<?php

namespace LaravelPrism\Cache;

use Illuminate\Support\Facades\File;

class DocumentationCache
{
    private string $cacheDir;

    private bool $enabled;

    public function __construct()
    {
        $this->cacheDir = config('prism.cache.directory', storage_path('app/prism/cache'));
        $this->enabled = config('prism.cache.enabled', true);

        if ($this->enabled) {
            File::ensureDirectoryExists($this->cacheDir);
        }
    }

    /**
     * キャッシュから取得、なければコールバックを実行
     */
    public function remember(string $key, \Closure $callback, array $dependencies = []): mixed
    {
        if (! $this->enabled) {
            return $callback();
        }

        // 依存ファイルが変更されていないかチェック
        if ($this->isDependenciesChanged($key, $dependencies)) {
            $result = $callback();
            $this->put($key, $result, $dependencies);

            return $result;
        }

        // キャッシュから取得
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        // キャッシュミスの場合は生成
        $result = $callback();
        $this->put($key, $result, $dependencies);

        return $result;
    }

    /**
     * FormRequest解析結果をキャッシュ
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
     */
    public function rememberRoutes(\Closure $callback): array
    {
        $routeFiles = [
            base_path('routes/api.php'),
            base_path('routes/web.php'),
        ];

        // カスタムルートファイルも含める
        $customRoutes = config('prism.route_files', []);
        $routeFiles = array_merge($routeFiles, $customRoutes);

        $existingFiles = array_filter($routeFiles, 'file_exists');

        return $this->remember('routes:all', $callback, $existingFiles);
    }

    /**
     * 依存ファイルが変更されているかチェック
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

            return $cacheData['data'] ?? null;
        } catch (\Exception $e) {
            // 破損したキャッシュは削除
            File::delete($cacheFile);

            return null;
        }
    }

    /**
     * メタデータを取得
     */
    private function getMetadata(string $key): ?array
    {
        $cacheFile = $this->getCachePath($key);

        if (! File::exists($cacheFile)) {
            return null;
        }

        try {
            $cacheData = unserialize(File::get($cacheFile));

            return $cacheData['metadata'] ?? null;
        } catch (\Exception $e) {
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
     */
    public function getStats(): array
    {
        if (! File::isDirectory($this->cacheDir)) {
            return [
                'enabled' => $this->enabled,
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
        $factor = floor((strlen((string) $bytes) - 1) / 3);

        return sprintf('%.2f', $bytes / pow(1024, $factor)).' '.$units[$factor];
    }

    /**
     * Resourceの依存関係を検出
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
}
