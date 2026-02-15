# Contributing to Laravel Spectrum

Thank you for your interest in contributing to Laravel Spectrum! This document provides quick guidance for contributors.

## Quick Start

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/laravel-spectrum.git
cd laravel-spectrum

# Install dependencies
composer install

# Run tests
composer test

# Run code quality checks
composer format:fix && composer analyze && composer test
```

## Development Commands

| Command | Description |
|---------|-------------|
| `composer test` | Run all tests |
| `composer test-coverage` | Run tests with coverage report |
| `composer format:fix` | Fix code style (Laravel Pint) |
| `composer analyze` | Run static analysis (PHPStan) |
| `composer infection` | Run full mutation testing (same MSI thresholds as CI full run) |

## Pull Request Process

1. **Create a branch** from `main`
   - Feature: `feature/description`
   - Bug fix: `fix/description`
   - Docs: `docs/description`

2. **Make your changes**
   - Follow PSR-12 coding standards
   - Write tests for new functionality
   - Update documentation if needed

3. **Run quality checks**
   ```bash
   composer format:fix && composer analyze && composer test
   ```

4. **Submit PR** with a clear description of changes

## Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add new feature
fix: resolve bug
docs: update documentation
test: add tests
refactor: code improvements
```

## Code Style

- PHP 8.1+ with strict types
- PSR-12 coding standard
- PHPStan level 5 compliance
- Comprehensive PHPDoc blocks

## Testing

- Write unit tests in `tests/Unit/`
- Write feature tests in `tests/Feature/`
- Use test fixtures in `tests/Fixtures/`
- Verify in demo app: `cd demo-app/laravel-12-app && php artisan spectrum:generate`

## Documentation

For detailed contribution guidelines, see:
- [Contributing Guide](docs/docs/contributing.md)

## Getting Help

- [GitHub Issues](https://github.com/wadakatu/laravel-spectrum/issues) - Bug reports and feature requests
- [GitHub Discussions](https://github.com/wadakatu/laravel-spectrum/discussions) - Questions and ideas

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
