# Migration Guide

This guide explains how to migrate from other API documentation generation tools to Laravel Spectrum.

## 🎯 Migration Overview

Laravel Spectrum works without modifying existing code, allowing for gradual migration. You can introduce Laravel Spectrum while keeping existing annotations and documentation.

## 📝 Migrating from Swagger-PHP

### Current State Assessment

If you're using Swagger-PHP, you should have annotations like these:

```php
/**
 * @OA\Post(
 *     path="/api/users",
 *     summary="Create a new user",
 *     tags={"Users"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name","email","password"},
 *             @OA\Property(property="name", type="string", example="John Doe"),
 *             @OA\Property(property="email", type="string", format="email"),
 *             @OA\Property(property="password", type="string", format="password")
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="User created successfully",
 *         @OA\JsonContent(ref="#/components/schemas/User")
 *     )
 * )
 */
public function store(Request $request)
{
    // ...
}
```

### Step 1: Install Laravel Spectrum

```bash
composer require wadakatu/laravel-spectrum --dev
```

### Step 2: Initial Comparison

Compare existing Swagger output with Laravel Spectrum output:

```bash
# Backup existing Swagger documentation
cp storage/api-docs/api-docs.json storage/api-docs/swagger-backup.json

# Generate documentation with Laravel Spectrum
php artisan spectrum:generate

# Check with comparison tool
```

### Step 3: Migrate to FormRequest

Gradually migrate from annotations to FormRequest:

**Before (Swagger-PHP):**
```php
/**
 * @OA\RequestBody(
 *     required=true,
 *     @OA\JsonContent(
 *         required={"name","email","password"},
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="email", type="string", format="email"),
 *         @OA\Property(property="password", type="string", minLength=8)
 *     )
 * )
 */
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:8',
    ]);
}
```

**After (Laravel Spectrum):**
```php
// Create FormRequest
class StoreUserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
        ];
    }
}

// Update controller
public function store(StoreUserRequest $request)
{
    $validated = $request->validated();
    // Annotations can be removed
}
```

### Step 4: Migrate Responses

**Before:**
```php
/**
 * @OA\Response(
 *     response=200,
 *     @OA\JsonContent(
 *         type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="email", type="string")
 *     )
 * )
 */
public function show($id)
{
    $user = User::findOrFail($id);
    return response()->json($user);
}
```

**After:**
```php
// Create API Resource
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}

// Update controller
public function show($id)
{
    $user = User::findOrFail($id);
    return new UserResource($user);
}
```

### Step 5: Migrate Configuration

```php
// Migrate settings from config/l5-swagger.php to spectrum.php
return [
    'title' => config('l5-swagger.documentations.default.info.title'),
    'version' => config('l5-swagger.documentations.default.info.version'),
    'description' => config('l5-swagger.documentations.default.info.description'),
    
    'servers' => array_map(function ($server) {
        return [
            'url' => $server['url'],
            'description' => $server['description'] ?? '',
        ];
    }, config('l5-swagger.documentations.default.servers', [])),
];
```

### Post-Migration Cleanup

```bash
# Uninstall Swagger-PHP (optional)
composer remove darkaonline/l5-swagger

# Create annotation removal script
php artisan make:command RemoveSwaggerAnnotations
```

## 🔧 Migrating from L5-Swagger

L5-Swagger is a Laravel wrapper for Swagger-PHP, so the basic migration steps are the same.

### Additional Considerations

1. **Route Configuration Migration**
   ```php
   // Disable L5-Swagger route configuration
   // config/l5-swagger.php
   'routes' => [
       'api' => false, // Disable documentation routes
   ],
   ```

2. **View Migration**
   ```php
   // Update existing Swagger UI view for Laravel Spectrum
   // resources/views/api/documentation.blade.php
   <script>
   SwaggerUIBundle({
       url: "{{ asset('storage/app/spectrum/openapi.json') }}",
       // L5-Swagger settings can be used as-is
   });
   </script>
   ```

## 📚 Migrating from Scribe

### Key Differences

Scribe is partially annotation-free, but not completely:

```php
// Scribe annotation example
/**
 * @group User Management
 * @authenticated
 * @response {
 *   "id": 1,
 *   "name": "John Doe"
 * }
 */
```

### Migration Steps

1. **Group/Tag Migration**
   ```php
   // config/spectrum.php
   'tags' => [
       'api/users/*' => 'User Management',
       'api/posts/*' => 'Content Management',
   ],
   ```

