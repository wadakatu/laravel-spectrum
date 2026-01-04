# Improve type definitions in ValidationRuleTypeMapper

## Summary

`src/Support/ValidationRuleTypeMapper.php` uses `array<mixed>` in 6 places where more specific types could be defined. This reduces type safety and makes static analysis less effective.

## Current State

The following methods use `array<mixed>` for the `$rules` parameter:

| Method | Line | Current Type |
|--------|------|--------------|
| `inferType()` | 88 | `array<mixed>` |
| `inferFormat()` | 157 | `array<mixed>` |
| `hasEnumRule()` | 197 | `array<mixed>` |
| `extractEnumValues()` | 221 | `array<mixed>` |
| `extractConstraints()` | 276 | `array<mixed>` |
| `normalizeRules()` | 402-403 | `string\|array<mixed>\|null` → `array<mixed>` |

## Analysis

Looking at the actual usage in the code:

```php
foreach ($rules as $rule) {
    if (! is_string($rule)) {
        continue;
    }
    // ... process string rules
}

// Also handles objects:
if (is_object($rule) && class_basename($rule) === 'In') {
    // Handle Rule::in() object
}
```

The `$rules` array actually contains:
1. **Strings** - e.g., `'required'`, `'email'`, `'max:255'`, `'in:a,b,c'`
2. **Objects** - Laravel validation rule objects like `Rule::in()`, `Rule::enum()`, etc.

## Proposed Solution

Replace `array<mixed>` with more specific types:

### Option A: Simple union type
```php
/**
 * @param  array<int, string|object>  $rules
 */
```

### Option B: With Laravel contract (more precise)
```php
/**
 * @param  array<int, string|\Illuminate\Contracts\Validation\ValidationRule|\Illuminate\Contracts\Validation\Rule|\Illuminate\Contracts\Validation\InvokableRule>  $rules
 */
```

### Option C: Create a type alias (recommended for reusability)
```php
// In a shared types file or at the class level:
/**
 * @phpstan-type ValidationRule string|object
 * @phpstan-type ValidationRules array<int, ValidationRule>
 */

// Then use:
/**
 * @param  ValidationRules  $rules
 */
```

## Files to Modify

- `src/Support/ValidationRuleTypeMapper.php`

## Benefits

1. **Better static analysis** - PHPStan can catch type errors
2. **Improved IDE support** - Better autocomplete and type hints
3. **Self-documenting code** - Clearer expectations for developers
4. **Reduced PHPStan baseline** - Helps eliminate `missingType.iterableValue` errors

## Related

- PHPStan baseline currently has 347 `missingType.iterableValue` errors
- This is part of an ongoing effort to improve type safety across the codebase

## Labels

- `enhancement`
- `type-safety`
- `good first issue`
