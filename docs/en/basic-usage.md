# Basic Usage

This guide explains the basic usage of Laravel Spectrum.

## 🎯 Basic Commands

### Generate Documentation

The most basic command. Analyzes your project's API and generates OpenAPI documentation:

```bash
php artisan spectrum:generate
```

This command performs:
- Analyzes all API routes
- Detects validation rules
- Infers response structures
- Generates documentation in OpenAPI 3.0 format

### Real-time Preview

View documentation in real-time during development:

```bash
php artisan spectrum:watch
```

- Displays documentation at `http://localhost:8080`
- Automatically detects file changes
- Auto-refreshes browser via WebSocket

### Clear Cache

Clear the analysis cache:

```bash
php artisan spectrum:cache:clear
```

### Start Mock Server

Launch a mock API server from the generated documentation:

```bash
php artisan spectrum:mock
# Mock API available at http://localhost:8081
```

## 📝 Basic Examples

### Simple API Controller

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;

class UserController extends Controller
{
    /**
     * Get user list
     */
    public function index()
    {
        $users = User::paginate(20);
        return UserResource::collection($users);
    }

    /**
     * Create new user
     */
    public function store(CreateUserRequest $request)
    {
        $user = User::create($request->validated());
        return new UserResource($user);
    }
}
```

### FormRequest Example

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'role' => 'required|in:admin,user,guest',
        ];
    }
}
```

Laravel Spectrum automatically:
- ✅ Detects each field's type
- ✅ Converts validation rules to OpenAPI schema
- ✅ Identifies required/optional fields
- ✅ Detects enum values (`in:admin,user,guest`)

## 🎨 Displaying Documentation

### Display in Blade View

```html
<!DOCTYPE html>
<html>
<head>
    <title>API Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist/swagger-ui.css">
</head>
<body>
    <div id="swagger-ui"></div>
    
    <script src="https://unpkg.com/swagger-ui-dist/swagger-ui-bundle.js"></script>
    <script>
    window.onload = function() {
        SwaggerUIBundle({
            url: "{{ asset('storage/app/spectrum/openapi.json') }}",
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [
                SwaggerUIBundle.presets.apis,
            ],
            layout: "StandaloneLayout"
        });
    };
    </script>
</body>
</html>
```

### Display with ReDoc

For a more refined documentation UI:

```html
<!DOCTYPE html>
<html>
<head>
    <title>API Documentation</title>
    <style>
        body { margin: 0; padding: 0; }
    </style>
</head>
<body>
    <redoc spec-url="{{ asset('storage/app/spectrum/openapi.json') }}"></redoc>
    <script src="https://cdn.jsdelivr.net/npm/redoc/bundles/redoc.standalone.js"></script>
</body>
</html>
```

## 🔍 Detected Elements

### 1. HTTP Methods and Paths

```php
Route::get('/api/users', [UserController::class, 'index']);
Route::post('/api/users', [UserController::class, 'store']);
Route::put('/api/users/{user}', [UserController::class, 'update']);
Route::delete('/api/users/{user}', [UserController::class, 'destroy']);
```

### 2. Request Parameters

Automatically detected from FormRequest:
- Required/optional fields
- Data types
- Validation rules
- Default values
- Enum constraints

### 3. Response Structure

Automatically detected from API Resources:
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
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

### 4. Authentication Requirements

Automatically detected from middleware:
```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/api/profile', [ProfileController::class, 'show']);
});
```

## 💡 Best Practices

### 1. Use FormRequest

FormRequest is preferred over inline validation:

```php
// Recommended
public function store(CreateUserRequest $request)
{
    // ...
}

// Not recommended (but still works)
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string',
        // ...
    ]);
}
```

### 2. Use API Resources

For consistent response structures:

```php
// Recommended
return new UserResource($user);

// Not recommended (but still works)
return response()->json(['user' => $user]);
```

### 3. Utilize Route Groups

```php
Route::prefix('api/v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('posts', PostController::class);
    });
});
```

## 🚀 Next Steps

- [Features](./features.md) - Detailed feature list
- [Advanced Features](./advanced-features.md) - Customization and techniques
- [Performance Optimization](./performance.md) - For large projects