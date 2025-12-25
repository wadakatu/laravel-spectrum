# Testing Rules

## Test-First Development (Mandatory)

All new features and bug fixes MUST follow TDD:

1. Write a failing test first
2. Implement minimal code to make it pass
3. Refactor while keeping tests green

## Test Structure

```
tests/
├── Unit/           # Unit tests for individual components
├── Feature/        # Integration tests
├── E2E/            # End-to-end tests
├── Performance/    # Performance benchmarks
└── Fixtures/       # Test data and mock classes
```

## Test Commands

```bash
composer test                 # Run all PHPUnit tests
composer test-coverage        # Generate HTML coverage report
vendor/bin/phpunit tests/Unit/FormRequestAnalyzerTest.php  # Specific file
vendor/bin/phpunit --filter testMethodName                  # Specific method
```

## Test Naming Conventions

- Test classes: `{ClassName}Test` (e.g., `FormRequestAnalyzerTest`)
- Test methods: `test_{action}_{condition}_{expected_result}` or camelCase with `@test` annotation
- Use descriptive names that explain the scenario

## Test Fixtures

- Place test fixtures in `tests/Fixtures/`
- Use realistic data that represents actual use cases
- Prefer factories for complex object creation

## Demo App Testing (Required)

Before submitting PRs, verify functionality in the demo app:

```bash
cd demo-app/laravel-12-app
php artisan spectrum:generate
# Check output in storage/app/spectrum/openapi.json
```

## Coverage Requirements

- Aim for high coverage on Analyzers and Generators
- Critical paths must have 100% coverage
- Edge cases should be explicitly tested
