<?php

namespace LaravelSpectrum\Services;

use Illuminate\Support\Facades\Cache;

class DocumentationCache
{
    private string $prefix = 'spectrum:';

    private int $ttl = 3600; // 1 hour

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->prefix.$key, $default);
    }

    public function put(string $key, mixed $value, ?int $ttl = null): bool
    {
        return Cache::put($this->prefix.$key, $value, $ttl ?? $this->ttl);
    }

    public function forget(string $key): bool
    {
        return Cache::forget($this->prefix.$key);
    }

    public function flush(): bool
    {
        // Clear all spectrum cache entries
        return Cache::flush();
    }

    public function remember(string $key, \Closure $callback, ?int $ttl = null): mixed
    {
        return Cache::remember($this->prefix.$key, $ttl ?? $this->ttl, $callback);
    }
}
