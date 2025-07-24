<?php

namespace LaravelSpectrum\Services;

use Illuminate\Support\Facades\Cache;

class DocumentationCache
{
    private string $prefix = 'spectrum:';

    private int $ttl = 3600; // 1 hour default

    public function __construct()
    {
        // TTL from config
        $this->ttl = config('spectrum.cache.ttl', 3600);
    }

    /**
     * Get cache key with prefix
     */
    protected function getCacheKey(string $key): string
    {
        return $this->prefix.$key;
    }

    /**
     * Get value from cache
     */
    public function get(string $key, $default = null)
    {
        return Cache::get($this->getCacheKey($key), $default);
    }

    /**
     * Set value in cache
     */
    public function set(string $key, $value): bool
    {
        return Cache::put($this->getCacheKey($key), $value, $this->ttl);
    }

    /**
     * Check if key exists in cache
     */
    public function has(string $key): bool
    {
        return Cache::has($this->getCacheKey($key));
    }

    /**
     * Remove key from cache
     */
    public function forget(string $key): bool
    {
        return Cache::forget($this->getCacheKey($key));
    }

    /**
     * Clear all cache
     */
    public function flush(): bool
    {
        // Get all cache keys with our prefix and delete them
        $keys = $this->getAllCacheKeys();
        foreach ($keys as $key) {
            Cache::forget($key);
        }

        return true;
    }

    /**
     * Get all cache keys
     */
    public function getAllCacheKeys(): array
    {
        // This is implementation specific and depends on the cache driver
        // For simplicity, we'll return an empty array
        // In production, this would need to be implemented based on the cache driver
        return [];
    }
}
