# Frequently Asked Questions (FAQ)

A collection of frequently asked questions and answers about Laravel Spectrum.

## 🚀 Basic Questions

### Q: What is Laravel Spectrum?

**A:** Laravel Spectrum is a zero-annotation API documentation generation tool for Laravel. It analyzes existing code and automatically generates OpenAPI 3.0 specification documentation. You can generate documentation directly from code without writing annotations or comments.

### Q: Which versions of Laravel are supported?

**A:** The following versions are supported:
- Laravel 10.x, 11.x, 12.x
- PHP 8.1 or higher

### Q: Can I integrate it into an existing project?

**A:** Yes, you can easily integrate it into existing projects. No code changes are required, just install with Composer and run the command:

```bash
composer require wadakatu/laravel-spectrum --dev
php artisan spectrum:generate
```

### Q: What are the differences from Swagger-PHP or Scribe?

**A:** The main differences are:

| Feature | Laravel Spectrum | Swagger-PHP | Scribe |
|---------|-----------------|-------------|---------|
| Annotations | Not required | Required | Partially required |
| Setup time | Less than 1 minute | Several hours | Tens of minutes |
| Maintenance | Automatic | Manual | Manual |
| Real-time updates | Yes | No | No |
| Mock server | Yes | No | No |

See [Comparison with Other Tools](./comparison.md) for details.

## 📝 Documentation Generation

### Q: What information is automatically detected?

**A:** The following information is automatically detected:

- **Route information**: HTTP methods, paths, middleware
- **Requests**: FormRequest validation, inline validation, file uploads
- **Responses**: API resources, Fractal transformers, pagination
- **Authentication**: Bearer Token, API Key, Basic authentication, OAuth2
- **Others**: Enum constraints, query parameters, conditional validation

### Q: Does it work even if I'm not using FormRequest?

**A:** Yes, it works. The following patterns are also detected:

```php
// Inline validation
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string',
        'email' => 'required|email',
    ]);
}

// Validator facade
$validator = Validator::make($request->all(), [
    'title' => 'required|max:255',
]);
```

However, using FormRequest is recommended.

### Q: Does it support custom response formats?

**A:** Yes, it does. It detects various patterns including API resources, Fractal transformers, and custom response classes:

```php
// API Resource
return new UserResource($user);

// Collection
return UserResource::collection($users);

// Custom response
return response()->json([
    'data' => $users,
    'meta' => ['total' => $count],
]);
```

### Q: Are conditional validations detected?

**A:** Yes, they are detected. HTTP method-based and dynamic conditions are supported:

```php
public function rules()
{
    $rules = ['name' => 'required'];
    
    if ($this->isMethod('POST')) {
        $rules['password'] = 'required|min:8';
    }
    
    return $rules;
}
```

## ⚡ Performance

### Q: Can it be used for large projects (1000+ routes)?

**A:** Yes, you can process them quickly using the optimization command:

```bash
php artisan spectrum:generate:optimized --workers=8
```

See the [Performance Optimization Guide](./performance.md) for details.

### Q: How long does generation take?

**A:** It depends on the project size, but here are some guidelines:

- Less than 100 routes: A few seconds
- 100-500 routes: 10-30 seconds
- 500-1000 routes: 30 seconds to 1 minute
- 1000+ routes: 1-2 minutes with optimization command

### Q: I'm getting memory shortage errors

**A:** You can solve this in the following ways:

1. **Temporary solution**:
   ```bash
   php -d memory_limit=1G artisan spectrum:generate
   ```

2. **Using optimization command**:
   ```bash
   php artisan spectrum:generate:optimized --chunk-size=50
   ```

3. **Excluding unnecessary routes**:
   ```php
   // config/spectrum.php
   'excluded_routes' => [
       'telescope/*',
       'horizon/*',
   ],
   ```

## 🔧 Configuration and Customization

### Q: I want to exclude specific routes

**A:** You can specify exclusion patterns in the configuration file:

```php
// config/spectrum.php
'excluded_routes' => [
    'api/internal/*',
    'api/debug/*',
    'api/health',
],
```

### Q: I want to separate by API version

**A:** You can specify versions with route patterns:

```php
// config/spectrum.php
'route_patterns' => [
    'api/v1/*',
    'api/v2/*',
],
```

After updating route patterns, regenerate documentation:

```bash
php artisan spectrum:generate --clear-cache
```

### Q: I'm using custom authentication middleware

**A:** Configure middleware mapping:

