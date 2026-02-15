# Troubleshooting Guide

This guide explains common problems and their solutions when using Laravel Spectrum.

## 🚨 Common Issues

### Routes Not Detected

#### Symptoms
```bash
php artisan spectrum:generate
# Output: No routes found matching the configured patterns.
```

#### Causes and Solutions

1. **Check Route Patterns**
   ```php
   // config/spectrum.php
   'route_patterns' => [
       'api/*',    // Correct
       '/api/*',   // No leading slash needed
   ],
   ```

2. **Clear Route Cache**
   ```bash
   php artisan route:clear
   php artisan route:cache
   ```

3. **Verify Route Registration**
   ```bash
   php artisan route:list --path=api
   ```

4. **Namespace Issues**
   ```php
   // RouteServiceProvider.php
   protected $namespace = 'App\\Http\\Controllers';
   ```

### Validation Not Detected

#### Symptoms
FormRequest is used but parameters don't appear in documentation.

#### Solutions

1. **Check Type Hints**
   ```php
   // ❌ Wrong
   public function store(Request $request)
   
   // ✅ Correct
   public function store(StoreUserRequest $request)
   ```

2. **Verify FormRequest Structure**
   ```php
   class StoreUserRequest extends FormRequest
   {
       public function authorize()
       {
           return true; // false prevents analysis
       }
       
       public function rules()
       {
           return [
               'name' => 'required|string',
               // ...
           ];
       }
   }
   ```

3. **For Inline Validation**
   ```php
   public function store(Request $request)
   {
       // Place at the beginning of the method
       $validated = $request->validate([
           'name' => 'required|string',
       ]);
   }
   ```

### Out of Memory Errors

#### Symptoms
```
Fatal error: Allowed memory size of 134217728 bytes exhausted
```

#### Solutions

1. **Temporary Fix**
   ```bash
   php -d memory_limit=1G artisan spectrum:generate
   ```

2. **Permanent Fix**
   ```php
   // php.ini
   memory_limit = 512M
   ```

3. **Use Optimization Command**
   ```bash
   php artisan spectrum:generate:optimized --chunk-size=50
   ```

4. **Exclude Unnecessary Routes**
   ```php
   'excluded_routes' => [
       'telescope/*',
       'horizon/*',
       '_debugbar/*',
   ],
   ```

### File Uploads Not Displayed Correctly

#### Symptoms
File fields are displayed as regular strings.

#### Solutions

1. **Check Validation Rules**
   ```php
   'avatar' => 'required|file|image|max:2048',
   'document' => 'required|mimes:pdf,doc,docx',
   'images.*' => 'image|max:1024',
   ```

2. **Verify Content-Type**
   ```php
   // Add to FormRequest
   public function rules()
   {
       return [
           'file' => 'file', // At minimum, 'file' rule is required
       ];
   }
   ```

### Response Structure Not Detected

#### Symptoms
API Resources are used but response schema is empty.

#### Solutions

1. **Check Return Statement**
   ```php
   // ❌ Wrong
   return response()->json(new UserResource($user));
   
   // ✅ Correct
   return new UserResource($user);
   ```

2. **Verify Resource Class**
   ```php
   class UserResource extends JsonResource
   {
       public function toArray($request)
       {
           return [
               'id' => $this->id,
               'name' => $this->name,
               // Must return data
           ];
       }
   }
   ```

### Authentication Not Applied

#### Symptoms
Authentication is required but not shown in documentation.

#### Solutions

1. **Check Middleware**
   ```php
   Route::middleware('auth:sanctum')->group(function () {
       Route::get('/profile', [ProfileController::class, 'show']);
   });
   ```

2. **Verify Configuration**
   ```php
   // config/spectrum.php
   'authentication' => [
       'middleware_map' => [
           'auth:sanctum' => 'bearer',
           'auth:api' => 'bearer',
           'auth' => 'bearer',
       ],
   ],
   ```

## 🔍 Debugging Methods

### Enable Detailed Logging

