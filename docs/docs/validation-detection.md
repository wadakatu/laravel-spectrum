# Validation Detection Guide

This guide details Laravel Spectrum's powerful validation detection capabilities. It automatically detects and documents various validation patterns including FormRequest classes, inline validation, and conditional validation.

## 📋 Supported Validation Methods

### 1. FormRequest Classes

The most recommended approach. Laravel Spectrum fully analyzes `FormRequest`.

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'age' => 'nullable|integer|min:0|max:150',
            'role' => 'required|in:admin,editor,viewer',
            'profile_image' => 'nullable|image|mimes:jpeg,png|max:2048',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'User Name',
            'email' => 'Email Address',
            'password' => 'Password',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'User name is required.',
            'email.unique' => 'This email address is already in use.',
        ];
    }
}
```

### 2. Inline Validation

The `validate()` method within controllers is also detected.

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'content' => 'required|string',
        'published_at' => 'nullable|date',
        'tags' => 'array',
        'tags.*' => 'string|max:50',
    ]);

    // ...
}
```

### 3. Validator Facade

```php
public function update(Request $request, $id)
{
    $validator = Validator::make($request->all(), [
        'name' => 'sometimes|required|string|max:255',
        'status' => 'sometimes|required|in:active,inactive',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // ...
}
```

## 🎯 Advanced Validation Features

### Arrays and Nested Data

```php
public function rules()
{
    return [
        'users' => 'required|array|min:1',
        'users.*.name' => 'required|string',
        'users.*.email' => 'required|email|distinct',
        'users.*.profile' => 'required|array',
        'users.*.profile.bio' => 'nullable|string|max:500',
        'users.*.profile.avatar' => 'nullable|url',
        
        'settings' => 'required|array',
        'settings.notifications' => 'required|array',
        'settings.notifications.email' => 'boolean',
        'settings.notifications.push' => 'boolean',
    ];
}
```

Generated OpenAPI schema:

```json
{
  "users": {
    "type": "array",
    "minItems": 1,
    "items": {
      "type": "object",
      "required": ["name", "email", "profile"],
      "properties": {
        "name": { "type": "string" },
        "email": { "type": "string", "format": "email" },
        "profile": {
          "type": "object",
          "properties": {
            "bio": { "type": "string", "maxLength": 500 },
            "avatar": { "type": "string", "format": "uri" }
          }
        }
      }
    }
  }
}
```

### File Uploads

```php
public function rules()
{
    return [
        'avatar' => 'required|image|mimes:jpeg,png,jpg|max:2048|dimensions:min_width=100,min_height=100',
        'document' => 'required|file|mimes:pdf,doc,docx|max:10240',
        'gallery' => 'required|array|max:5',
        'gallery.*' => 'image|mimes:jpeg,png|max:1024',
    ];
}
```

Properly documented with `multipart/form-data` encoding.

### Custom Validation Rules

```php
use App\Rules\PhoneNumber;
use Illuminate\Validation\Rule;

public function rules()
{
    return [
        'phone' => ['required', new PhoneNumber],
        'country' => ['required', Rule::in(['JP', 'US', 'UK'])],
        'username' => [
            'required',
            'string',
            'max:30',
            Rule::unique('users')->ignore($this->user),
        ],
    ];
}
```

### Enum Type Detection

```php
use App\Enums\UserStatus;
use App\Enums\UserRole;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

public function rules()
{
    return [
        // Using Rule::enum
        'status' => ['required', Rule::enum(UserStatus::class)],
        
        // Using Enum rule class
        'role' => ['required', new Enum(UserRole::class)],
        
        // String-based in validation also supported
        'priority' => 'required|in:low,medium,high,urgent',
    ];
}
```

## ⚡ Conditional Validation

### Based on HTTP Method

```php
public function rules()
{
    $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email',
    ];

    if ($this->isMethod('POST')) {
        $rules['password'] = 'required|string|min:8|confirmed';
        $rules['email'] .= '|unique:users';
    } elseif ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
        $rules['password'] = 'sometimes|nullable|string|min:8|confirmed';
        $rules['email'] .= '|unique:users,email,' . $this->route('user');
    }

    return $rules;
}
```

### Based on Other Fields

```php
public function rules()
{
    return [
        'type' => 'required|in:personal,business',
        'first_name' => 'required_if:type,personal|string|max:255',
        'last_name' => 'required_if:type,personal|string|max:255',
        'company_name' => 'required_if:type,business|string|max:255',
        'tax_id' => 'required_if:type,business|string|regex:/^[A-Z0-9\-]+$/',
        
        'subscribe_newsletter' => 'boolean',
        'email_frequency' => 'required_if:subscribe_newsletter,true|in:daily,weekly,monthly',
    ];
}
```

### Using Rule::when

