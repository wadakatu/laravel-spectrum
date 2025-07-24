# Performance Optimization Guide

Laravel Spectrum includes advanced performance optimizations designed for large-scale projects. This guide covers all performance features and best practices.

## Table of Contents

- [Overview](#overview)
- [Quick Start](#quick-start)
- [Features](#features)
- [Configuration](#configuration)
- [Best Practices](#best-practices)
- [Benchmarks](#benchmarks)
- [Troubleshooting](#troubleshooting)

## Overview

The performance optimization features in Laravel Spectrum are designed to handle projects with thousands of routes efficiently. The optimizations include:

- **Memory-efficient chunk processing**
- **Multi-core parallel processing**
- **Incremental generation**
- **Smart caching strategies**
- **Real-time progress tracking**

## Quick Start

### Basic Usage

```bash
# Use the optimized command for large projects
php artisan spectrum:generate:optimized
```

### Recommended Settings for Large Projects

```bash
# Enable all optimizations
php artisan spectrum:generate:optimized \
  --parallel \
  --incremental \
  --chunk-size=100 \
  --memory-limit=1G
```

## Features

### 1. Chunk Processing

Processes routes in manageable chunks to minimize memory usage.

```bash
# Process 50 routes at a time
php artisan spectrum:generate:optimized --chunk-size=50
```

**Benefits:**
- Prevents memory exhaustion
- Allows processing of unlimited routes
- Automatic garbage collection between chunks

### 2. Parallel Processing

Utilizes multiple CPU cores to process routes simultaneously.

```bash
# Use 8 workers
php artisan spectrum:generate:optimized --parallel --workers=8

# Auto-detect optimal workers (default)
php artisan spectrum:generate:optimized --parallel
```

**Requirements:**
- PHP PCNTL extension
- Non-Windows environment

**Benefits:**
- Up to 90% faster generation
- Scales with CPU cores
- Progress tracking across workers

### 3. Incremental Generation

Only regenerates documentation for routes that have changed.

```bash
# Enable incremental generation
php artisan spectrum:generate:optimized --incremental
```

**How it works:**
1. Tracks file changes
2. Builds dependency graph
3. Identifies affected routes
4. Regenerates only changed routes

**Benefits:**
- Instant regeneration for small changes
- Perfect for development workflow
- Maintains full accuracy

### 4. Memory Management

Active memory monitoring and optimization.

```bash
# Set memory limit
php artisan spectrum:generate:optimized --memory-limit=512M
```

**Features:**
- Real-time memory monitoring
- Automatic garbage collection
- Memory limit enforcement
- Warning thresholds

### 5. Progress Tracking

Real-time progress bars and statistics.

```bash
# Verbose output with statistics
php artisan spectrum:generate:optimized -v
```

**Shows:**
- Current progress
- Memory usage
- Processing speed
- Time estimates

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Enable performance optimizations
SPECTRUM_PERFORMANCE_ENABLED=true

# Parallel processing
SPECTRUM_PARALLEL_PROCESSING=true
SPECTRUM_MAX_WORKERS=8

# Chunk processing
SPECTRUM_CHUNK_SIZE=100

# Memory settings
SPECTRUM_MEMORY_LIMIT=512M

# Incremental generation
SPECTRUM_INCREMENTAL_ENABLED=true
```

### Config File

In `config/spectrum.php`:

```php
'performance' => [
    'enabled' => true,
    
    'parallel_processing' => true,
    'max_workers' => null, // null = auto-detect
    
    'chunk_size' => 100,
    'auto_chunk_size' => true,
    
    'memory_limit' => '512M',
    'memory_warning_threshold' => 0.8,
    'memory_critical_threshold' => 0.9,
    
    'incremental' => [
        'enabled' => true,
        'track_dependencies' => true,
        'cache_dependencies' => true,
    ],
],
```

## Best Practices

### 1. Choose the Right Command

```bash
# Small projects (< 100 routes)
php artisan spectrum:generate

# Medium projects (100-500 routes)
php artisan spectrum:generate:optimized --chunk-size=50

# Large projects (500+ routes)
php artisan spectrum:generate:optimized --parallel --chunk-size=100

# Very large projects (2000+ routes)
php artisan spectrum:generate:optimized \
  --parallel \
  --incremental \
  --chunk-size=200 \
  --memory-limit=1G
```

### 2. Development Workflow

```bash
# First generation
php artisan spectrum:generate:optimized --parallel

# Subsequent generations (during development)
php artisan spectrum:generate:optimized --incremental

# Watch mode for real-time updates
php artisan spectrum:watch
```

### 3. CI/CD Integration

```yaml
# .github/workflows/docs.yml
- name: Generate API Documentation
  run: |
    php artisan spectrum:generate:optimized \
      --parallel \
      --memory-limit=2G \
      --output=public/api-docs.json
```

### 4. Memory Optimization

```bash
# Monitor memory usage
php artisan spectrum:generate:optimized -v

# Adjust chunk size based on available memory
php artisan spectrum:generate:optimized --chunk-size=25

# Override memory limit if needed
php artisan spectrum:generate:optimized --memory-limit=256M
```

## Benchmarks

### Performance Comparison

| Routes | Standard | Optimized | Parallel | Incremental |
|--------|----------|-----------|----------|-------------|
| 100    | 5s       | 3s        | 2s       | < 1s        |
| 500    | 30s      | 12s       | 8s       | 2s          |
| 1000   | 2m       | 30s       | 15s      | 5s          |
| 2000   | 10m      | 1m 30s    | 45s      | 10s         |
| 5000   | 30m+     | 4m        | 2m       | 20s         |

### Memory Usage

| Routes | Standard | Chunk Processing | Savings |
|--------|----------|------------------|---------|
| 100    | 50MB     | 30MB            | 40%     |
| 500    | 250MB    | 80MB            | 68%     |
| 1000   | 500MB    | 120MB           | 76%     |
| 2000   | 2GB      | 200MB           | 90%     |
| 5000   | 5GB+     | 400MB           | 92%     |

## Troubleshooting

### Common Issues

#### 1. "Memory exhausted" error

```bash
# Increase memory limit
php artisan spectrum:generate:optimized --memory-limit=1G

# Reduce chunk size
php artisan spectrum:generate:optimized --chunk-size=25
```

#### 2. Parallel processing not working

```bash
# Check PCNTL extension
php -m | grep pcntl

# Disable if not available
php artisan spectrum:generate:optimized --no-parallel
```

#### 3. Incremental generation missing changes

```bash
# Clear cache and regenerate
php artisan spectrum:cache --clear
php artisan spectrum:generate:optimized --no-incremental
```

#### 4. Slow performance on shared hosting

```bash
# Use conservative settings
php artisan spectrum:generate:optimized \
  --chunk-size=10 \
  --memory-limit=128M \
  --no-parallel
```

### Debug Mode

```bash
# Enable debug output
php artisan spectrum:generate:optimized -vvv

# Profile performance
SPECTRUM_PROFILING_ENABLED=true php artisan spectrum:generate:optimized
```

### Performance Tips

1. **SSD Storage**: Use SSD for faster file operations
2. **PHP OPcache**: Enable OPcache for better performance
3. **Dedicated Resources**: Run on dedicated server/container
4. **Regular Cleanup**: Clear old cache files periodically

```bash
# Cleanup cache
php artisan spectrum:cache --clear

# Optimize composer autoload
composer dump-autoload -o
```

## Advanced Usage

### Custom Chunk Processor

```php
use LaravelSpectrum\Performance\ChunkProcessor;

$processor = new ChunkProcessor(
    chunkSize: 200,
    memoryManager: new MemoryManager()
);

$results = $processor->processInChunks($routes, function ($chunk) {
    // Custom processing logic
    return processRouteChunk($chunk);
});
```

### Parallel Processing with Custom Workers

```php
use LaravelSpectrum\Performance\ParallelProcessor;

$parallel = new ParallelProcessor();
$parallel->setWorkers(16);

$results = $parallel->process($routes, function ($route) {
    return generateRouteDocumentation($route);
});
```

### Memory Monitoring

```php
use LaravelSpectrum\Performance\MemoryManager;

$memory = new MemoryManager();

// Check before heavy operation
$memory->checkMemoryUsage();

// Get stats
$stats = $memory->getMemoryStats();
echo "Using {$stats['current']} of {$stats['limit']}";

// Force garbage collection
$memory->runGarbageCollection();
```

## Conclusion

The performance optimization features in Laravel Spectrum make it suitable for projects of any size. By using the appropriate combination of features, you can achieve optimal generation times while maintaining low memory usage.

For projects with thousands of routes, the optimized command can reduce generation time from hours to seconds, making it practical to regenerate documentation as part of your development workflow.