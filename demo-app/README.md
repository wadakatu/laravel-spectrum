# Demo App Compliance Environment

`demo-app` contains two Laravel applications (`laravel-11-app`, `laravel-12-app`) used to verify OpenAPI compliance end-to-end.

## What This Checks

`check-openapi-compliance.sh` runs this matrix:

- Laravel 11 + OpenAPI `3.0.0`
- Laravel 11 + OpenAPI `3.1.0`
- Laravel 12 + OpenAPI `3.0.0`
- Laravel 12 + OpenAPI `3.1.0`

For each case, it:

1. Generates spec via `php artisan spectrum:generate --no-cache`
2. Validates it with `devizzent/cebe-php-openapi`
3. Applies version-specific guard checks:
   - 3.0.x: no `jsonSchemaDialect`, no `webhooks`, no `type: []`
   - 3.1.x: `jsonSchemaDialect` exists, no `nullable`, `webhooks` exists

## Usage

Run from repository root:

```bash
./demo-app/check-openapi-compliance.sh
```

## Output

Each run writes artifacts under:

`demo-app/reports/<timestamp>/`

- `summary.md`: matrix result table
- `*-openapi-<version>.json`: generated specs
- `*-openapi-<version>.log`: generation + validation logs

Use this directory as evidence for compliance checks and regression tracking.
