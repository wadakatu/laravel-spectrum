# Advanced Features

Laravel Spectrum provides powerful features for complex API documentation scenarios.

## Intelligent Tag Organization

Spectrum automatically organizes your endpoints with smart tag generation:

```php
// Routes are automatically tagged based on URL structure
Route::apiResource('products', ProductController::class);
// → Tag: "Products"

Route::post('api/v1/orders/{order}/payments', [PaymentController::class, 'store']);
// → Tags: ["Orders", "Payments"]

// Override with custom tags in config
'tags' => [
    'api/v1/auth/*' => 'Authentication',
    'api/v1/billing/*' => 'Billing & Payments',
],
```

## Response Example Generation

Spectrum automatically generates realistic examples from your code:

```php
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'verified' => $this->email_verified_at !== null,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}

// Spectrum generates example like:
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "admin",
    "verified": true,
    "created_at": "2024-01-15 10:30:00"
}
```

### Realistic Examples with Faker Integration

Laravel Spectrum now integrates with Faker to generate more realistic example data:

```php
// With Faker enabled (default), you get realistic data:
{
    "id": 5432,
    "name": "Sarah Johnson",
    "email": "sarah.johnson@gmail.com",
    "phone": "+1-555-234-5678",
    "bio": "Passionate software developer with expertise in Laravel and Vue.js. Love building scalable applications.",
    "avatar": "https://via.placeholder.com/200x200.png/00aa55?text=avatar",
    "created_at": "2024-03-15T14:22:10Z"
}
```

### Custom Example Mapping

Define custom example generators for your API Resources:

```php
use LaravelSpectrum\Contracts\HasCustomExamples;

class ProductResource extends JsonResource implements HasCustomExamples
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'currency' => $this->currency,
            'status' => $this->status,
            'tags' => $this->tags,
        ];
    }

    public static function getExampleMapping(): array
    {
        return [
            'name' => fn($faker) => $faker->words(3, true) . ' ' . $faker->randomElement(['Pro', 'Plus', 'Max']),
            'price' => fn($faker) => $faker->randomFloat(2, 99.99, 999.99),
            'currency' => fn($faker) => $faker->randomElement(['USD', 'EUR', 'JPY']),
            'status' => fn($faker) => $faker->randomElement(['in_stock', 'out_of_stock', 'discontinued']),
            'tags' => fn($faker) => $faker->words(3),
        ];
    }
}

// Generates examples like:
{
    "id": 123,
    "name": "wireless bluetooth headphones Pro",
    "price": 249.99,
    "currency": "USD",
    "status": "in_stock",
    "tags": ["electronics", "audio", "premium"]
}
```

### Configuration Options

Configure Faker behavior in `config/spectrum.php`:

```php
'example_generation' => [
    // Enable/disable Faker (useful for consistent testing)
    'use_faker' => env('SPECTRUM_USE_FAKER', true),
    
    // Set locale for region-specific data
    'faker_locale' => env('SPECTRUM_FAKER_LOCALE', 'en_US'),
    // Examples: 'ja_JP' for Japanese, 'de_DE' for German
    
    // Set seed for consistent examples
    'faker_seed' => env('SPECTRUM_FAKER_SEED', null),
    
    // Global custom generators
    'custom_generators' => [
        'status' => fn($faker) => $faker->randomElement(['active', 'inactive', 'pending']),
        'role' => fn($faker) => $faker->randomElement(['admin', 'user', 'guest']),
    ],
],
```

### Smart Field Detection

Spectrum automatically detects field types and generates appropriate data:

- **Email fields**: `email`, `email_address` → realistic email addresses
- **Names**: `first_name`, `last_name`, `full_name` → appropriate name formats
- **Timestamps**: `created_at`, `updated_at` → proper date-time values
- **Prices**: `price`, `amount`, `total` → monetary values
- **Images**: `avatar`, `thumbnail`, `banner` → placeholder URLs with correct dimensions
- **Geographic**: `latitude`, `longitude`, `address`, `city` → real coordinates and locations
- **Identifiers**: `uuid`, `token`, `api_key` → properly formatted identifiers
- **Boolean fields**: `is_active`, `has_permission` → boolean values
- **Counts**: `views_count`, `likes_count` → realistic numbers

### Locale-Specific Examples

Generate region-appropriate data:

