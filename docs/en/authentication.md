# Authentication Configuration Guide

Laravel Spectrum automatically detects various authentication methods and properly documents them. It supports common authentication patterns including JWT, Laravel Sanctum, OAuth2, and API Key authentication.

## 🔐 Supported Authentication Methods

### 1. Bearer Token Authentication (JWT/Sanctum)

The most common authentication method.

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'profile']);
    Route::apiResource('posts', PostController::class);
});

// or
Route::middleware('auth:api')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

**Configuration (config/spectrum.php):**

```php
'authentication' => [
    'default' => 'bearer',
    'flows' => [
        'bearer' => [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
            'description' => 'Enter the token with the `Bearer ` prefix',
        ],
    ],
    'middleware_map' => [
        'auth:sanctum' => 'bearer',
        'auth:api' => 'bearer',
        'auth' => 'bearer',
    ],
],
```

### 2. API Key Authentication

Sending API keys via headers or query parameters.

```php
// Custom middleware
class ApiKeyAuthentication
{
    public function handle($request, Closure $next)
    {
        $apiKey = $request->header('X-API-Key') ?? $request->query('api_key');
        
        if (!$apiKey || !$this->isValidApiKey($apiKey)) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }
        
        return $next($request);
    }
}

// Usage example
Route::middleware('api-key')->group(function () {
    Route::get('/data', [DataController::class, 'index']);
});
```

**Configuration:**

```php
'authentication' => [
    'flows' => [
        'apiKey' => [
            'type' => 'apiKey',
            'in' => 'header', // or 'query'
            'name' => 'X-API-Key',
            'description' => 'API key for authentication',
        ],
    ],
    'middleware_map' => [
        'api-key' => 'apiKey',
    ],
],
```

### 3. Basic Authentication

HTTP Basic authentication.

```php
Route::middleware('auth.basic')->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});
```

**Configuration:**

```php
'authentication' => [
    'flows' => [
        'basic' => [
            'type' => 'http',
            'scheme' => 'basic',
            'description' => 'Basic HTTP authentication',
        ],
    ],
    'middleware_map' => [
        'auth.basic' => 'basic',
    ],
],
```

### 4. OAuth2 Authentication

OAuth2 implementations like Laravel Passport.

```php
Route::middleware('auth:passport')->group(function () {
    Route::get('/oauth/user', [OAuthController::class, 'user']);
});
```

**Configuration:**

```php
'authentication' => [
    'flows' => [
        'oauth2' => [
            'type' => 'oauth2',
            'flows' => [
                'authorizationCode' => [
                    'authorizationUrl' => '/oauth/authorize',
                    'tokenUrl' => '/oauth/token',
                    'refreshUrl' => '/oauth/token/refresh',
                    'scopes' => [
                        'read' => 'Read access',
                        'write' => 'Write access',
                        'admin' => 'Admin access',
                    ],
                ],
                'password' => [
                    'tokenUrl' => '/oauth/token',
                    'scopes' => [
                        'read' => 'Read access',
                        'write' => 'Write access',
                    ],
                ],
            ],
        ],
    ],
    'middleware_map' => [
        'auth:passport' => 'oauth2',
    ],
],
```

## 🎯 Implementation Patterns

### Laravel Sanctum

#### SPA Authentication (Cookie)

```php
// routes/web.php
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth:sanctum');

// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
```

#### API Token Authentication

```php
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }
}
```

### JWT Authentication (tymon/jwt-auth)

```php
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => new UserResource(auth()->user()),
        ]);
    }

    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }
}
```

### Custom Authentication Middleware

```php
namespace App\Http\Middleware;

use Closure;
use App\Models\ApiClient;

class CustomApiAuthentication
{
    public function handle($request, Closure $next, ...$scopes)
    {
        // Get authentication credentials from headers
        $apiKey = $request->header('X-API-Key');
        $apiSecret = $request->header('X-API-Secret');

        if (!$apiKey || !$apiSecret) {
            return response()->json([
                'error' => 'Missing authentication credentials'
            ], 401);
        }

        // Verify client
        $client = ApiClient::where('key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (!$client || !hash_equals($client->secret, $apiSecret)) {
            return response()->json([
                'error' => 'Invalid credentials'
            ], 401);
        }

        // Check scopes
        if (!empty($scopes) && !$client->hasScopes($scopes)) {
            return response()->json([
                'error' => 'Insufficient permissions'
            ], 403);
        }

        // Request rate limiting
        if (!$this->checkRateLimit($client)) {
            return response()->json([
                'error' => 'Rate limit exceeded'
            ], 429);
        }

        $request->merge(['api_client' => $client]);

        return $next($request);
    }

    private function checkRateLimit($client)
    {
        // Rate limiting logic
        return true;
    }
}
```

