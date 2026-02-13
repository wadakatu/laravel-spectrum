# Export Features Guide

Laravel Spectrum can export generated OpenAPI documents to formats that can be imported directly into Postman and Insomnia.

## Postman Export

### Basic Export

```bash
php artisan spectrum:export:postman
```

Default outputs:
- `storage/app/spectrum/postman/postman_collection.json`
- `storage/app/spectrum/postman/postman_environment_local.json`

### Available Options

- `--output=`: Output directory
- `--environments=`: Environments to export (comma-separated, default `local`)
- `--single-file`: Embed environments into the collection instead of separate files

### Examples

```bash
# Use default output directory
php artisan spectrum:export:postman

# Export to custom directory
php artisan spectrum:export:postman --output=storage/app/exports/postman

# Export multiple environments
php artisan spectrum:export:postman --environments=local,staging,production

# Embed environments into one collection file
php artisan spectrum:export:postman --single-file --environments=local,staging
```

## Insomnia Export

### Basic Export

```bash
php artisan spectrum:export:insomnia
```

Default output:
- `storage/app/spectrum/insomnia/insomnia_collection.json`

### Available Options

- `--output=`: Output file path or output directory

When `--output` is a directory (or ends with `/`), Laravel Spectrum writes `insomnia_collection.json` into that directory.

### Examples

```bash
# Use default output path
php artisan spectrum:export:insomnia

# Export to explicit file path
php artisan spectrum:export:insomnia --output=storage/app/exports/insomnia/api.json

# Export to directory (filename is appended automatically)
php artisan spectrum:export:insomnia --output=storage/app/exports/insomnia/
```

## Import Procedures

### Importing to Postman

1. Open Postman.
2. Click "Import".
3. Select `postman_collection.json`.
4. Import the corresponding `postman_environment_*.json` file (unless `--single-file` was used).
5. Select your environment and run requests.

### Importing to Insomnia

1. Open Insomnia.
2. Go to `Application -> Preferences -> Data -> Import Data`.
3. Select `From File`.
4. Choose the exported `insomnia_collection.json` (or your custom output file).

## CI Example

```yaml
# .github/workflows/export-api.yml
name: Export API Documentation

on:
  push:
    branches: [main]

jobs:
  export:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Export Postman collection
        run: php artisan spectrum:export:postman --environments=local,staging

      - name: Export Insomnia collection
        run: php artisan spectrum:export:insomnia
```

## Related Documentation

- [CLI Reference](./cli-reference.md)
- [CI/CD Integration](./ci-cd-integration.md)
