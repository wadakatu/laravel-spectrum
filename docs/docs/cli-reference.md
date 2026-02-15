# CLI Command Reference

A detailed reference of all available Artisan commands and options in Laravel Spectrum.

## 📋 Command List

| Command | Description |
|---------|-------------|
| `spectrum:generate` | Generate OpenAPI documentation |
| `spectrum:generate:optimized` | Optimized generation (for large projects) |
| `spectrum:watch` | Real-time preview mode |
| `spectrum:mock` | Launch mock API server |
| `spectrum:export:postman` | Export to Postman collection |
| `spectrum:export:insomnia` | Export to Insomnia workspace |
| `spectrum:cache` | Manage cache (clear, stats, warm) |

## 🔧 spectrum:generate

The basic command to generate API documentation.

### Usage

```bash
php artisan spectrum:generate [options]
```

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--output` | storage/app/spectrum/openapi.json | Output file path |
| `--format` | json | Output format (json/yaml/html) |
| `--no-cache` | false | Don't use cache |
| `--clear-cache` | false | Clear cache before generation |
| `--fail-on-error` | false | Stop execution on first error |
| `--ignore-errors` | false | Continue generation ignoring errors |
| `--error-report` | none | Save error report to file |
| `--no-try-it-out` | false | Disable "Try It Out" feature in HTML output |

### Examples

```bash
# Basic generation
php artisan spectrum:generate

# Output in YAML format
php artisan spectrum:generate --format=yaml --output=docs/api.yaml

# Output in HTML format (with Swagger UI)
php artisan spectrum:generate --format=html --output=docs/api.html

# Clear cache and regenerate
php artisan spectrum:generate --clear-cache

# Generate without using cache
php artisan spectrum:generate --no-cache

# Stop on error
php artisan spectrum:generate --fail-on-error

# Ignore errors and continue
php artisan spectrum:generate --ignore-errors

# Save error report
php artisan spectrum:generate --error-report=storage/spectrum-errors.json

# Generate with verbose output
php artisan spectrum:generate -vvv
```

## ⚡ spectrum:generate:optimized

Optimized generation command for large projects.

### Usage

```bash
php artisan spectrum:generate:optimized [options]
```

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--format` | json | Output format (json/yaml) |
| `--output` | storage/app/spectrum/openapi.json | Output file path |
| `--parallel` | false | Enable parallel processing |
| `--workers` | none | Number of parallel workers |
| `--chunk-size` | auto | Number of routes processed per chunk |
| `--memory-limit` | none | Memory limit override (example: `1G`) |
| `--incremental` | false | Process only changed files |

### Examples

```bash
# Generate with automatic optimization
php artisan spectrum:generate:optimized

# Enable parallel processing
php artisan spectrum:generate:optimized --parallel

# Parallel processing with 8 workers
php artisan spectrum:generate:optimized --parallel --workers=8

# Adjust memory and chunk size
php artisan spectrum:generate:optimized --memory-limit=1G --chunk-size=50

# Incremental generation
php artisan spectrum:generate:optimized --incremental

# Output in YAML format
php artisan spectrum:generate:optimized --format=yaml

# Generate with verbose output
php artisan spectrum:generate:optimized -v
```

## 👁️ spectrum:watch

Watch file changes and update documentation in real-time.

### Usage

```bash
php artisan spectrum:watch [options]
```

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--port` | 8080 | Preview server port |
| `--host` | 127.0.0.1 | Preview server host |
| `--no-open` | false | Don't open browser automatically |

### Examples

```bash
# Basic usage
php artisan spectrum:watch

# Launch on custom port
php artisan spectrum:watch --port=3000

# Launch without opening browser
php artisan spectrum:watch --no-open

# Make externally accessible
php artisan spectrum:watch --host=0.0.0.0
```

## 🎭 spectrum:mock

Launch a mock API server based on OpenAPI documentation.

### Usage

```bash
php artisan spectrum:mock [options]
```

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--host` | 127.0.0.1 | Host address to bind |
| `--port` | 8081 | Port number to listen on |
| `--spec` | storage/app/spectrum/openapi.json | Path to OpenAPI specification file |
| `--delay` | none | Response delay (milliseconds) |
| `--scenario` | success | Default response scenario |

