# Improve type definitions in ExampleValueFactory

## Summary

`src/Generators/ExampleValueFactory.php` uses `mixed` return type in 4 methods where the actual return types are more constrained. The `$fieldSchema` parameter also lacks proper typing.

## Current State

### Methods returning `mixed`

| Method | Line | Actual Return Types |
|--------|------|---------------------|
| `create()` | 46 | `string\|int\|float\|bool\|array\|null` |
| `generateByType()` | 90 | `string\|int\|float\|bool\|array` |
| `selectEnumValue()` | 126 | Same type as enum array elements |
| `handleSpecialFields()` | 138 | `string\|null` |

### Parameters with untyped arrays

| Method | Parameter | Line |
|--------|-----------|------|
| `create()` | `$fieldSchema` | 46 |
| `handleSpecialFields()` | `$fieldSchema` | 138 |
| `generateContextualName()` | `$fieldSchema` | 225 |

## Analysis

Looking at the code:

```php
public function create(string $fieldName, array $fieldSchema, ?callable $customGenerator = null): mixed
{
    // Returns from various sources:
    // - $fieldSchema['const'] (any type)
    // - $fieldSchema['examples'][0] (any type)
    // - enum value (any type)
    // - $fieldSchema['default'] (any type)
    // - strategy->generate() result
}
```

The `$fieldSchema` follows OpenAPI schema structure with known keys:
- `type`: string
- `format`: string|null
- `const`: mixed
- `examples`: array
- `enum`: array
- `default`: mixed

## Proposed Solution

### Option A: Define OpenAPI schema type alias

```php
/**
 * @phpstan-type OpenApiSchema array{
 *     type?: string,
 *     format?: string,
 *     const?: scalar|array|null,
 *     examples?: array<int, scalar|array|null>,
 *     enum?: array<int, scalar>,
 *     default?: scalar|array|null,
 *     nullable?: bool,
 *     properties?: array<string, OpenApiSchema>,
 *     items?: OpenApiSchema
 * }
 */
class ExampleValueFactory
{
    /**
     * @param  OpenApiSchema  $fieldSchema
     * @return scalar|array|null
     */
    public function create(string $fieldName, array $fieldSchema, ?callable $customGenerator = null): mixed
}
```

### Option B: Use scalar union type

```php
/**
 * @return string|int|float|bool|array<mixed>|null
 */
public function create(string $fieldName, array $fieldSchema, ?callable $customGenerator = null): string|int|float|bool|array|null
```

### Option C: Create ExampleValue DTO

```php
readonly class ExampleValue
{
    public function __construct(
        public string|int|float|bool|array|null $value,
        public string $type,
    ) {}
}
```

## Specific Method Fixes

### `handleSpecialFields()` - Line 138

This method only returns `string|null` based on the code analysis:

```php
private function handleSpecialFields(string $fieldName, array $fieldSchema): ?string
{
    // All return paths return string or null
    return $this->generateTimestamp($lower);      // returns ?string
    return $this->generateContextualName(...);    // returns string
    return $this->generatePhone();                // returns string
    return $this->generateImageUrl(...);          // returns string
    return null;
}
```

## Files to Modify

- `src/Generators/ExampleValueFactory.php`

## Benefits

1. **Clearer API contracts** - Developers know what types to expect
2. **Better IDE support** - Type hints for return values
3. **Catch type mismatches** - Static analysis can detect issues
4. **Documentation** - Self-documenting code

## Labels

- `enhancement`
- `type-safety`
