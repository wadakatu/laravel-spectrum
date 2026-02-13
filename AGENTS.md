# AGENTS.md

Canonical instructions for coding agents in this repository.

## Scope And Priority

- This file is the single source of truth for agent behavior in this repo.
- If `CLAUDE.md` exists, it should reference this file instead of duplicating rules.
- Keep this file concise and action-oriented. Link to detailed docs instead of copying long text.
- Prefer explicit commands and measurable checks over vague guidance.

## Project Snapshot

- Package: `wadakatu/laravel-spectrum`
- Purpose: OpenAPI generation from existing Laravel code (zero annotations)
- Runtime: PHP `^8.2`
- Laravel support: `11.x` and `12.x`
- Namespace: `LaravelSpectrum\\`
- Entry point: `LaravelSpectrum\SpectrumServiceProvider`

## Working Style

- Make minimal, single-purpose changes.
- Do not modify unrelated files.
- Do not revert user changes unless explicitly requested.
- Avoid destructive Git commands (for example: `git reset --hard`, `git checkout --`).
- When assumptions are required, state them briefly in the PR description.

## Quick Commands

```bash
# Install
composer install

# Tests
composer test
composer test-coverage
vendor/bin/phpunit tests/Unit/Analyzers/FormRequestAnalyzerTest.php
vendor/bin/phpunit --filter testMethodName

# Formatting / Static analysis
composer format
composer format:fix
composer analyze

# Mutation testing
composer infection

# Recommended pre-push gate
composer format:fix && composer analyze && composer test
```

## Repository Map

- `src/Analyzers/`: Extract route/controller/request/resource/auth data
- `src/Generators/`: Build OpenAPI output from DTO/analyzer results
- `src/Converters/`: OpenAPI version conversion (3.0/3.1)
- `src/Console/`: Artisan commands (`spectrum:*`)
- `src/MockServer/`: Mock API server implementation
- `src/Support/`: Shared utilities, typing, helpers
- `tests/Unit/`: Unit tests
- `tests/Feature/`: Integration tests
- `tests/Performance/`: Performance tests
- `tests/Fixtures/`: Fixtures

## Code Rules

- Use `declare(strict_types=1);` in PHP files.
- Follow existing naming conventions (`*Analyzer`, `*Generator`, `*Visitor`).
- Keep analyzers resilient: collect errors, avoid hard-fail behavior where existing patterns use collectors.
- Add/update tests for behavior changes.
- Do not add new PHPStan baseline entries as a shortcut.

## Change Workflow

1. Identify the exact component and nearest existing tests.
2. Add or update tests first when practical.
3. Implement the smallest safe change.
4. Run relevant checks locally.
5. Update docs when public behavior, CLI usage, or config contracts change.

## Verification Policy

- Docs-only change: no mandatory test run, but ensure examples/commands are valid.
- Code change in `src/`: run at least `composer format` and `composer test`.
- Behavior/schema/analyzer change: run `composer format:fix && composer analyze && composer test`.
- If verification is skipped, explain why in the PR.

## PR Policy

- Use Conventional Commits (`feat:`, `fix:`, `docs:`, `test:`, `refactor:`).
- Keep PRs focused on one concern.
- Include:
  - Summary of intent
  - Files/components changed
  - Verification commands run
  - Risks or follow-ups

## References

- Repo overview: `README.md`
- Contribution flow: `CONTRIBUTING.md`
- Detailed contributor docs: `docs/en/contributing.md`, `docs/ja/contributing.md`
