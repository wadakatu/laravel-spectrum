# Upgrade Guide

This guide helps you upgrade Laravel Spectrum from beta versions (v0.x) to v1.0.0.

## Quick Upgrade

For most users, upgrading is straightforward:

```bash
composer require wadakatu/laravel-spectrum:^1.0
php artisan vendor:publish --tag=spectrum-config --force
```

## Breaking Changes from Beta

### PHP Version Requirement

| Version | PHP Requirement |
|---------|-----------------|
| v0.1.x | PHP 8.1+ |
| v0.2.x | PHP 8.2+ |
| v1.0.0 | PHP 8.2+ |

**Action Required**: Upgrade to PHP 8.2 or later before upgrading to v1.0.0.

### Laravel Version Support

| Version | Laravel Support |
|---------|-----------------|
| v0.1.x | Laravel 10, 11 |
| v0.2.x | Laravel 11, 12 |
| v1.0.0 | Laravel 11, 12 |

**Action Required**: Upgrade to Laravel 11 or later. Laravel 10 is no longer supported.

### Lumen Support Removed

Lumen support was removed in v0.2.1-beta. If you're using Lumen, you must migrate to Laravel or stay on v0.1.x (not recommended for production).

**Action Required**: Migrate from Lumen to Laravel, or use a different documentation tool.

## Configuration Changes

### New Configuration Options

The following options were added since v0.1.0-beta and should be reviewed:

```php
// config/spectrum.php

// Example generation with Faker (v0.1.0-beta)
'example_generation' => [
    'use_faker' => true,
    'faker_locale' => 'en_US',
    'faker_seed' => null,
],

// Error handling (v0.1.0-beta)
'error_handling' => [
    'collect_errors' => true,
    'stop_on_critical' => false,
    'log_errors' => true,
],

// Response detection (v0.1.0-beta)
'response_detection' => [
    'enabled' => true,
    'analyze_return_types' => true,
],

// Performance optimization (v0.1.0-beta)
'performance' => [
    'parallel_processing' => true,
    'cache_enabled' => true,
    'chunk_size' => 50,
],

// Export options (v0.1.0-beta)
'export' => [
    'postman' => [...],
    'insomnia' => [...],
],
```

**Action Required**: Re-publish the config file to get new options:

```bash
php artisan vendor:publish --tag=spectrum-config --force
```

### Removed Configuration Options

The following Lumen-specific options were removed:

```php
// These no longer exist in v1.0.0
'lumen' => [
    'router' => null,
    'routes_path' => null,
],
```

**Action Required**: Remove any Lumen-specific configuration from your `config/spectrum.php`.

## Command Changes

### New Commands

| Command | Added In | Description |
|---------|----------|-------------|
| `spectrum:export:postman` | v0.1.0-beta | Export to Postman |
| `spectrum:export:insomnia` | v0.1.0-beta | Export to Insomnia |

### Command Behavior Changes

The `spectrum:generate` command now:
- Reports analysis errors at the end of generation
- Supports `--parallel` flag for parallel processing
- Supports `--cache` flag for incremental caching

## New Features in v1.0.0

### Faker Integration

Generate realistic example data using Faker:

```php
// config/spectrum.php
'example_generation' => [
    'use_faker' => true,
    'faker_locale' => 'ja_JP', // Locale-specific data
],
```

### Conditional Validation Support

Spectrum now detects conditional validation rules (`sometimes`, `required_if`, etc.) and generates `oneOf` schemas.

### Response Body Detection

Automatic detection of response structures from controller return statements.

### Error Collection

Non-fatal errors are collected and reported instead of stopping generation.

### Performance Optimization

- Parallel route processing
- Incremental caching
- Memory management for large codebases

### Export Capabilities

Export to Postman and Insomnia formats:

```bash
php artisan spectrum:export:postman
php artisan spectrum:export:insomnia
```

## Deprecations

No features are deprecated in v1.0.0. All beta features have been stabilized.

## Troubleshooting

### "Class not found" Errors

Clear the autoloader cache:

```bash
composer dump-autoload
php artisan clear-compiled
```

### Configuration Not Loading

Ensure you've published the latest config:

```bash
php artisan config:clear
php artisan vendor:publish --tag=spectrum-config --force
```

### Memory Issues with Large Codebases

Enable performance optimizations:

```php
'performance' => [
    'parallel_processing' => true,
    'chunk_size' => 25, // Reduce if memory issues persist
],
```

## Getting Help

- [GitHub Issues](https://github.com/wadakatu/laravel-spectrum/issues)
- [Documentation](https://wadakatu.github.io/laravel-spectrum/)
- [CHANGELOG.md](CHANGELOG.md)
