# Known Issues

This document lists known issues and workarounds for Laravel Spectrum. These issues are planned to be fixed in future versions.

## 🚨 Critical Issues

### 1. Anonymous FormRequest Class Analysis

**Issue:**
Validation rules in FormRequests defined as anonymous classes may not be detected correctly.

```php
// Pattern that may not be detected
Route::post('/users', function (Request $request) {
    $validated = $request->validate(
        (new class extends FormRequest {
            public function rules() {
                return ['name' => 'required'];
            }
        })->rules()
    );
});
```

**Workaround:**
Define FormRequest as a regular class.

```php
// Recommended approach
class StoreUserRequest extends FormRequest
{
    public function rules()
    {
        return ['name' => 'required'];
    }
}

Route::post('/users', [UserController::class, 'store']);
```

**Status:** Planned fix in v2.0

### 2. Deeply Nested Array Validation

**Issue:**
Nested array validation deeper than 3 levels is not fully parsed.

```php
// Levels beyond the 3rd may only be partially detected
public function rules()
{
    return [
        'data' => 'required|array',
        'data.*.items' => 'required|array',
        'data.*.items.*.details' => 'required|array',
        'data.*.items.*.details.*.value' => 'required|string', // May not be detected
    ];
}
```

**Workaround:**
Increase the parsing depth in the configuration or flatten the structure.

```php
// config/spectrum.php
'analysis' => [
    'max_depth' => 5, // Default is 3
],
```

**Status:** Under investigation

## ⚠️ Limitations

### 1. Dynamic Route Registration

**Issue:**
Routes dynamically registered at runtime are not detected.

```php
// Patterns not detected
if (config('features.new_api')) {
    Route::post('/new-endpoint', [NewController::class, 'store']);
}

// Dynamically generating routes from database
foreach (Module::active() as $module) {
    Route::prefix($module->slug)->group($module->routes);
}
```

**Workaround:**
Use static route definitions or create a custom analyzer.

```php
// Custom analyzer example
class DynamicRouteAnalyzer implements Analyzer
{
    public function analyze($target): array
    {
        // Logic to analyze dynamic routes
    }
}
```

**Status:** Specification limitation

### 2. Custom Middleware Authentication Detection

**Issue:**
Authentication requirements for non-standard custom middleware are not automatically detected.

```php
// Pattern not detected
Route::middleware('custom.auth:special')->group(function () {
    Route::get('/protected', [Controller::class, 'index']);
});
```

**Workaround:**
Configure middleware mapping.

```php
// config/spectrum.php
'authentication' => [
    'middleware_map' => [
        'custom.auth' => 'bearer',
        'custom.auth:special' => 'apiKey',
    ],
],
```

**Status:** Documented

## 🐛 Bugs

### 1. Enum Type Array Validation

**Issue:**
Enum constraints for individual values in array validation are not detected.

```php
use App\Enums\StatusEnum;

public function rules()
{
    return [
        // Array validation is detected
        'statuses' => ['required', 'array'],
        // Enum constraint for individual elements is not detected
        'statuses.*' => ['required', Rule::enum(StatusEnum::class)],
    ];
}
```

**Workaround:**
Use the `in` rule together.

```php
'statuses.*' => [
    'required',
    Rule::enum(StatusEnum::class),
    Rule::in(StatusEnum::cases()), // Add this as well
],
```

**Status:** Planned fix in v1.3

### 2. Fractal Include Detection

**Issue:**
Fractal's `availableIncludes` are not correctly detected when conditional.

```php
class UserTransformer extends TransformerAbstract
{
    public function __construct(private bool $isAdmin)
    {
        $this->availableIncludes = $isAdmin 
            ? ['posts', 'comments', 'privateData']
            : ['posts', 'comments'];
    }
}
```

**Workaround:**
Define as a static property.

```php
protected array $availableIncludes = ['posts', 'comments', 'privateData'];

public function includePrivateData(User $user)
{
    if (!$this->isAdmin) {
        return null;
    }
    return $this->item($user->privateData, new PrivateDataTransformer);
}
```

**Status:** Being fixed

## 💻 Environment-Specific Issues

### 1. File Watching on Windows

**Issue:**
The `spectrum:watch` command doesn't detect file changes on Windows.

**Workaround:**
Use polling mode.

```bash
php artisan spectrum:watch --poll
```

Or enable in configuration:

```php
// config/spectrum.php
'watch' => [
    'polling' => [
        'enabled' => true,
        'interval' => 1000,
    ],
],
```

**Status:** Platform limitation

### 2. Performance in Docker Containers

**Issue:**
Generation is slow when using volume mounts with Docker for Mac/Windows.

**Workaround:**
1. Use caching aggressively
2. Run generation inside the container
3. Copy only results to host

```dockerfile
# Dockerfile
RUN php artisan spectrum:generate && \
    cp -r storage/app/spectrum /tmp/spectrum

# docker-compose.yml
volumes:
  - ./storage/app/spectrum:/tmp/spectrum
```

**Status:** Docker limitation

## 🔧 Performance Issues

### 1. Memory Usage in Large Projects

**Issue:**
Memory exhaustion may occur in projects with over 1000 routes.

**Workaround:**
Use the optimized command:

```bash
php artisan spectrum:generate:optimized --chunk-size=50
```

**Status:** Continuously improving

### 2. Circular Reference Detection

**Issue:**
Circular references between resources may cause stack overflow.

```php
// Circular reference example
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'posts' => PostResource::collection($this->posts),
        ];
    }
}

class PostResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'author' => new UserResource($this->author), // Circular reference
        ];
    }
}
```

**Workaround:**
Set maximum depth or use conditional loading:

```php
'author' => $this->when(!$request->has('no_author'), function () {
    return new UserResource($this->author);
}),
```

**Status:** Improvement planned for v1.4

## 📝 Minor Issues

### 1. Custom Date Formats

**Issue:**
Custom date formats are not recognized as `date-time` format.

```php
'created_at' => $this->created_at->format('Y年m月d日'),
```

**Workaround:**
Use ISO 8601 format or treat custom formats as strings.

### 2. Japanese Field Names

**Issue:**
Japanese field names may not be properly escaped in OpenAPI.

**Workaround:**
Use English field names and include Japanese in `description`:

```php
public function attributes()
{
    return [
        'name' => '名前',
        'email' => 'メールアドレス',
    ];
}
```

## 🔄 Update Information

### Recently Fixed Issues

- **v1.2.0**: Improved pagination metadata detection
- **v1.1.5**: Fixed service provider registration issue in Lumen
- **v1.1.4**: Fixed deprecation warnings in PHP 8.2

### Planned Fixes

- **v1.3.0**: Full support for enum array validation
- **v1.4.0**: Automatic circular reference detection and handling
- **v2.0.0**: Full support for anonymous classes

## 📞 Reporting Issues

When reporting a new issue, please include the following information:

1. **Environment Information**
   ```bash
   php -v
   php artisan --version
   composer show wadakatu/laravel-spectrum
   ```

2. **Reproduction Steps**
    - Minimal reproduction code
    - Expected behavior
    - Actual behavior

3. **Error Logs**
   ```bash
   tail -n 100 storage/logs/laravel.log
   ```

**Report to:** [GitHub Issues](https://github.com/wadakatu/laravel-spectrum/issues)

## 📚 Related Documentation

- [Troubleshooting](./troubleshooting.md) - Solving common problems
- [FAQ](./faq.md) - Frequently asked questions