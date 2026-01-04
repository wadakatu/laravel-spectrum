# Improve type definitions in QueryParameterTypeInference

## Summary

`src/Support/QueryParameterTypeInference.php` uses `array<mixed>` for validation rules parameters and `array<string, mixed>` for context parameters. These can be replaced with more specific types.

## Current State

### Methods using `array<mixed>` for rules

| Method | Line | Current Type |
|--------|------|--------------|
| `inferFromValidationRules()` | 122-124 | `array<mixed>` |
| `getConstraintsFromRules()` | 192-195 | `array<mixed>` |
| `hasEnumRule()` | 203-205 | `array<mixed>` |
| `hasExplicitStringRule()` | 213-215 | `array<mixed>` |

### Methods using `array<string, mixed>` for context

| Method | Line | Current Type |
|--------|------|--------------|
| `inferFromContext()` | 70 | `array<string, mixed>` |
| `detectEnumValues()` | 145-146 | `array<string, mixed>`, returns `array<mixed>\|null` |
| `getFormatForType()` | 171 | `array<string, mixed>` |

## Analysis

### Validation Rules

The `$rules` parameter represents Laravel validation rules, which are:
- Strings like `'required'`, `'email'`, `'max:255'`
- Objects implementing `Illuminate\Contracts\Validation\Rule`

This is the same pattern as `ValidationRuleTypeMapper.php`.

### Context Array

The `$context` array has a known structure:

```php
$context = [
    'numeric_operation' => 'float'|'double'|'int',
    'array_operation' => bool,
    'boolean_context' => bool,
    'date_operation' => bool,
    'date_format' => string,
    'where_clause' => ['column' => string],
    'enum_values' => array<scalar>,
    'in_array' => array<scalar>,
    'switch_cases' => array<scalar>,
    'validation_rules' => array<string|object>,
];
```

## Proposed Solution

### For validation rules (consistent with ValidationRuleTypeMapper)

```php
/**
 * @phpstan-type ValidationRule string|object
 * @phpstan-type ValidationRules array<int, ValidationRule>
 */

/**
 * @param  ValidationRules  $rules
 */
public function inferFromValidationRules(array $rules): ?string
```

### For context array

```php
/**
 * @phpstan-type TypeInferenceContext array{
 *     numeric_operation?: 'float'|'double'|'int',
 *     array_operation?: bool,
 *     boolean_context?: bool,
 *     date_operation?: bool,
 *     date_format?: string,
 *     where_clause?: array{column: string},
 *     enum_values?: array<int, scalar>,
 *     in_array?: array<int, scalar>,
 *     switch_cases?: array<int, scalar>,
 *     validation_rules?: array<int, string|object>
 * }
 */

/**
 * @param  TypeInferenceContext  $context
 */
public function inferFromContext(array $context): ?string
```

### For detectEnumValues return type

```php
/**
 * @param  TypeInferenceContext  $context
 * @return array<int, scalar>|null
 */
public function detectEnumValues(array $context): ?array
```

## Files to Modify

- `src/Support/QueryParameterTypeInference.php`

## Related Issues

- This class uses `ValidationRuleTypeMapper`, so the type aliases should be shared or imported

## Benefits

1. **Consistent typing** - Same validation rule type as `ValidationRuleTypeMapper`
2. **Context structure documentation** - Clear contract for context array
3. **Better static analysis** - PHPStan can validate context key access
4. **IDE support** - Autocomplete for context keys

## Labels

- `enhancement`
- `type-safety`
