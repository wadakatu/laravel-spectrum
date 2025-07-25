# CLI Reference

This document provides a comprehensive reference for all Laravel Spectrum CLI commands and their options.

## Table of Contents

- [Generation Commands](#generation-commands)
- [Export Commands](#export-commands)
- [Cache Commands](#cache-commands)
- [Development Commands](#development-commands)
- [Utility Commands](#utility-commands)

## Generation Commands

### `spectrum:generate`

Generate OpenAPI documentation from your Laravel/Lumen routes.

```bash
php artisan spectrum:generate [options]
```

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--format=FORMAT` | Output format (json, yaml) | json |
| `--output=PATH` | Custom output path | storage/app/spectrum/openapi.json |
| `--version=VERSION` | API version | 1.0.0 |
| `--title=TITLE` | API title | App name from config |
| `--description=DESC` | API description | null |
| `--server=URL` | Server URL | APP_URL from .env |
| `--dry-run` | Show what would be generated | false |
| `--no-cache` | Disable caching | false |
| `--tags=TAGS` | Only include specific tags | null |
| `--exclude-tags=TAGS` | Exclude specific tags | null |

**Examples:**

```bash
# Generate with YAML format
php artisan spectrum:generate --format=yaml

# Custom output location
php artisan spectrum:generate --output=public/api-docs.json

# With custom metadata
php artisan spectrum:generate \
  --title="My API" \
  --description="API for my application" \
  --version=2.0.0

# Generate only specific tags
php artisan spectrum:generate --tags=Users,Auth

# Dry run to preview
php artisan spectrum:generate --dry-run
```

### `spectrum:generate:optimized`

Generate documentation with performance optimizations for large projects.

```bash
php artisan spectrum:generate:optimized [options]
```

**Options:**

All options from `spectrum:generate` plus:

| Option | Description | Default |
|--------|-------------|---------|
| `--parallel` | Enable parallel processing | false |
| `--workers=N` | Number of parallel workers | CPU cores |
| `--chunk-size=N` | Routes per chunk | 100 |
| `--memory-limit=SIZE` | Memory limit (e.g., 512M) | 256M |
| `--incremental` | Only process changed routes | false |
| `--no-progress` | Disable progress bar | false |
| `--stats` | Show performance statistics | false |

**Examples:**

```bash
# Basic optimized generation
php artisan spectrum:generate:optimized

# Full optimization
php artisan spectrum:generate:optimized \
  --parallel \
  --incremental \
  --workers=8

# Memory-constrained environment
php artisan spectrum:generate:optimized \
  --chunk-size=25 \
  --memory-limit=128M

# With statistics
php artisan spectrum:generate:optimized --stats
```

## Export Commands

### `spectrum:export:postman`

Export documentation as a Postman collection.

```bash
php artisan spectrum:export:postman [options]
```

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--output=PATH` | Output directory | storage/app/spectrum/postman |
| `--environments=ENVS` | Comma-separated environments | local |
| `--single-file` | Embed environments in collection | false |

**Examples:**

```bash
# Basic export
php artisan spectrum:export:postman

# Multiple environments
php artisan spectrum:export:postman --environments=local,staging,production

# Custom output
php artisan spectrum:export:postman --output=./exports/postman
```

### `spectrum:export:insomnia`

Export documentation as an Insomnia collection.

```bash
php artisan spectrum:export:insomnia [options]
```

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--output=PATH` | Output file path | storage/app/spectrum/insomnia/insomnia_collection.json |

**Examples:**

```bash
# Basic export
php artisan spectrum:export:insomnia

# Custom output
php artisan spectrum:export:insomnia --output=./exports/insomnia.json
```

## Cache Commands

### `spectrum:cache`

Manage the documentation cache.

```bash
php artisan spectrum:cache [options]
```

**Options:**

| Option | Description |
|--------|-------------|
| `--clear` | Clear all cache |
| `--stats` | Show cache statistics |
| `--prune` | Remove expired cache entries |

**Examples:**

```bash
# Clear cache
php artisan spectrum:cache --clear

# View cache statistics
php artisan spectrum:cache --stats

# Clean up old entries
php artisan spectrum:cache --prune
```

## Development Commands

### `spectrum:watch`

Start the development server with hot reload.

```bash
php artisan spectrum:watch [options]
```

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--host=HOST` | Server host | localhost |
| `--port=PORT` | Server port | 8080 |
| `--no-open` | Don't open browser | false |
| `--ui=UI` | UI theme (swagger-ui, redoc, rapidoc) | swagger-ui |

**Examples:**

```bash
# Basic watch mode
php artisan spectrum:watch

# Custom port
php artisan spectrum:watch --port=3000

# With Redoc UI
php artisan spectrum:watch --ui=redoc

# Don't auto-open browser
php artisan spectrum:watch --no-open
```

## Utility Commands

### `spectrum:analyze`

Analyze your API routes and show statistics.

```bash
php artisan spectrum:analyze [options]
```

**Options:**

| Option | Description |
|--------|-------------|
| `--complexity` | Show route complexity analysis |
| `--coverage` | Show documentation coverage |
| `--missing` | Show undocumented routes |

**Examples:**

```bash
# Basic analysis
php artisan spectrum:analyze

# Complexity analysis
php artisan spectrum:analyze --complexity

# Find undocumented routes
php artisan spectrum:analyze --missing
```

### `spectrum:validate`

Validate your generated OpenAPI documentation.

```bash
php artisan spectrum:validate [options]
```

**Options:**

| Option | Description |
|--------|-------------|
| `--file=PATH` | Path to OpenAPI file |
| `--strict` | Enable strict validation |

**Examples:**

```bash
# Validate default output
php artisan spectrum:validate

# Validate specific file
php artisan spectrum:validate --file=public/api-docs.json

# Strict validation
php artisan spectrum:validate --strict
```

## Global Options

These options are available for all commands:

| Option | Description |
|--------|-------------|
| `-q, --quiet` | Suppress all output |
| `-v, --verbose` | Increase verbosity |
| `-vv` | More verbose output |
| `-vvv` | Debug output |
| `--ansi` | Force ANSI output |
| `--no-ansi` | Disable ANSI output |
| `-n, --no-interaction` | Do not ask any questions |

## Environment Variables

Configure default behavior using environment variables:

```env
# Default format
SPECTRUM_DEFAULT_FORMAT=json

# Default output path
SPECTRUM_OUTPUT_PATH=storage/app/spectrum/openapi.json

# Performance settings
SPECTRUM_PERFORMANCE_ENABLED=true
SPECTRUM_PARALLEL_PROCESSING=true
SPECTRUM_CHUNK_SIZE=100

# UI settings
SPECTRUM_WATCH_PORT=8080
SPECTRUM_WATCH_UI=swagger-ui

# Cache settings
SPECTRUM_CACHE_ENABLED=true
SPECTRUM_CACHE_TTL=3600
```

## Exit Codes

| Code | Description |
|------|-------------|
| 0 | Success |
| 1 | General error |
| 2 | Invalid options |
| 3 | No routes found |
| 4 | Cache error |
| 5 | Export error |

## Tips and Tricks

### 1. Aliases for Common Commands

Add to your shell configuration:

```bash
alias specgen='php artisan spectrum:generate'
alias specwatch='php artisan spectrum:watch'
alias specexport='php artisan spectrum:export:postman'
```

### 2. Composer Scripts

Add to `composer.json`:

```json
{
  "scripts": {
    "docs": "php artisan spectrum:generate",
    "docs:watch": "php artisan spectrum:watch",
    "docs:export": [
      "php artisan spectrum:export:postman",
      "php artisan spectrum:export:insomnia"
    ]
  }
}
```

### 3. Git Hooks

Auto-generate on commit:

```bash
#!/bin/sh
# .git/hooks/pre-commit
php artisan spectrum:generate:optimized --incremental
git add storage/app/spectrum/openapi.json
```

### 4. CI/CD Integration

GitHub Actions example:

```yaml
- name: Generate API Documentation
  run: |
    php artisan spectrum:generate:optimized \
      --parallel \
      --format=json \
      --output=public/api-docs.json
    
- name: Export Collections
  run: |
    php artisan spectrum:export:postman \
      --output=./exports \
      --environments=production
```

## Troubleshooting

### Command Not Found

```bash
# Clear composer cache
composer clear-cache

# Re-install
composer require wadakatu/laravel-spectrum --dev

# Clear Laravel cache
php artisan cache:clear
php artisan config:clear
```

### Permission Errors

```bash
# Fix storage permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### Memory Errors

```bash
# Increase memory limit
php -d memory_limit=512M artisan spectrum:generate

# Or use optimized command
php artisan spectrum:generate:optimized --memory-limit=512M
```

## See Also

- [Configuration Guide](./configuration.md)
- [Performance Optimization](./performance.md)
- [Export Features](./export.md)
- [Troubleshooting Guide](./troubleshooting.md)