2. **Authentication Configuration Migration**
   ```php
   // Replace Scribe's @authenticated with automatic detection
   Route::middleware('auth:sanctum')->group(function () {
       // These routes are automatically detected as requiring authentication
   });
   ```

3. **Example Data Migration**
   ```php
   // Migrate Scribe examples to Factories or Seeders
   class UserFactory extends Factory
   {
       public function definition()
       {
           return [
               'name' => $this->faker->name(),
               'email' => $this->faker->unique()->safeEmail(),
           ];
       }
   }
   ```

## 🔄 Migrating from API Blueprint

### Converting from Blueprint Format

If using API Blueprint (`.apib` files):

```apib
# Group Users
## User Collection [/users]
### List Users [GET]
+ Response 200 (application/json)
    + Attributes (array[User])
```

### Migration Approach

1. **Verify Route Structure**
   ```bash
   # Match with Laravel routes
   php artisan route:list --path=api
   ```

2. **Migrate Data Structures**
    - Convert Blueprint Data Structures to Laravel Resources
    - Convert Attributes to FormRequests

## 🎯 Phased Migration Strategy

### Phase 1: Coexistence Period

```php
// Generate both documentations
"scripts": {
    "docs:swagger": "php artisan l5-swagger:generate",
    "docs:spectrum": "php artisan spectrum:generate",
    "docs:all": "npm run docs:swagger && npm run docs:spectrum"
}
```

### Phase 2: Validation Period

```php
// Custom command to check differences
class CompareDocumentationCommand extends Command
{
    public function handle()
    {
        $swagger = json_decode(file_get_contents('storage/api-docs/api-docs.json'), true);
        $spectrum = json_decode(file_get_contents('storage/app/spectrum/openapi.json'), true);
        
        // Compare paths
        $swaggerPaths = array_keys($swagger['paths'] ?? []);
        $spectrumPaths = array_keys($spectrum['paths'] ?? []);
        
        $missing = array_diff($swaggerPaths, $spectrumPaths);
        $extra = array_diff($spectrumPaths, $swaggerPaths);
        
        $this->info('Missing paths: ' . implode(', ', $missing));
        $this->info('Extra paths: ' . implode(', ', $extra));
    }
}
```

### Phase 3: Switchover

```php
// Control with environment variable
if (env('USE_SPECTRUM_DOCS', false)) {
    return redirect('/api/documentation/spectrum');
} else {
    return redirect('/api/documentation/swagger');
}
```

## 💡 Migration Best Practices

### 1. Create Backups

```bash
# Backup existing documentation
git add .
git commit -m "Backup: Before Laravel Spectrum migration"
git tag pre-spectrum-migration
```

### 2. Team Communication

```markdown
## Documentation Migration Notice

- Migration Period: 2 weeks
- Impact: API documentation auto-generation method changes
- Benefits: No annotations required, reduced maintenance
- Action: Use of FormRequest recommended
```

### 3. Update CI/CD

```yaml
# .github/workflows/api-docs.yml
- name: Generate API Documentation
  run: |
    # Temporarily generate both
    php artisan l5-swagger:generate || true
    php artisan spectrum:generate
    
    # Generate comparison report
    php artisan docs:compare > docs-comparison.txt
```

### 4. Migration Checklist

- [ ] Install Laravel Spectrum
- [ ] Create configuration file
- [ ] Test with sample endpoints
- [ ] Gradual migration to FormRequests
- [ ] Create API Resources
- [ ] Verify documentation comparison
- [ ] Team review
- [ ] Deploy to production
- [ ] Remove old tools

## 🔍 Troubleshooting

### Paths Not Found After Migration

```php
// Check details in debug mode
php artisan spectrum:generate -vvv

// Generate with specific pattern
php artisan spectrum:generate --pattern="api/users/*"
```

### Schema Mismatches

```php
// Add custom mapping
Spectrum::addSchemaMapping(function ($path, $method) {
    if ($path === '/api/legacy/endpoint') {
        return [
            'deprecated' => true,
            'x-legacy-schema' => true,
        ];
    }
});
```

## 📚 Related Documentation

- [Installation and Configuration](./installation.md) - Initial setup
- [Comparison with Other Tools](./comparison.md) - Feature comparison table
- [FAQ](./faq.md) - Frequently asked questions