```php
// config/spectrum.php
'debug' => [
    'enabled' => true,
    'log_channel' => 'spectrum',
    'verbose' => true,
],
```

### Debug Commands

```bash
# Generate with full debug output and error report
php artisan spectrum:generate --no-cache --error-report=storage/logs/spectrum-errors.json -vvv

# Verbose output
php artisan spectrum:generate -vvv

# Clear cache before rerun
php artisan spectrum:generate --clear-cache
```

### Check Log Files

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Spectrum-specific logs
tail -f storage/logs/spectrum.log
```

## ⚠️ Error Message Solutions

### "Class not found" Error

```bash
# Regenerate Composer autoload
composer dump-autoload

# Clear caches
php artisan cache:clear
php artisan config:clear
```

### "Cannot redeclare class" Error

```bash
# Reset opcache
php artisan opcache:clear

# Or disable opcache in development
# php.ini
opcache.enable=0
```

### Permission Errors

```bash
# Set storage directory permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R www-data:www-data storage

# For SELinux
semanage fcontext -a -t httpd_sys_rw_content_t "/path/to/storage(/.*)?"
restorecon -Rv /path/to/storage
```

## 🛠️ Environment-Specific Issues

### Docker Environment

```dockerfile
# Dockerfile
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    && docker-php-ext-install zip

# Set memory limit
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory.ini
```

### Laravel Sail

```bash
# Run inside Sail container
sail artisan spectrum:generate

# For memory issues
sail php -d memory_limit=1G artisan spectrum:generate
```

### Homestead/Vagrant

```yaml
# Homestead.yaml
sites:
    - map: api.test
      to: /home/vagrant/api/public
      php: "8.2"
      params:
          - key: memory_limit
            value: 512M
```

## 💻 IDE-Related Issues

### PhpStorm

If FormRequest is not recognized:

1. File → Invalidate Caches / Restart
2. Regenerate Laravel IDE Helper:
   ```bash
   php artisan ide-helper:generate
   php artisan ide-helper:models
   ```

### VSCode

Recommended extensions:
- PHP Intelephense
- Laravel Extension Pack
- Laravel Blade Snippets

## 🚀 Performance Issues

### Slow Generation

1. **Enable Cache**
   ```php
   'cache' => [
       'enabled' => true,
   ],
   ```

2. **Use Parallel Processing**
   ```bash
   php artisan spectrum:generate:optimized
   ```

3. **Disable Unnecessary Features**
   ```php
   'features' => [
       'example_generation' => false,
       'deep_analysis' => false,
   ],
   ```

### File Size Too Large

1. **Limit analyzed routes**
   ```php
   // config/spectrum.php
   'route_patterns' => [
       'api/public/*',
   ],
   'excluded_routes' => [
       'api/internal/*',
   ],
   ```

2. **Exclude Unnecessary Information**
   ```php
   'output' => [
       'include_examples' => false,
       'include_descriptions' => false,
   ],
   ```

## 📞 Support

### If Problems Persist

1. **Create an Issue**
    - [GitHub Issues](https://github.com/wadakatu/laravel-spectrum/issues)
    - Include full error messages
    - Provide environment info (PHP/Laravel/Spectrum versions)

2. **Collect Debug Information**
   ```bash
   php -v > debug-info.txt
   php artisan --version >> debug-info.txt
   composer show wadakatu/laravel-spectrum >> debug-info.txt
   ```

3. **Minimal Reproduction Code**
   Provide minimal code sample that reproduces the issue

### Frequently Asked Questions (FAQ)

**Q: Can it be used alongside existing Swagger annotations?**
A: Yes, but Laravel Spectrum ignores annotations. Manually merge after generation if needed.

**Q: Can it generate documentation for private APIs?**
A: Yes, documentation is generated even with authentication middleware. Implement access control separately.

## 📚 Related Documentation

- [Installation and Configuration](./installation.md) - Setup guide
- [Configuration Reference](./config-reference.md) - Detailed configuration options
- [FAQ](./faq.md) - Frequently asked questions and answers
