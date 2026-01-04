# Improve type definitions in DocumentationCache

## Summary

`src/Cache/DocumentationCache.php` uses `mixed` type and untyped arrays in multiple places where more specific types could be defined. This is a generic cache class, but we can improve type safety using generics.

## Current State

### Methods using `mixed` type

| Method | Line | Issue |
|--------|------|-------|
| `remember()` | 42 | Returns `mixed`, parameter `$data` in `put()` is `mixed` |
| `get()` | 193 | Returns `mixed` |
| `put()` | 165 | Parameter `$data` is `mixed` |

### Methods with untyped arrays

| Method | Line | Issue |
|--------|------|-------|
| `remember()` | 42 | `$dependencies` is `array` without value type |
| `rememberFormRequest()` | 69 | Returns `array` without type specification |
| `rememberResource()` | 87 | Returns `array` without type specification |
| `rememberRoutes()` | 111 | Returns `array` without type specification |
| `getMetadata()` | 229 | Returns `?array` without type specification |
| `findResourceDependencies()` | 419 | Returns `array` without type specification |
| `getAllCacheKeys()` | 459 | Returns `array` without type specification |

## Analysis

The cache stores various types of data:
- FormRequest analysis results (array structures)
- Resource analysis results (array structures)
- Route analysis results (array structures)

The `mixed` type is used because the cache is generic, but we can improve this with PHPStan generics.

## Proposed Solution

### Option A: Use PHPStan generics for the remember method

```php
/**
 * @template T
 * @param  \Closure(): T  $callback
 * @param  array<string>  $dependencies  File paths
 * @return T
 */
public function remember(string $key, \Closure $callback, array $dependencies = []): mixed
```

### Option B: Create specific cache methods with proper return types

The class already has specific methods like `rememberFormRequest()`, `rememberResource()`, etc. We should define proper return types for these:

```php
/**
 * @return array{
 *     rules: array<string, array<string|object>>,
 *     attributes: array<string, string>,
 *     messages: array<string, string>
 * }
 */
public function rememberFormRequest(string $requestClass, \Closure $callback): array
```

### Option C: Define DTOs for cache data structures

Create typed DTOs for each cache data type:

```php
// FormRequestCacheData DTO
readonly class FormRequestCacheData {
    public function __construct(
        public array $rules,
        public array $attributes,
        public array $messages,
    ) {}
}
```

## Files to Modify

- `src/Cache/DocumentationCache.php`
- Possibly create new DTOs in `src/DTO/Cache/`

## Benefits

1. **Type-safe caching** - Compile-time checks for cached data structures
2. **Better IDE support** - Autocomplete for cached data
3. **Documentation** - Self-documenting cache contracts
4. **Reduced PHPStan baseline** - Eliminate multiple `missingType.iterableValue` errors

## Labels

- `enhancement`
- `type-safety`
