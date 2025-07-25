# Middleware Guide

Laravel Spectrum automatically detects middleware applied to API routes and reflects authentication requirements and other constraints in the OpenAPI documentation.

## 🎯 Middleware Detection

### Authentication Middleware

Laravel Spectrum automatically detects the following authentication middleware:

```php
// Sanctum authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/api/profile', [ProfileController::class, 'show']);
    Route::put('/api/profile', [ProfileController::class, 'update']);
});

// JWT authentication
Route::middleware('auth:api')->group(function () {
    Route::apiResource('api/posts', PostController::class);
});

// Multiple guards
Route::middleware(['auth:sanctum,api'])->group(function () {
    Route::post('/api/admin/users', [AdminController::class, 'createUser']);
});
```

Generated OpenAPI Schema:

```yaml
paths:
  /api/profile:
    get:
      security:
        - sanctum: []
      responses:
        401:
          description: Unauthenticated
```

## 🔐 Custom Middleware

### API Versioning

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckApiVersion
{
    public function handle(Request $request, Closure $next, $version = 'v1')
    {
        $requestVersion = $request->header('X-API-Version', 'v1');
        
        if ($requestVersion !== $version) {
            return response()->json([
                'error' => 'Unsupported API version',
                'supported_version' => $version,
                'requested_version' => $requestVersion
            ], 400);
        }
        
        // Add version information to response
        $response = $next($request);
        $response->header('X-API-Version', $version);
        
        return $response;
    }
}

// Usage in routes
Route::middleware('api.version:v2')->group(function () {
    Route::get('/api/v2/users', [UserV2Controller::class, 'index']);
});
```

### Rate Limiting

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomRateLimit
{
    protected $limiter;
    
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }
    
    public function handle(Request $request, Closure $next, $maxAttempts = 60, $decayMinutes = 1)
    {
        $key = $this->resolveRequestSignature($request);
        
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildResponse($key, $maxAttempts);
        }
        
        $this->limiter->hit($key, $decayMinutes * 60);
        
        $response = $next($request);
        
        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }
    
    protected function resolveRequestSignature(Request $request)
    {
        if ($user = $request->user()) {
            return 'user:' . $user->id;
        }
        
        return 'ip:' . $request->ip();
    }
    
    protected function buildResponse($key, $maxAttempts)
    {
        $retryAfter = $this->limiter->availableIn($key);
        
        return response()->json([
            'error' => 'Too many requests',
            'retry_after' => $retryAfter
        ], 429)->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
            'Retry-After' => $retryAfter,
            'X-RateLimit-Reset' => $this->availableAt($retryAfter)
        ]);
    }
    
    protected function addHeaders(Response $response, $maxAttempts, $remainingAttempts)
    {
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ]);
    }
}
```

## 🎨 Middleware Groups

### API Middleware Groups

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'api' => [
        \App\Http\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \App\Http\Middleware\ForceJsonResponse::class,
        \App\Http\Middleware\LogApiRequests::class,
    ],
    
    'api.v2' => [
        'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \App\Http\Middleware\CheckApiVersion::class . ':v2',
        \App\Http\Middleware\ValidateApiKey::class,
    ],
];
```

### Conditional Middleware

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ConditionalAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        // Skip authentication for specific endpoints
        $publicEndpoints = [
            'api/public/*',
            'api/health',
            'api/status'
        ];
        
        foreach ($publicEndpoints as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }
        
        // Authentication required for other endpoints
        if (!$request->user()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        
        return $next($request);
    }
}
```

## 🔄 Middleware Parameters

### Dynamic Parameters

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!$request->user()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        
        if (!$request->user()->hasAnyRole($roles)) {
            return response()->json([
                'error' => 'Insufficient permissions',
                'required_roles' => $roles,
                'user_roles' => $request->user()->roles->pluck('name')
            ], 403);
        }
        
        return $next($request);
    }
}

