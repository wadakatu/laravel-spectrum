# Performance Optimization Guide

This guide explains the configuration and techniques for optimal Laravel Spectrum performance in large-scale projects.

## 🚀 Optimization Commands

### Basic Optimized Generation

```bash
php artisan spectrum:generate:optimized
```

This command automatically:
- Detects available CPU cores
- Analyzes routes with parallel processing
- Optimizes memory usage
- Displays real-time progress

### Advanced Options

```bash
php artisan spectrum:generate:optimized \
    --workers=8 \
    --chunk-size=50 \
    --memory-limit=512M \
    --incremental
```

Option descriptions:
- `--workers`: Number of parallel workers (default: CPU core count)
- `--chunk-size`: Number of routes each worker processes (default: 100)
- `--memory-limit`: Memory limit per worker
- `--incremental`: Process only changed files

## 📊 Performance Statistics

After generation completes, the following statistics are displayed:

```
✅ Documentation generated successfully!

📊 Performance Statistics:
├─ Total routes processed: 1,247
├─ Generation time: 23.5 seconds
├─ Memory usage: 128 MB (peak: 256 MB)
├─ Cache hits: 892 (71.5%)
├─ Workers used: 8
└─ Average time per route: 18.8 ms
```

## ⚡ Optimization Techniques

### 1. Incremental Generation

Track file changes and regenerate only necessary parts:

```bash
# Full generation on first run
php artisan spectrum:generate:optimized

# Only changes afterwards
php artisan spectrum:generate:optimized --incremental
```

### 2. Cache Utilization

```php
// config/spectrum.php
'cache' => [
    'enabled' => true,
    'ttl' => null, // Permanent cache
    'directory' => storage_path('app/spectrum/cache'),
    
    // File change tracking
    'watch_files' => [
        base_path('composer.json'),
        base_path('composer.lock'),
    ],
    
    // Smart cache invalidation
    'smart_invalidation' => true,
],
```

### 3. Memory Optimization

```php
// config/spectrum.php
'performance' => [
    // Process routes in chunks
    'chunk_processing' => true,
    'chunk_size' => 100,
    
    // Memory limits
    'memory_limit' => '512M',
    
    // Garbage collection
    'gc_collect_cycles' => true,
    'gc_interval' => 100, // Run GC every 100 routes
],
```

### 4. Selective Generation

Generate only specific route patterns:

```bash
# Specific version only
php artisan spectrum:generate --pattern="api/v2/*"

# Multiple patterns
php artisan spectrum:generate --pattern="api/users/*" --pattern="api/posts/*"

# Exclusion patterns
php artisan spectrum:generate --exclude="api/admin/*" --exclude="api/debug/*"
```

## 🔧 Configuration Optimization

### Large Project Configuration

```php
// config/spectrum.php
return [
    // Basic settings
    'performance' => [
        'enabled' => true,
        'parallel_processing' => true,
        'workers' => env('SPECTRUM_WORKERS', 'auto'), // 'auto' uses CPU core count
        'chunk_size' => env('SPECTRUM_CHUNK_SIZE', 100),
        'memory_limit' => env('SPECTRUM_MEMORY_LIMIT', '1G'),
    ],

    // Analysis optimization
    'analysis' => [
        'max_depth' => 3, // Limit nested analysis depth
        'skip_vendor' => true, // Skip vendor directory
        'lazy_loading' => true, // Load files only when needed
    ],

    // Cache strategy
    'cache' => [
        'strategy' => 'aggressive', // Aggressive caching
        'segments' => [
            'routes' => 86400, // 24 hours
            'schemas' => 3600, // 1 hour
            'examples' => 7200, // 2 hours
        ],
    ],
];
```

### Resource Limits

```php
// Resource limits during generation
'limits' => [
    'max_routes' => 10000, // Maximum number of routes
    'max_file_size' => '50M', // Maximum output file size
    'timeout' => 300, // Timeout (seconds)
    'max_schema_depth' => 10, // Maximum schema depth
],
```

## 📈 Benchmark Results

### Test Environment
- CPU: 8 cores
- RAM: 16GB
- Routes: 1,000

| Method | Execution Time | Memory Usage | 
|--------|----------------|--------------|
| Normal generation | 120s | 1.2GB |
| Optimized (4 workers) | 35s | 400MB |
| Optimized (8 workers) | 20s | 600MB |
| Incremental (10% changed) | 3s | 150MB |

## 🔍 Troubleshooting

### Out of Memory Errors

```bash
# Increase memory limit
php artisan spectrum:generate:optimized --memory-limit=2G

# Or reduce chunk size
php artisan spectrum:generate:optimized --chunk-size=25
```

### Worker Process Errors

```bash
# Reduce worker count
php artisan spectrum:generate:optimized --workers=2

# Or single process mode
php artisan spectrum:generate:optimized --workers=1
```

### Cache Issues

```bash
# Clear cache
php artisan spectrum:cache clear

# Generate without cache
php artisan spectrum:generate:optimized --no-cache
```

## 💡 Best Practices

### 1. CI/CD Pipeline Usage

```yaml
# .github/workflows/generate-docs.yml
- name: Generate API Documentation
  run: |
    php artisan spectrum:generate:optimized \
      --workers=4 \
      --chunk-size=50 \
      --no-interaction
```

### 2. Regular Cache Clearing

```bash
# crontab
0 2 * * * cd /path/to/project && php artisan spectrum:cache clear --quiet
```

### 3. Monitoring and Alerts

```php
// AppServiceProvider.php
use LaravelSpectrum\Events\DocumentationGenerated;

Event::listen(DocumentationGenerated::class, function ($event) {
    if ($event->duration > 60) {
        // Alert if generation takes more than 60 seconds
        Log::warning('Documentation generation took too long', [
            'duration' => $event->duration,
            'routes_count' => $event->routesCount,
        ]);
    }
});
```

## 🚀 Next-Generation Features (Experimental)

### Distributed Generation

For large-scale projects requiring distributed processing, use the optimized command with multiple workers:

```bash
php artisan spectrum:generate:optimized \
    --workers=8 \
    --memory-limit=1G
```


## 📚 Related Documentation

- [Basic Usage](./basic-usage.md) - Normal usage
- [Configuration Reference](./config-reference.md) - Detailed configuration options
- [Troubleshooting](./troubleshooting.md) - Problem-solving guide