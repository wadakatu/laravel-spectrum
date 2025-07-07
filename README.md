# Laravel Spectrum

<p align="center">
  <img src="assets/banner.svg" alt="Laravel Spectrum Banner" width="100%">
</p>

<p align="center">
  <img src="https://img.shields.io/github/v/release/wadakatu/laravel-spectrum?style=for-the-badge&label=LATEST%20VERSION&color=00FF00" alt="Latest Version">
</p>

[![Tests](https://github.com/wadakatu/laravel-spectrum/workflows/Tests/badge.svg)](https://github.com/wadakatu/laravel-spectrum/actions)
[![Code Coverage](https://codecov.io/gh/wadakatu/laravel-spectrum/branch/main/graph/badge.svg)](https://codecov.io/gh/wadakatu/laravel-spectrum)
[![Latest Stable Version](https://poser.pugx.org/wadakatu/laravel-spectrum/v)](https://packagist.org/packages/wadakatu/laravel-spectrum)
[![Total Downloads](https://poser.pugx.org/wadakatu/laravel-spectrum/downloads)](https://packagist.org/packages/wadakatu/laravel-spectrum)
[![License](https://poser.pugx.org/wadakatu/laravel-spectrum/license)](https://packagist.org/packages/wadakatu/laravel-spectrum)
[![PHP Version Require](https://poser.pugx.org/wadakatu/laravel-spectrum/require/php)](https://packagist.org/packages/wadakatu/laravel-spectrum)

> 🎯 **Zero-annotation API documentation generator for Laravel & Lumen**
> 
> Transform your existing Laravel/Lumen APIs into comprehensive OpenAPI documentation without writing a single annotation or modifying your code.

## ✨ Key Features

<table>
<tr>
<td width="50%">

### 🚀 **Zero Configuration**
Automatically detects and documents your API routes without any annotations or comments

### 📝 **Smart Request Analysis**
- Laravel FormRequest automatic parsing
- Lumen inline validation support
- Type inference from validation rules
- Custom messages & attributes

</td>
<td width="50%">

### 📦 **Flexible Response Handling**
- Laravel API Resources analysis
- Fractal Transformer support
- Automatic includes detection
- Multiple format compatibility

### 🛡️ **Complete Error Documentation**
- 422 validation errors auto-generation
- Authentication errors (401/403) detection
- Custom error response mapping

</td>
</tr>
<tr>
<td width="50%">

### 🔐 **Security & Authentication**
- Bearer Token auto-detection
- API Key authentication support
- Sanctum/Passport compatibility
- Security scheme generation

</td>
<td width="50%">

### 🔥 **Developer Experience**
- **Real-time preview** with hot-reload
- File change auto-detection
- WebSocket live updates
- Intelligent caching system

</td>
</tr>
</table>

## 📸 Demo

```bash
# Generate your API documentation instantly
php artisan spectrum:generate

# Watch mode for real-time updates
php artisan spectrum:watch
```

![Laravel Spectrum Demo](https://user-images.githubusercontent.com/your-demo.gif)

## 🔧 Requirements

- **PHP** 8.1 or higher
- **Laravel** 10.x, 11.x, or 12.x / **Lumen** 10.x, 11.x, 12.x
- **Composer** 2.0 or higher

## 📦 Installation

```bash
composer require wadakatu/laravel-spectrum
```

That's it! No configuration needed to get started.

## 🚀 Quick Start

### 1. Generate Documentation

```bash
# Generate OpenAPI documentation
php artisan spectrum:generate

# Generate in YAML format
php artisan spectrum:generate --format=yaml

# Custom output path
php artisan spectrum:generate --output=public/api-docs.json
```

### 2. Real-time Preview (Development)

```bash
# Start the watcher with live reload
php artisan spectrum:watch

# Visit http://localhost:8080 to see your documentation
```

### 3. View with Swagger UI

```html
<!-- Add to your blade template -->
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
<script>
SwaggerUIBundle({
    url: "/api-documentation.json",
    dom_id: '#swagger-ui',
})
</script>
```

## 📖 Usage Examples

### Laravel FormRequest Example

```php
// app/Http/Requests/StoreUserRequest.php
class StoreUserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'age' => 'nullable|integer|min:18|max:120',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
        ];
    }

    public function messages()
    {
        return [
            'email.unique' => 'This email is already registered.',
        ];
    }
}

// Controller - automatically documented!
public function store(StoreUserRequest $request)
{
    $user = User::create($request->validated());
    return new UserResource($user);
}
```

### Laravel API Resource Example

```php
// app/Http/Resources/UserResource.php
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'created_at' => $this->created_at->toDateTimeString(),
            'profile' => new ProfileResource($this->whenLoaded('profile')),
        ];
    }
}
```

### Fractal Transformer Example

```php
// app/Transformers/UserTransformer.php
class UserTransformer extends TransformerAbstract
{
    protected $availableIncludes = ['posts', 'profile', 'followers'];
    protected $defaultIncludes = ['profile'];
    
    public function transform(User $user)
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'member_since' => $user->created_at->toDateString(),
            'is_active' => (bool) $user->is_active,
        ];
    }
    
    public function includePosts(User $user)
    {
        return $this->collection($user->posts, new PostTransformer());
    }
}
```

### Lumen Inline Validation Example

```php
// Lumen Controller with inline validation
public function store(Request $request)
{
    // Automatically detected and documented!
    $this->validate($request, [
        'title' => 'required|string|max:255',
        'content' => 'required|string',
        'status' => 'required|in:draft,published',
        'tags' => 'array',
        'tags.*' => 'string|max:50',
    ]);

    $post = Post::create($request->all());
    
    return $this->fractal->item($post, new PostTransformer());
}
```

### Authentication Configuration

```php
// Automatically detects authentication methods
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class);
});

// API Key authentication
Route::middleware('auth.apikey')->group(function () {
    Route::get('/stats', StatsController::class);
});
```

## ⚙️ Configuration

Publish the configuration file for advanced customization:

```bash
php artisan vendor:publish --tag=spectrum-config
```

### Configuration Options

```php
// config/spectrum.php
return [
    // API Information
    'title' => env('APP_NAME', 'Laravel API'),
    'version' => '1.0.0',
    'description' => 'API Documentation',
    
    // Server Configuration
    'servers' => [
        [
            'url' => env('APP_URL'),
            'description' => 'Production server',
        ],
    ],
    
    // Route Detection
    'route_patterns' => [
        'api/*',        // Include all /api routes
        'api/v1/*',     // Version-specific routes
        'api/v2/*',
    ],
    
    'excluded_routes' => [
        'api/health',   // Exclude health checks
        'api/debug/*',  // Exclude debug endpoints
    ],
    
    // Response Formats
    'response_formats' => [
        'resource' => true,     // Laravel Resources
        'fractal' => true,      // Fractal Transformers
        'json' => true,         // Plain JSON responses
    ],
    
    // Security Schemes
    'security_schemes' => [
        'bearer' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ],
        'apiKey' => [
            'type' => 'apiKey',
            'in' => 'header',
            'name' => 'X-API-Key',
        ],
    ],
    
    // Cache Configuration
    'cache' => [
        'enabled' => env('SPECTRUM_CACHE_ENABLED', true),
        'ttl' => 3600, // 1 hour
    ],
    
    // Watch Mode Settings
    'watch' => [
        'port' => 8080,
        'host' => '127.0.0.1',
        'open_browser' => true,
    ],
];
```

## 🎯 Advanced Features

### Custom Type Mappings

```php
// config/spectrum.php
'type_mappings' => [
    'json' => ['type' => 'object'],
    'uuid' => ['type' => 'string', 'format' => 'uuid'],
    'decimal' => ['type' => 'number', 'format' => 'float'],
],
```

### Response Examples

```php
// Automatically generates examples from your Resources
class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'currency' => 'USD',
            'in_stock' => $this->quantity > 0,
            'meta' => [
                'sku' => $this->sku,
                'weight' => $this->weight,
            ],
        ];
    }
}
```

### Error Response Customization

```php
// Automatic 422 validation error format
{
    "message": "The given data was invalid.",
    "errors": {
        "email": [
            "The email field is required.",
            "The email must be a valid email address."
        ]
    }
}

// Custom error responses
class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'error' => 'Resource not found',
                'code' => 'RESOURCE_NOT_FOUND'
            ], 404);
        }
        
        return parent::render($request, $exception);
    }
}
```

## 🔧 Troubleshooting

### Common Issues

**Q: Documentation is not generating for some routes**
```bash
# Check registered routes
php artisan route:list --path=api

# Clear cache
php artisan spectrum:clear-cache
```

**Q: FormRequest validation rules not detected**
```bash
# Ensure FormRequest is properly type-hinted
public function store(StoreUserRequest $request) // ✅ Correct
public function store(Request $request) // ❌ Won't detect custom rules
```

**Q: Fractal includes not appearing**
```bash
# Define available includes in transformer
protected $availableIncludes = ['posts', 'profile']; // ✅ Will be documented
```

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

```bash
# Setup development environment
git clone https://github.com/wadakatu/laravel-spectrum.git
cd laravel-spectrum
composer install

# Run tests
composer test

# Run static analysis
composer analyze

# Fix code style
composer format:fix
```

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

<p align="center">
  Made with ❤️ by Wadakatu
  <br>
  <a href="https://github.com/wadakatu/laravel-spectrum">Star ⭐ this repo</a> if you find it helpful!
</p>