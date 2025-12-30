# Stability and Backward Compatibility

This document describes Laravel Spectrum's stability guarantees and backward compatibility policy.

## Versioning

Laravel Spectrum follows [Semantic Versioning 2.0.0](https://semver.org/):

- **MAJOR** version (X.0.0): Breaking changes to the public API
- **MINOR** version (0.X.0): New features, backward-compatible
- **PATCH** version (0.0.X): Bug fixes, backward-compatible

## Public API Definition

The following components are considered part of the **public API** and are covered by our backward compatibility promise:

### Artisan Commands

| Command | Description |
|---------|-------------|
| `spectrum:generate` | Generate OpenAPI documentation |
| `spectrum:mock` | Start mock API server |
| `spectrum:export:postman` | Export to Postman collection |
| `spectrum:export:insomnia` | Export to Insomnia collection |

Command names, required arguments, and option behavior will not change in minor/patch releases.

### Configuration (`config/spectrum.php`)

All configuration keys and their expected value types are stable. New configuration options may be added in minor releases with sensible defaults.

### Contracts (Interfaces)

Interfaces in `src/Contracts/` are stable:

- `HasCustomExamples` - Custom example generation
- `HasExamples` - Resource example definition
- `HasErrors` - Error collection
- `ExampleGenerationStrategy` - Custom generation strategies
- Analyzer contracts in `src/Contracts/Analyzers/`
- Performance contracts in `src/Contracts/Performance/`

### Generated Output

The structure of generated OpenAPI 3.0 specifications is stable. The output will remain valid OpenAPI 3.0 format.

## What's NOT Covered

The following are **internal implementation details** and may change without notice:

- Internal classes in `src/Analyzers/Support/`
- Private/protected methods
- Class constructor signatures (use dependency injection container)
- PHPStan baseline entries
- Test fixtures and test utilities
- Development dependencies

## Backward Compatibility Promise

### Within Minor/Patch Releases

We guarantee:

1. **CLI commands** - Same behavior, no removed options
2. **Configuration** - Existing keys work the same way
3. **Contracts** - No breaking interface changes
4. **OpenAPI output** - Valid 3.0 specification format

### What May Change

Even in minor/patch releases:

1. **Bug fixes** - May change incorrect behavior
2. **Analysis improvements** - Better type inference may detect more routes/parameters
3. **Generated examples** - Faker-generated values are not deterministic
4. **Error messages** - Wording may improve
5. **Performance** - Internal optimizations

## Deprecation Policy

When features are deprecated:

1. **Announcement** - Deprecation noted in CHANGELOG.md
2. **Warning** - Runtime deprecation warnings for at least one minor version
3. **Documentation** - Migration guide provided
4. **Removal** - Only in next major version

### Deprecation Annotations

```php
/**
 * @deprecated since 1.2.0, use NewClass instead
 */
```

## Supported Versions

| Version | PHP | Laravel | Status |
|---------|-----|---------|--------|
| 1.x | 8.1 - 8.4 | 11.x, 12.x | Active |

Security fixes are backported to supported versions for at least 12 months after a new major release.

## Recommended Usage

For stability, we recommend:

```json
{
    "require": {
        "wadakatu/laravel-spectrum": "^1.0"
    }
}
```

Using the caret (`^`) constraint ensures you receive bug fixes and new features while avoiding breaking changes.

**Always commit `composer.lock`** to ensure consistent behavior across environments.

## Reporting Compatibility Issues

If you encounter an unexpected breaking change in a minor/patch release, please [open an issue](https://github.com/wadakatu/laravel-spectrum/issues) with:

1. Previous working version
2. Current broken version
3. Code sample demonstrating the break
4. Expected vs actual behavior
