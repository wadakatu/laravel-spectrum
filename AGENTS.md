# AGENTS.md

Guidance for coding agents working in this repository.

## Project Snapshot

- Package: `wadakatu/laravel-spectrum`
- Purpose: Generate OpenAPI docs from Laravel code without annotations.
- Runtime: PHP `^8.2`
- Laravel support: `11.x` and `12.x`
- Main namespace: `LaravelSpectrum\\`

## Setup

```bash
composer install
```

## Common Commands

```bash
# Tests
composer test
composer test-coverage

# Code style
composer format
composer format:fix

# Static analysis
composer analyze

# Mutation testing
composer infection

# Recommended local check before pushing
composer format:fix && composer analyze && composer test
```

## Repository Map

- `src/Analyzers/`: Extract route/controller/request/resource data
- `src/Generators/`: Build OpenAPI structures from DTOs
- `src/Converters/`: OpenAPI version conversions
- `src/Console/`: Artisan commands
- `src/MockServer/`: Mock server implementation
- `src/Support/`: Shared utilities and helpers
- `tests/Unit/`: Unit tests for analyzers/generators
- `tests/Feature/`: Integration tests
- `tests/Fixtures/`: Test fixtures

## Engineering Rules

- Use `declare(strict_types=1);` in PHP files.
- Follow existing naming patterns: `*Analyzer`, `*Generator`, `*Visitor`.
- Prefer small, focused changes over broad refactors.
- Add or update tests when behavior changes.
- Keep analyzer failures non-fatal; use the existing error collection patterns.
- Do not introduce new PHPStan baseline entries.

## Change Workflow

1. Understand the affected pipeline stage(s) and existing tests.
2. Add/adjust tests first when practical.
3. Implement the smallest safe change.
4. Run quality gates (`format:fix`, `analyze`, `test`).
5. Update docs when public behavior or command usage changes.

## Pull Request Expectations

- Use conventional commits (`feat:`, `fix:`, `docs:`, `test:`, `refactor:`).
- Keep PR scope single-purpose.
- Include a clear summary and verification steps in the PR body.