```php
// config/spectrum.php
'authentication' => [
    'middleware_map' => [
        'custom-auth' => 'bearer',
        'api-key-auth' => 'apiKey',
    ],
],
```

### Q: I want to customize example data

**A:** You can configure custom generators:

```php
// config/spectrum.php
'example_generation' => [
    'custom_generators' => [
        'user_id' => fn() => rand(1000, 9999),
        'email' => fn($faker) => $faker->companyEmail(),
        'status' => fn() => 'active',
    ],
],
```

## 🎭 Mock Server

### Q: What is a mock server?

**A:** It's a feature that automatically starts a mock API server from the generated OpenAPI documentation. You can develop frontend and test without an actual backend:

```bash
php artisan spectrum:mock
```

### Q: What authentication does the mock server support?

**A:** It simulates the following authentication methods:

- Bearer Token (JWT format)
- API Key (header or query)
- Basic authentication
- OAuth2 (partial)

### Q: Can I customize mock server responses?

**A:** You can switch with scenario parameters:

```bash
# Success response
curl http://localhost:8081/api/users?_scenario=success

# Error response
curl http://localhost:8081/api/users?_scenario=error

# Empty response
curl http://localhost:8081/api/users?_scenario=empty
```

## 🚨 Troubleshooting

### Q: Routes are not detected

**A:** Please check the following:

1. **Check route patterns**:
   ```php
   // config/spectrum.php
   'route_patterns' => ['api/*'], // 'api/*' not 'api/'
   ```

2. **Clear route cache**:
   ```bash
   php artisan route:clear
   ```

3. **Check route list**:
   ```bash
   php artisan route:list --path=api
   ```

### Q: Validation is not detected

**A:** Please check the following:

1. **FormRequest's `authorize()` method**:
   ```php
   public function authorize()
   {
       return true; // Not detected if false
   }
   ```

2. **Check type hints**:
   ```php
   // ✅ Correct
   public function store(StoreUserRequest $request)
   
   // ❌ Not detected
   public function store(Request $request)
   ```

### Q: Documentation is not updated

**A:** Please clear the cache:

```bash
php artisan spectrum:cache clear
php artisan spectrum:generate --no-cache
```

```php
// bootstrap/app.php
$app->register(LaravelSpectrum\SpectrumServiceProvider::class);
```

## 🔄 CI/CD

### Q: Can I integrate it into CI/CD pipelines?

**A:** Yes, you can easily integrate it:

```yaml
# GitHub Actions
- name: Generate API Docs
  run: |
    composer install
    php artisan spectrum:generate
    
- name: Upload Docs
  uses: actions/upload-artifact@v3
  with:
    name: api-docs
    path: storage/app/spectrum/
```

### Q: I want to automatically publish documentation

**A:** You can publish to GitHub Pages or other static hosting services:

```yaml
- name: Deploy to GitHub Pages
  uses: peaceiris/actions-gh-pages@v3
  with:
    github_token: ${{ secrets.GITHUB_TOKEN }}
    publish_dir: ./storage/app/spectrum
```

## 📦 Export

### Q: Can I use it with Postman or Insomnia?

**A:** Yes, there are dedicated export commands:

```bash
# Postman collection
php artisan spectrum:export:postman

# Insomnia workspace
php artisan spectrum:export:insomnia
```

### Q: Can I include test scripts in exports?

**A:** Yes, you can automatically generate test scripts in Postman exports:

```bash
php artisan spectrum:export:postman --include-tests
```

## 🤝 Contributing

### Q: I found a bug

**A:** Please report it on [GitHub Issues](https://github.com/wadakatu/laravel-spectrum/issues). Include the following information:

- Laravel version
- PHP version
- Laravel Spectrum version
- Error message (if any)
- Steps to reproduce

### Q: I have a feature request

**A:** Please propose it on [GitHub Issues](https://github.com/wadakatu/laravel-spectrum/issues). Pull requests are also welcome!

## 📚 Other

### Q: What is the license?

**A:** MIT License. Commercial use is allowed.

### Q: Is there support?

**A:** You can get support in the following ways:

1. Check the [Documentation](index.md)
2. Ask questions on [GitHub Issues](https://github.com/wadakatu/laravel-spectrum/issues)
3. Discuss on [GitHub Discussions](https://github.com/wadakatu/laravel-spectrum/discussions)

### Q: Are there versions in other languages?

**A:** Currently, documentation is available in Japanese and English. Contributions for translations to other languages are welcome!

---

**If your question is not resolved, please feel free to contact us on [GitHub Issues](https://github.com/wadakatu/laravel-spectrum/issues).**