### Examples

```bash
# Basic launch
php artisan spectrum:mock

# Custom port and host
php artisan spectrum:mock --host=0.0.0.0 --port=3000

# Add response delay
php artisan spectrum:mock --delay=500

# Set error scenario as default
php artisan spectrum:mock --scenario=error

# Custom OpenAPI file
php artisan spectrum:mock --spec=docs/custom-api.json
```

## 📤 spectrum:export:postman

Export API documentation as a Postman collection.

### Usage

```bash
php artisan spectrum:export:postman [options]
```

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--output` | storage/app/spectrum/postman | Output directory |
| `--environments` | local | Environments to export (comma-separated) |
| `--single-file` | false | Export as a single file with embedded environments |

### Examples

```bash
# Basic export
php artisan spectrum:export:postman

# Custom output location
php artisan spectrum:export:postman --output=postman/

# Export multiple environments
php artisan spectrum:export:postman --environments=local,staging,production

# Export as single file
php artisan spectrum:export:postman --single-file
```

## 🦊 spectrum:export:insomnia

Export API documentation as an Insomnia workspace.

### Usage

```bash
php artisan spectrum:export:insomnia [options]
```

### Options

| Option | Default | Description |
|--------|---------|-------------|
| `--output` | storage/app/spectrum/insomnia/insomnia_collection.json | Output file path |

### Examples

```bash
# Basic export
php artisan spectrum:export:insomnia

# Custom output location (file path)
php artisan spectrum:export:insomnia --output=insomnia/api.json

# Custom output location (directory)
php artisan spectrum:export:insomnia --output=insomnia/
```

## 🗑️ spectrum:cache

Manage Laravel Spectrum cache (clear, show statistics, or warm up).

### Usage

```bash
php artisan spectrum:cache {action}
```

### Actions

| Action | Description |
|--------|-------------|
| `clear` | Clear all cached documentation |
| `stats` | Show cache statistics (size, files, etc.) |
| `warm` | Clear and regenerate cache |

### Examples

```bash
# Clear all cache
php artisan spectrum:cache clear

# Show cache statistics
php artisan spectrum:cache stats

# Warm up cache (clear and regenerate)
php artisan spectrum:cache warm
```

## 🔍 Global Options

Laravel global options available for all commands:

| Option | Short | Description |
|--------|-------|-------------|
| `--help` | `-h` | Show help |
| `--quiet` | `-q` | Suppress output |
| `--verbose` | `-v/-vv/-vvv` | Increase verbosity |
| `--version` | `-V` | Display version |
| `--ansi` | | Force ANSI output |
| `--no-ansi` | | Disable ANSI output |
| `--no-interaction` | `-n` | Don't ask interactive questions |
| `--env` | | Specify environment |

## 💡 Useful Tips

### Setting Aliases

```bash
# Add to ~/.bashrc or ~/.zshrc
alias specgen="php artisan spectrum:generate"
alias specwatch="php artisan spectrum:watch"
alias specmock="php artisan spectrum:mock"
```

### Using Makefile

```makefile
# Makefile
.PHONY: docs docs-watch docs-mock

docs:
	php artisan spectrum:generate

docs-watch:
	php artisan spectrum:watch

docs-mock:
	php artisan spectrum:mock

docs-export:
	php artisan spectrum:export:postman --environments=local
	php artisan spectrum:export:insomnia
```

### Integration with npm scripts

```json
{
  "scripts": {
    "api:docs": "php artisan spectrum:generate",
    "api:watch": "php artisan spectrum:watch",
    "api:mock": "php artisan spectrum:mock",
    "dev": "concurrently \"npm run api:mock\" \"npm run serve\""
  }
}
```

## 📚 Related Documentation

- [Basic Usage](./basic-usage.md) - Basic usage guide
- [Configuration Reference](./config-reference.md) - Configuration file details
- [Troubleshooting](./troubleshooting.md) - Problem solving guide
