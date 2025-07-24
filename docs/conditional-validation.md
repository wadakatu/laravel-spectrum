# Conditional Validation Rules

Laravel Spectrum automatically detects and documents conditional validation rules in your FormRequest classes. This powerful feature generates OpenAPI 3.0 `oneOf` schemas to accurately represent different validation scenarios.

## Overview

When your FormRequest has different validation rules based on conditions (like HTTP methods, user states, or other logic), Laravel Spectrum will:

1. Analyze the AST (Abstract Syntax Tree) of your FormRequest class
2. Extract all possible rule sets and their conditions
3. Generate appropriate OpenAPI schemas using `oneOf`
4. Add descriptions explaining when each schema applies

## Basic Example

### FormRequest with HTTP Method Conditions

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'email' => 'required|email|unique:users',
                'password' => 'required|min:8',
                'name' => 'required|string|max:255',
            ];
        }

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            return [
                'email' => 'sometimes|email|unique:users,email,' . $this->user->id,
                'name' => 'sometimes|string|max:255',
                'current_password' => 'required_with:password|string',
                'password' => 'sometimes|min:8|confirmed',
            ];
        }

        return [
            'name' => 'required|string',
        ];
    }
}
```

### Generated OpenAPI Schema

```json
{
  "requestBody": {
    "required": true,
    "content": {
      "application/json": {
        "schema": {
          "oneOf": [
            {
              "type": "object",
              "properties": {
                "email": {
                  "type": "string",
                  "format": "email"
                },
                "password": {
                  "type": "string",
                  "minLength": 8
                },
                "name": {
                  "type": "string",
                  "maxLength": 255
                }
              },
              "required": ["email", "password", "name"],
              "description": "When HTTP method is POST"
            },
            {
              "type": "object",
              "properties": {
                "email": {
                  "type": "string",
                  "format": "email"
                },
                "name": {
                  "type": "string",
                  "maxLength": 255
                },
                "current_password": {
                  "type": "string"
                },
                "password": {
                  "type": "string",
                  "minLength": 8
                }
              },
              "required": ["current_password"],
              "description": "When $this->isMethod('PUT') || $this->isMethod('PATCH')"
            },
            {
              "type": "object",
              "properties": {
                "name": {
                  "type": "string"
                }
              },
              "required": ["name"],
              "description": "Default rules"
            }
          ],
          "discriminator": {
            "propertyName": "_condition"
          }
        }
      }
    }
  }
}
```

## Advanced Patterns

### 1. User-Based Conditions

```php
public function rules(): array
{
    if ($this->user() && $this->user()->isAdmin()) {
        return [
            'title' => 'required|string',
            'content' => 'required|string',
            'published_at' => 'required|date',
            'featured' => 'boolean',
        ];
    }

    return [
        'title' => 'required|string',
        'content' => 'required|string',
    ];
}
```

### 2. Nested Conditions

```php
public function rules(): array
{
    if ($this->isMethod('POST')) {
        if ($this->input('type') === 'premium') {
            return [
                'name' => 'required|string',
                'features' => 'required|array|min:3',
                'price' => 'required|numeric|min:100',
            ];
        }
        
        return [
            'name' => 'required|string',
            'features' => 'required|array',
            'price' => 'required|numeric|min:0',
        ];
    }

    return [
        'name' => 'sometimes|string',
    ];
}
```

### 3. Variable Assignment with array_merge()

```php
public function rules(): array
{
    $baseRules = [
        'title' => 'required|string|max:255',
        'slug' => 'required|string|unique:posts',
    ];

    if ($this->isMethod('POST')) {
        return array_merge($baseRules, [
            'content' => 'required|string|min:100',
            'category_id' => 'required|exists:categories,id',
            'tags' => 'array',
        ]);
    }

    if ($this->isMethod('PUT')) {
        $baseRules['slug'] = 'required|string|unique:posts,slug,' . $this->post->id;
        
        return array_merge($baseRules, [
            'content' => 'sometimes|string|min:100',
            'category_id' => 'sometimes|exists:categories,id',
        ]);
    }

    return $baseRules;
}
```

### 4. Using Laravel Rule Classes

```php
use Illuminate\Validation\Rule;

public function rules(): array
{
    if ($this->isMethod('POST')) {
        return [
            'email' => ['required', 'email', Rule::unique('users')],
            'role' => ['required', Rule::in(['admin', 'editor', 'user'])],
            'status' => ['required', Rule::in(UserStatus::cases())], // Enum support
        ];
    }

    return [
        'email' => [
            'sometimes',
            'email',
            Rule::unique('users')->ignore($this->user()->id),
        ],
        'role' => ['sometimes', Rule::in(['admin', 'editor', 'user'])],
    ];
}
```

### 5. Request Helper Function

```php
public function rules(): array
{
    if (request()->isMethod('POST')) {
        return [
            'name' => 'required|string',
            'email' => 'required|email',
        ];
    }

    return [
        'name' => 'sometimes|string',
        'email' => 'sometimes|email',
    ];
}
```

## Supported Condition Types

Laravel Spectrum recognizes and documents the following condition types:

1. **HTTP Method Conditions**
   - `$this->isMethod('POST')`
   - `request()->isMethod('GET')`
   - `$request->isMethod('PUT')`

2. **User State Conditions**
   - `$this->user()`
   - `$this->user()->hasRole('admin')`
   - `auth()->check()`

3. **Input Value Conditions**
   - `$this->input('type') === 'premium'`
   - `$this->has('field')`
   - `$this->filled('field')`

4. **Custom Method Calls**
   - Any method that returns boolean
   - Chained method calls

5. **Logical Operators**
   - `&&` (AND)
   - `||` (OR)
   - `!` (NOT)

## Best Practices

1. **Keep Conditions Simple**: Complex nested conditions can be hard to understand in documentation
2. **Use Descriptive Methods**: Create methods with clear names for complex conditions
3. **Document Edge Cases**: Add comments in your code for unusual validation scenarios
4. **Test Your Rules**: Ensure all condition branches are tested

## Limitations

1. **Dynamic Conditions**: Conditions that depend on database state or external APIs may not be fully captured
2. **Runtime Evaluation**: Some complex runtime evaluations might be simplified in the documentation
3. **Method Implementations**: Private method implementations are not analyzed deeply

## Configuration

You can enable or disable conditional validation analysis in your `config/spectrum.php`:

```php
return [
    // ... other config
    
    'analyze_conditional_rules' => true, // Enable/disable conditional rules analysis
    
    'conditional_rules_depth' => 3, // Maximum nesting depth for condition analysis
];
```

## Troubleshooting

### Rules Not Detected as Conditional

If your conditional rules are not being detected:

1. Ensure your FormRequest extends `Illuminate\Foundation\Http\FormRequest`
2. Check that your conditions are in the `rules()` method
3. Verify that your PHP syntax is valid
4. Try simplifying complex conditions

### Performance Considerations

Analyzing conditional rules requires AST parsing, which can be resource-intensive for large codebases:

1. Use caching: `php artisan spectrum:cache`
2. Limit route patterns to only API routes
3. Consider generating documentation in CI/CD pipeline

## Examples in Demo App

Check out the demo application for real-world examples:

- `demo-app/laravel-app/app/Http/Requests/ConditionalUserRequest.php`
- `demo-app/laravel-app/app/Http/Controllers/ConditionalUserController.php`

These examples demonstrate various conditional validation patterns and how they appear in the generated documentation.