```php
use Illuminate\Validation\Rule;

public function rules()
{
    return [
        'account_type' => 'required|in:free,premium,enterprise',
        
        'payment_method' => Rule::when(
            in_array($this->account_type, ['premium', 'enterprise']),
            'required|in:credit_card,paypal,bank_transfer',
            'nullable'
        ),
        
        'billing_address' => Rule::when(
            fn() => $this->account_type !== 'free' && $this->payment_method === 'credit_card',
            'required|array',
            'nullable'
        ),
    ];
}
```

### Complex Conditional Validation

```php
public function rules()
{
    $rules = [
        'event_type' => 'required|in:online,offline,hybrid',
        'location' => 'required_unless:event_type,online|string',
        'streaming_url' => 'required_if:event_type,online,hybrid|url',
        'max_attendees' => 'required|integer|min:1',
    ];

    // Additional rules based on conditions
    if ($this->event_type === 'offline' || $this->event_type === 'hybrid') {
        $rules['venue_capacity'] = 'required|integer|gte:max_attendees';
        $rules['safety_measures'] = 'required|array';
    }

    return $rules;
}
```

## 🔧 Type Inference and Schema Generation

### Type Inference from Validation Rules

| Validation Rule | Inferred Type | OpenAPI Schema |
|----------------|---------------|----------------|
| `string` | string | `{ "type": "string" }` |
| `integer` | integer | `{ "type": "integer" }` |
| `numeric` | number | `{ "type": "number" }` |
| `boolean` | boolean | `{ "type": "boolean" }` |
| `array` | array | `{ "type": "array" }` |
| `date` | string | `{ "type": "string", "format": "date" }` |
| `email` | string | `{ "type": "string", "format": "email" }` |
| `url` | string | `{ "type": "string", "format": "uri" }` |
| `file`/`image` | file | `{ "type": "string", "format": "binary" }` |

### Constraint Conversion

| Validation Rule | OpenAPI Constraint |
|----------------|-------------------|
| `min:3` | `{ "minimum": 3 }` (numeric) / `{ "minLength": 3 }` (string) |
| `max:100` | `{ "maximum": 100 }` (numeric) / `{ "maxLength": 100 }` (string) |
| `between:1,10` | `{ "minimum": 1, "maximum": 10 }` |
| `size:5` | `{ "minItems": 5, "maxItems": 5 }` (array) |
| `in:a,b,c` | `{ "enum": ["a", "b", "c"] }` |
| `regex:/pattern/` | `{ "pattern": "pattern" }` |

## 💡 Best Practices

### 1. Use FormRequest

```php
// ✅ Recommended
public function store(CreateUserRequest $request)
{
    $user = User::create($request->validated());
    return new UserResource($user);
}

// ❌ Not recommended (but works)
public function store(Request $request)
{
    $validated = $request->validate([...]);
}
```

### 2. Explicit Type Specification

```php
public function rules()
{
    return [
        // ✅ Clear types
        'age' => 'required|integer|min:0|max:150',
        'price' => 'required|numeric|min:0',
        'is_active' => 'required|boolean',
        
        // ❌ Ambiguous type
        'value' => 'required',
    ];
}
```

### 3. Meaningful Validation Messages

```php
public function messages()
{
    return [
        'email.unique' => 'This email address is already registered.',
        'password.min' => 'Password must be at least :min characters.',
        'age.between' => 'Age must be between :min and :max years.',
    ];
}

public function attributes()
{
    return [
        'email' => 'Email Address',
        'password' => 'Password',
        'age' => 'Age',
    ];
}
```

### 4. Validation Rule Reuse

```php
// Define common validation rules
trait CommonValidationRules
{
    protected function passwordRules(): array
    {
        return ['required', 'string', 'min:8', 'regex:/[A-Z]/', 'regex:/[0-9]/'];
    }

    protected function emailRules(bool $unique = true): array
    {
        $rules = ['required', 'email', 'max:255'];
        
        if ($unique) {
            $rules[] = 'unique:users,email';
        }
        
        return $rules;
    }
}
```

## 🔍 Troubleshooting

### When Validation Is Not Detected

1. **Check FormRequest's `authorize()` method**
   ```php
   public function authorize()
   {
       return true; // false prevents analysis
   }
   ```

2. **Check namespace and use statements**
   ```php
   use Illuminate\Foundation\Http\FormRequest; // Required
   ```

3. **Check type hints**
   ```php
   // ✅ Correct
   public function store(CreateUserRequest $request)
   
   // ❌ Not detected
   public function store($request)
   ```

### When Conditional Validation Is Not Displayed Correctly

1. **Clear cache**
   ```bash
   php artisan spectrum:cache clear
   ```

2. **Enable conditional analysis**
   ```bash
   php artisan spectrum:generate --analyze-conditions
   ```

## 📚 Related Documentation

- [Basic Usage](./basic-usage.md) - FormRequest basics
- [Advanced Features](./advanced-features.md) - Complex validation
- [API Reference](./api-reference.md) - Validation analysis API