## 🛡️ Security Headers

### CORS Configuration

```php
// config/cors.php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

### Security Middleware

```php
class SecurityHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        return $response;
    }
}
```

## 📝 Documentation Generation Configuration

### Global Authentication Configuration

```php
// config/spectrum.php
'authentication' => [
    // Default authentication method
    'default' => 'bearer',
    
    // Routes that don't require authentication
    'exclude_patterns' => [
        'api/health',
        'api/status',
        'api/auth/login',
        'api/auth/register',
    ],
    
    // Global security requirements
    'global_security' => true,
    
    // Custom security schemes
    'custom_schemes' => [
        'twoFactor' => [
            'type' => 'apiKey',
            'in' => 'header',
            'name' => 'X-2FA-Code',
            'description' => 'Two-factor authentication code',
        ],
    ],
],
```

### Route-specific Authentication Configuration

```php
// Using multiple authentication methods
Route::middleware(['auth:sanctum', 'verified', '2fa'])->group(function () {
    Route::post('/sensitive-action', [SecureController::class, 'action']);
});

// Authentication options (any of)
Route::middleware('auth:sanctum,api')->group(function () {
    Route::get('/flexible-endpoint', [FlexibleController::class, 'index']);
});
```

## 💡 Best Practices

### 1. Environment-specific Configuration

```php
// .env
API_AUTH_DRIVER=sanctum
API_RATE_LIMIT=60
API_TOKEN_LIFETIME=60

// config/auth.php
'guards' => [
    'api' => [
        'driver' => env('API_AUTH_DRIVER', 'sanctum'),
        'provider' => 'users',
    ],
],
```

### 2. Proper Token Management

```php
class TokenService
{
    public function generateToken(User $user, string $name = 'api-token'): array
    {
        // Delete existing tokens (optional)
        $user->tokens()->where('name', $name)->delete();
        
        $token = $user->createToken($name, ['*']);
        
        return [
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at,
            'abilities' => $token->accessToken->abilities,
        ];
    }
    
    public function revokeToken(User $user, ?string $tokenId = null): void
    {
        if ($tokenId) {
            $user->tokens()->where('id', $tokenId)->delete();
        } else {
            $user->tokens()->delete();
        }
    }
}
```

### 3. Rate Limiting

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/user', [UserController::class, 'show']);
});

// RouteServiceProvider.php
protected function configureRateLimiting()
{
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by(
            $request->user()?->id ?: $request->ip()
        );
    });
    
    RateLimiter::for('auth', function (Request $request) {
        return Limit::perMinute(5)->by($request->ip());
    });
}
```

### 4. Authentication Error Handling

```php
// app/Exceptions/Handler.php
public function render($request, Throwable $exception)
{
    if ($exception instanceof AuthenticationException) {
        return response()->json([
            'error' => 'Unauthenticated',
            'message' => 'Authentication required',
        ], 401);
    }
    
    if ($exception instanceof AuthorizationException) {
        return response()->json([
            'error' => 'Forbidden',
            'message' => 'You do not have permission to access this resource',
        ], 403);
    }
    
    return parent::render($request, $exception);
}
```

## 🔍 Troubleshooting

### Authentication Not Detected

1. **Check middleware mapping**
   ```php
   // config/spectrum.php
   'middleware_map' => [
       'your-custom-auth' => 'bearer',
   ],
   ```

2. **Check route middleware**
   ```bash
   php artisan route:list --path=api
   ```

3. **Clear cache**
   ```bash
   php artisan config:clear
   php artisan route:clear
   ```

### Multiple Authentication Methods

```php
// config/spectrum.php
'authentication' => [
    'flows' => [
        'bearer' => [...],
        'apiKey' => [...],
    ],
    'middleware_map' => [
        'auth:sanctum' => 'bearer',
        'api-key' => 'apiKey',
    ],
    // Use different authentication per route
    'route_overrides' => [
        'api/external/*' => 'apiKey',
        'api/internal/*' => 'bearer',
    ],
],
```

## 📚 Related Documentation

- [Security](./security.md) - Security best practices
- [Middleware](./middleware.md) - Creating custom middleware
- [Configuration Reference](./config-reference.md) - Detailed authentication configuration