// Usage example
Route::middleware('role:admin,editor')->group(function () {
    Route::post('/api/posts', [PostController::class, 'store']);
    Route::put('/api/posts/{post}', [PostController::class, 'update']);
    Route::delete('/api/posts/{post}', [PostController::class, 'destroy']);
});
```

### Multiple Condition Middleware

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSubscription
{
    public function handle(Request $request, Closure $next, $plan = null, $feature = null)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        
        // Check plan
        if ($plan && !$user->subscribedToPlan($plan)) {
            return response()->json([
                'error' => 'Subscription required',
                'required_plan' => $plan,
                'current_plan' => $user->subscription?->plan
            ], 403);
        }
        
        // Check feature
        if ($feature && !$user->hasFeature($feature)) {
            return response()->json([
                'error' => 'Feature not available',
                'required_feature' => $feature,
                'available_features' => $user->availableFeatures()
            ], 403);
        }
        
        return $next($request);
    }
}
```

## 🚀 Middleware Priority

```php
// app/Http/Kernel.php
protected $middlewarePriority = [
    \App\Http\Middleware\ForceHttps::class,
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \App\Http\Middleware\CheckMaintenanceMode::class,
    \Illuminate\Auth\Middleware\Authenticate::class,
    \App\Http\Middleware\CheckApiVersion::class,
    \App\Http\Middleware\LogApiRequests::class,
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
    \Illuminate\Auth\Middleware\Authorize::class,
];
```

## 📊 Logging in Middleware

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogApiRequests
{
    public function handle(Request $request, Closure $next)
    {
        $requestId = Str::uuid()->toString();
        $request->headers->set('X-Request-ID', $requestId);
        
        $startTime = microtime(true);
        
        // Request log
        Log::info('API Request', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'headers' => $this->filterHeaders($request->headers->all()),
            'body' => $this->filterBody($request->all())
        ]);
        
        $response = $next($request);
        
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        // Response log
        Log::info('API Response', [
            'request_id' => $requestId,
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'response_size' => strlen($response->getContent())
        ]);
        
        $response->header('X-Request-ID', $requestId);
        $response->header('X-Response-Time', $duration . 'ms');
        
        return $response;
    }
    
    private function filterHeaders(array $headers)
    {
        // Exclude sensitive headers
        $sensitive = ['authorization', 'cookie', 'x-api-key'];
        
        return collect($headers)->except($sensitive)->toArray();
    }
    
    private function filterBody(array $body)
    {
        // Exclude sensitive data like passwords
        $sensitive = ['password', 'password_confirmation', 'credit_card'];
        
        return collect($body)->except($sensitive)->toArray();
    }
}
```

## 🔧 Testing Middleware

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TestEnvironmentOnly
{
    public function handle(Request $request, Closure $next)
    {
        if (!app()->environment('testing', 'local')) {
            return response()->json([
                'error' => 'This endpoint is only available in test environment'
            ], 403);
        }
        
        return $next($request);
    }
}

// Test routes
Route::middleware('test.only')->group(function () {
    Route::post('/api/test/reset-database', function () {
        Artisan::call('migrate:fresh');
        return response()->json(['message' => 'Database reset']);
    });
    
    Route::post('/api/test/seed-data', function () {
        Artisan::call('db:seed');
        return response()->json(['message' => 'Data seeded']);
    });
});
```

## 💡 Best Practices

### 1. Single Responsibility for Middleware
Each middleware should have only one responsibility:
- Authentication check
- Authorization check
- Rate limiting
- Logging
- Request transformation

### 2. Consistent Error Responses
```php
trait MiddlewareResponse
{
    protected function errorResponse(string $message, int $status, array $data = [])
    {
        return response()->json(array_merge([
            'error' => $message,
            'status' => $status
        ], $data), $status);
    }
}
```

### 3. Performance Considerations
- Avoid heavy processing
- Utilize caching
- Execute DB queries only when necessary

## 📚 Related Documentation

- [Authentication Configuration](./authentication.md) - Detailed authentication settings
- [Error Handling](./error-handling.md) - Managing error responses
- [Security](./security.md) - Security best practices