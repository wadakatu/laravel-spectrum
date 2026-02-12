# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel Spectrum is a zero-annotation OpenAPI documentation generator for Laravel. It analyzes existing code (FormRequests, API Resources, controllers, routes) to automatically generate OpenAPI 3.0/3.1 specs without requiring annotations.

- **PHP**: ^8.2 | **Laravel**: 11.x, 12.x
- **Namespace**: `LaravelSpectrum\`
- **Entry point**: `SpectrumServiceProvider` (auto-discovered via composer extra)

## Commands

```bash
# Testing
composer test                    # Run all PHPUnit tests
composer test-coverage           # Tests with HTML coverage report (build/coverage/)
vendor/bin/phpunit tests/Unit/Analyzers/FormRequestAnalyzerTest.php  # Single file
vendor/bin/phpunit --filter testMethodName                           # Single method

# Code Quality
composer format:fix              # Fix code style (Laravel Pint, PSR-12)
composer format                  # Check code style without fixing
composer analyze                 # PHPStan level 6 static analysis

# Mutation Testing
composer infection               # Run Infection mutation testing

# Full pre-commit check
composer format:fix && composer analyze && composer test

# Demo app verification
cd demo-app/laravel-12-app && php artisan spectrum:generate
# Output: storage/app/spectrum/openapi.json
```

## Architecture

### Pipeline Flow

```
RouteAnalyzer → ControllerAnalyzer → [FormRequestAnalyzer | ResourceAnalyzer | ResponseAnalyzer] → SchemaGenerator → OpenApiGenerator
```

`OpenApiGenerator` is the main orchestrator. It receives route definitions from `RouteAnalyzer`, then delegates to specialized generators (`ParameterGenerator`, `RequestBodyGenerator`, `ResponseSchemaGenerator`, etc.) which in turn consume data from analyzers.

### Key Design Patterns

- **Analyzers extract, Generators produce**: Analyzers read code via AST (nikic/php-parser) or Reflection and return structured DTOs. Generators convert those DTOs into OpenAPI-compliant arrays.
- **ErrorCollector pattern**: Analyzers must never throw exceptions. Errors are collected via `ErrorCollector` and categorized with `AnalyzerErrorType` enum. Analysis continues even when individual items fail.
- **SchemaRegistry**: Resource schemas are registered once and referenced via `$ref` throughout the spec to avoid duplication.
- **AST Visitors**: Static code analysis uses PHP-Parser's `NodeVisitorAbstract` pattern (see `src/Analyzers/AST/Visitors/`).
- **DTOs** (`src/DTO/`): Typed value objects carry data between analyzers and generators. Key ones: `RouteInfo`, `ControllerInfo`, `ResourceInfo`, `OpenApiOperation`, `OpenApiSpec`.

### Component Map

| Directory | Role |
|-----------|------|
| `src/Analyzers/` | Code extraction (routes, controllers, FormRequests, resources, auth, enums) |
| `src/Generators/` | OpenAPI spec generation (schemas, parameters, responses, security, examples) |
| `src/Converters/` | OpenAPI version conversion (3.0 → 3.1) |
| `src/Exporters/` | Export to Postman/Insomnia |
| `src/MockServer/` | Built-in mock server (Workerman) |
| `src/Performance/` | Parallel processing (spatie/fork), chunking, memory management |
| `src/Console/` | Artisan commands (`spectrum:generate`, `spectrum:watch`, `spectrum:cache`, `spectrum:mock`, `spectrum:export`) |
| `src/Support/` | Shared utilities, error collection, type inference |

### Test Structure

| Directory | Purpose |
|-----------|---------|
| `tests/Unit/` | Unit tests for individual analyzers and generators |
| `tests/Feature/` | Integration tests with Laravel app context |
| `tests/Performance/` | Performance benchmarks |
| `tests/Fixtures/` | Fake controllers, FormRequests, resources, models for testing |

## Development Rules

- **Test-first**: Write a failing test before implementing code.
- **PHPStan level 6**: No new baseline additions. All errors must be fixed.
- **`declare(strict_types=1)`** in all PHP files.
- **Conventional commits**: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`
- **Naming**: `{Name}Analyzer`, `{Name}Generator`, `{Name}Visitor`, `Has{Feature}` (traits)
- **Branch naming**: `feature/`, `fix/`, `refactor/`

## Configuration

Main config: `config/spectrum.php`. Key sections: `route_patterns`, `openapi.version` (3.0.0 or 3.1.0), `authentication`, `transformers`, `performance`, `example_generation`.
