# Improve type definitions in FileUploadDetector

## Summary

`src/Support/FileUploadDetector.php` uses `array<mixed>` for validation rules and has some return types that could be more specific.

## Current State

### Methods with `array<mixed>` parameters

| Method | Line | Current Type |
|--------|------|--------------|
| `extractFileRules()` | 40-41 | `array<string, string\|array<mixed>>` |
| `extractSizeConstraints()` | 66-68 | `array<mixed>`, returns `array<string, int>` |
| `extractDimensionConstraints()` | 92-94 | `array<mixed>`, returns `array<string, mixed>` |
| `flattenRules()` | 125-127 | `array<mixed>`, returns `array<mixed>` |
| `detectFilePatterns()` | 146-148 | `array<string, string\|array<mixed>>` |

## Analysis

### Validation Rules Structure

The `$rules` parameter in `extractFileRules()` and `detectFilePatterns()` follows Laravel's validation rules format:

```php
$rules = [
    'avatar' => ['required', 'image', 'max:2048'],
    'document' => 'required|file|mimes:pdf,doc',
    'photos.*' => ['image', 'dimensions:min_width=100'],
];
```

So the type should be: `array<string, string|array<int, string|object>>`

### flattenRules() Method

This method processes individual field rules:
- Input: `['required', 'image', 'max:2048']` or `['required|image|max:2048']`
- Output: `['required', 'image', 'max:2048']`

Both input and output are arrays of strings or rule objects.

### extractDimensionConstraints() Return Type

Looking at the code:

```php
foreach ($pairs as $pair) {
    if ($key === 'ratio') {
        $dimensions[$key] = $value;  // string
    } else {
        $dimensions[$key] = (int) $value;  // int
    }
}
```

The return type is: `array{min_width?: int, max_width?: int, min_height?: int, max_height?: int, ratio?: string}`

## Proposed Solution

### Define validation rules type alias

```php
/**
 * @phpstan-type ValidationRule string|object
 * @phpstan-type FieldRules string|array<int, ValidationRule>
 * @phpstan-type FormRules array<string, FieldRules>
 */
```

### Update method signatures

```php
/**
 * @param  FormRules  $rules
 * @return array<string, array<int, ValidationRule>>
 */
public function extractFileRules(array $rules): array

/**
 * @param  array<int, ValidationRule>  $rules
 * @return array{min?: int, max?: int}
 */
public function extractSizeConstraints(array $rules): array

/**
 * @param  array<int, ValidationRule>  $rules
 * @return array{min_width?: int, max_width?: int, min_height?: int, max_height?: int, width?: int, height?: int, ratio?: string}
 */
public function extractDimensionConstraints(array $rules): array

/**
 * @param  array<int, ValidationRule>  $rules
 * @return array<int, ValidationRule>
 */
private function flattenRules(array $rules): array

/**
 * @param  FormRules  $rules
 * @return array{single_files: array<string>, array_files: array<string>, nested_files: array<string>}
 */
public function detectFilePatterns(array $rules): array
```

## Files to Modify

- `src/Support/FileUploadDetector.php`

## Related

- Uses `ValidationRuleCollection` which may also need type improvements
- Similar validation rules type as other Support classes

## Benefits

1. **Clear dimension constraints** - Known keys for dimension constraints
2. **Consistent validation rules type** - Shared with other classes
3. **Pattern detection clarity** - Explicit return structure
4. **Static analysis** - PHPStan can verify array key access

## Labels

- `enhancement`
- `type-safety`
