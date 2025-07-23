# Troubleshooting Guide

This guide helps you resolve common issues when using Laravel Spectrum.

## Common Issues & Solutions

### Routes not appearing in documentation

**Problem:** Some or all routes are missing from the generated documentation.

**Solutions:**

1. **Check route patterns configuration**
   ```bash
   php artisan route:list --path=api
   ```
   
   Verify your routes match the patterns in `config/spectrum.php`:
   ```php
   'route_patterns' => [
       'api/*',     // Make sure your routes match
   ],
   ```

2. **Clear cache and regenerate**
   ```bash
   php artisan spectrum:cache clear
   php artisan spectrum:generate
   ```

3. **Check excluded routes**
   ```php
   // Make sure routes aren't excluded
   'excluded_routes' => [
       'api/health',  // These won't be documented
   ],
   ```

### FormRequest validation not detected

**Problem:** Validation rules from FormRequest classes aren't showing up.

**Solution:** Ensure you're type-hinting the FormRequest properly:

```php
// ✅ Correct - Type-hint the FormRequest
public function store(StoreUserRequest $request)
{
    // Validation rules will be detected
}

// ❌ Wrong - Generic Request won't show custom rules
public function store(Request $request)
{
    // Custom validation won't be detected
}
```

### File uploads not showing multipart/form-data

**Problem:** File upload endpoints don't show the correct content type.

**Solution:** Ensure file validation rules are present:

```php
// The 'file' rule triggers multipart detection
'avatar' => 'required|file|image|max:2048',
'document' => 'required|mimes:pdf,doc,docx',

// Or use the 'image' rule
'photo' => 'required|image|mimes:jpeg,png|max:2048',
```

### Pagination not detected

**Problem:** Paginated responses aren't properly documented.

**Solution:** Use Laravel's built-in pagination methods:

```php
// ✅ These will be detected
return ProductResource::collection(Product::paginate(15));
return Product::paginate(20);
return Product::simplePaginate(10);

// ❌ Custom pagination might not be detected
return response()->json([
    'data' => $products,
    'total' => $count,
]);
```

### Enum values not showing

**Problem:** PHP enum constraints aren't appearing in the documentation.

**Solutions:**

1. **Ensure you're using PHP 8.1+**
   ```php
   // This requires PHP 8.1 or higher
   enum Status: string {
       case ACTIVE = 'active';
       case INACTIVE = 'inactive';
   }
   ```

2. **Use the Rule::enum() validation**
   ```php
   'status' => ['required', Rule::enum(Status::class)],
   ```

### Authentication not detected

**Problem:** Security requirements aren't showing for protected routes.

**Solution:** Ensure middleware is properly applied:

```php
// ✅ Will be detected
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
});

// ✅ Also detected
Route::get('/profile', [ProfileController::class, 'show'])
    ->middleware('auth:api');
```

### Watch mode not updating

**Problem:** Changes aren't triggering documentation regeneration.

**Solutions:**

1. **Check watched paths**
   ```php
   // config/spectrum.php
   'watch' => [
       'paths' => [
           app_path('Http/Controllers'),
           app_path('Http/Requests'),
           app_path('Http/Resources'),
           base_path('routes'),
       ],
   ],
   ```

2. **Increase debounce time for slower systems**
   ```php
   'watch' => [
       'debounce' => 500, // Increase from 300ms
   ],
   ```

3. **Check file permissions**
   ```bash
   chmod -R 755 app/Http
   chmod -R 755 routes
   ```

## Performance Issues

### Slow generation on large codebases

**Solutions:**

1. **Enable caching**
   ```bash
   SPECTRUM_CACHE_ENABLED=true
   ```

2. **Exclude unnecessary routes**
   ```php
   'excluded_routes' => [
       'api/debug/*',
       'api/test/*',
       'horizon/*',
       'telescope/*',
   ],
   ```

3. **Limit analysis scope**
   ```php
   'route_patterns' => [
       'api/v1/*',  // Only document v1 API
   ],
   ```

### Out of memory errors

**Solutions:**

1. **Increase PHP memory limit**
   ```ini
   ; php.ini
   memory_limit = 512M
   ```

2. **Process routes in batches**
   ```bash
   php artisan spectrum:generate --batch-size=50
   ```

## Debugging Tips

### Enable verbose output

```bash
php artisan spectrum:generate -vvv
```

### Check generated cache

```bash
php artisan spectrum:cache stats
```

### Validate OpenAPI output

```bash
# Install spectral
npm install -g @stoplight/spectral-cli

# Validate generated spec
spectral lint storage/app/spectrum/openapi.json
```

### Common Log Locations

- Laravel logs: `storage/logs/laravel.log`
- Cache files: `storage/app/spectrum/cache/`
- Generated docs: `storage/app/spectrum/openapi.json`

## Getting Help

If you're still experiencing issues:

1. Check the [GitHub Issues](https://github.com/wadakatu/laravel-spectrum/issues)
2. Enable debug mode and collect logs
3. Create a minimal reproduction example
4. Open a new issue with:
   - Laravel/Lumen version
   - PHP version
   - Error messages and stack traces
   - Minimal code example

## FAQ

**Q: Can I use this with Lumen?**
A: Yes! Enable Lumen mode in configuration:
```php
'validation' => [
    'lumen' => [
        'enabled' => true,
    ],
],
```

**Q: Does it work with custom validation rules?**
A: Yes, Spectrum attempts to parse custom validation rules, though complex rules may require manual configuration.

**Q: Can I document multiple API versions?**
A: Yes, use different route patterns for each version and generate separate documentation files.

**Q: Is it compatible with API versioning packages?**
A: Yes, as long as routes are registered in Laravel's router, they'll be detected.