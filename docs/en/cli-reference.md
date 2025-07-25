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
| `spectrum:cache:clear` | Clear cache |

## 🔧 spectrum:generate

The basic command to generate API documentation.

### Usage

```bash
php artisan spectrum:generate [options]
```

### Options

| Option | Short | Default | Description |
|--------|-------|---------|-------------|
| `--output` | `-o` | storage/app/spectrum/openapi.json | Output file path |
| `--format` | `-f` | json | Output format (json/yaml) |
| `--pattern` | | config value | Route patterns to include |
| `--exclude` | | config value | Route patterns to exclude |
| `--no-cache` | | false | Don't use cache |
| `--force` | | false | Overwrite existing files |
| `--dry-run` | | false | Run without generating files |
| `--incremental` | `-i` | false | Process only changed files |

### Examples

```bash
# Basic generation
php artisan spectrum:generate

# Generate only specific patterns
php artisan spectrum:generate --pattern="api/v2/*"

# Multiple patterns
php artisan spectrum:generate --pattern="api/users/*" --pattern="api/posts/*"

# Exclude patterns
php artisan spectrum:generate --exclude="api/admin/*" --exclude="api/debug/*"

# Output in YAML format
php artisan spectrum:generate --format=yaml --output=docs/api.yaml

# Force regeneration without cache
php artisan spectrum:generate --no-cache --force

# Dry run (doesn't actually generate)
php artisan spectrum:generate --dry-run -vvv
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
| `--workers` | auto | Number of parallel workers (auto for CPU cores) |
| `--chunk-size` | 100 | Number of routes processed by each worker |
| `--memory-limit` | 512M | Memory limit for each worker |
| `--incremental` | false | Process only changed files |
| `--progress` | true | Show progress bar |
| `--stats` | true | Show performance statistics |

### Examples

```bash
# Generate with automatic optimization
php artisan spectrum:generate:optimized

# Parallel processing with 8 workers
php artisan spectrum:generate:optimized --workers=8

# Adjust memory and chunk size
php artisan spectrum:generate:optimized --memory-limit=1G --chunk-size=50

# Incremental generation
php artisan spectrum:generate:optimized --incremental

# Run quietly without statistics
php artisan spectrum:generate:optimized --no-stats --no-progress
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
| `--host` | localhost | Preview server host |
| `--no-open` | false | Don't open browser automatically |
| `--poll` | false | Use polling mode |
| `--interval` | 1000 | Polling interval (milliseconds) |

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

# Polling mode (Docker environments, etc.)
php artisan spectrum:watch --poll --interval=2000
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
| `--output` | storage/app/spectrum/postman/collection.json | Output file path |
| `--include-examples` | true | Include request/response examples |
| `--include-tests` | false | Generate test scripts |
| `--environment` | false | Also generate environment variables file |
| `--base-url` | APP_URL | Base URL |

### Examples

```bash
# Basic export
php artisan spectrum:export:postman

# Export with test scripts
php artisan spectrum:export:postman --include-tests

# Also generate environment variables file
php artisan spectrum:export:postman --environment

# Custom output location
php artisan spectrum:export:postman --output=postman/my-api.json

# Complete export
php artisan spectrum:export:postman \
    --include-tests \
    --environment \
    --base-url=https://api.example.com
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
| `--output` | storage/app/spectrum/insomnia/workspace.json | Output file path |
| `--workspace-name` | APP_NAME API | Workspace name |
| `--include-environments` | true | Include environment settings |
| `--folder-structure` | true | Organize with folder structure |

### Examples

```bash
# Basic export
php artisan spectrum:export:insomnia

# Custom workspace name
php artisan spectrum:export:insomnia --workspace-name="My Cool API"

# Flat structure without folders
php artisan spectrum:export:insomnia --no-folder-structure

# Custom output location
php artisan spectrum:export:insomnia --output=insomnia/api.json
```

## 🗑️ spectrum:cache:clear

Clear Laravel Spectrum cache.

### Usage

```bash
php artisan spectrum:cache:clear [options]
```

### Options

| Option | Description |
|--------|-------------|
| `--routes` | Clear only route cache |
| `--schemas` | Clear only schema cache |
| `--examples` | Clear only example data cache |
| `--all` | Clear all cache (default) |

### Examples

```bash
# Clear all cache
php artisan spectrum:cache:clear

# Routes cache only
php artisan spectrum:cache:clear --routes

# Multiple types
php artisan spectrum:cache:clear --routes --schemas
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
	php artisan spectrum:export:postman --environment
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