```php
// Japanese locale configuration
'faker_locale' => 'ja_JP',

// Generates:
{
    "name": "田中 太郎",
    "phone": "090-1234-5678",
    "address": "東京都渋谷区神南1-2-3",
    "company": "株式会社サンプル"
}
```

## Custom Type Mappings

Define custom type mappings for special data types:

```php
// config/spectrum.php
'type_mappings' => [
    'json' => ['type' => 'object'],
    'uuid' => ['type' => 'string', 'format' => 'uuid'],
    'decimal' => ['type' => 'number', 'format' => 'float'],
    'money' => ['type' => 'number', 'format' => 'float', 'example' => 99.99],
    'coordinate' => [
        'type' => 'object',
        'properties' => [
            'lat' => ['type' => 'number'],
            'lng' => ['type' => 'number'],
        ],
    ],
],
```

## Conditional Schema Generation

Spectrum intelligently handles conditional validations:

```php
public function rules()
{
    return [
        'type' => 'required|in:individual,company',
        'first_name' => 'required_if:type,individual',
        'last_name' => 'required_if:type,individual',
        'company_name' => 'required_if:type,company',
        'tax_id' => 'required_if:type,company',
    ];
}
```

## Advanced Validation Patterns

### Array Validation with Keys

```php
'permissions' => 'required|array',
'permissions.*.resource' => 'required|string',
'permissions.*.actions' => 'required|array',
'permissions.*.actions.*' => 'in:read,write,delete',
```

### Conditional Complex Rules

```php
'payment_method' => 'required|in:card,bank,crypto',
'card' => 'required_if:payment_method,card|array',
'card.number' => 'required_with:card|string|size:16',
'card.cvv' => 'required_with:card|string|size:3',
'bank' => 'required_if:payment_method,bank|array',
'bank.account' => 'required_with:bank|string',
'bank.routing' => 'required_with:bank|string|size:9',
```

## Resource Collections with Meta Data

```php
class ProductCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_products' => $this->collection->count(),
                'categories' => $this->collection->pluck('category')->unique()->values(),
                'price_range' => [
                    'min' => $this->collection->min('price'),
                    'max' => $this->collection->max('price'),
                ],
            ],
        ];
    }
}
```

## Versioned API Documentation

Handle multiple API versions:

```php
// config/spectrum.php
'versions' => [
    'v1' => [
        'route_patterns' => ['api/v1/*'],
        'title' => 'API v1 (Stable)',
    ],
    'v2' => [
        'route_patterns' => ['api/v2/*'],
        'title' => 'API v2 (Beta)',
    ],
],
```

## Custom Error Response Schemas

Define custom error response formats:

```php
// config/spectrum.php
'error_responses' => [
    422 => [
        'description' => 'Validation Error',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string'],
                'errors' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ],
    429 => [
        'description' => 'Too Many Requests',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'example' => 'Rate limit exceeded'],
                'retry_after' => ['type' => 'integer', 'example' => 60],
            ],
        ],
    ],
],
```

## Integration with CI/CD

### GitHub Actions Example

```yaml
name: Generate API Docs
on:
  push:
    branches: [main]
    
jobs:
  generate-docs:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          
      - name: Install dependencies
        run: composer install
        
      - name: Generate API documentation
        run: php artisan spectrum:generate --output=public/api-docs.json
        
      - name: Upload documentation
        uses: actions/upload-artifact@v3
        with:
          name: api-documentation
          path: public/api-docs.json
```

### Pre-commit Hook

```bash
#!/bin/sh
# .git/hooks/pre-commit

# Generate updated API documentation
php artisan spectrum:generate

# Add the generated file to the commit
git add storage/app/spectrum/openapi.json
```

## Performance Optimization

### Selective Route Analysis

```php
// Only analyze specific controllers
'analyze_only' => [
    App\Http\Controllers\Api\V1\ProductController::class,
    App\Http\Controllers\Api\V1\OrderController::class,
],
```

### Parallel Processing

```php
// Enable parallel processing for large codebases
'parallel_processing' => [
    'enabled' => true,
    'chunks' => 4,
],
```

### Memory Optimization

```php
// Limit analysis depth
'analysis' => [
    'max_depth' => 5,
    'skip_vendor' => true,
    'skip_traits' => ['SoftDeletes', 'Notifiable'],
],
```