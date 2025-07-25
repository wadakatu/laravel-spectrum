# Security Guide

This guide explains security best practices for Laravel Spectrum and APIs.

## 🔐 Authentication and Authorization

### Implementing Authentication

```php
// config/auth.php
'guards' => [
    'api' => [
        'driver' => 'sanctum',
        'provider' => 'users',
    ],
    'api-jwt' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],

// Protecting routes
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class);
});
```

### Two-Factor Authentication (2FA)

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Enable2FARequest;
use App\Http\Requests\Verify2FARequest;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthController extends Controller
{
    protected $google2fa;
    
    public function __construct(Google2FA $google2fa)
    {
        $this->google2fa = $google2fa;
    }
    
    public function enable(Enable2FARequest $request)
    {
        $user = $request->user();
        
        // Generate secret key
        $secretKey = $this->google2fa->generateSecretKey();
        
        // Generate QR code URL
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secretKey
        );
        
        // Temporarily save
        $user->two_factor_secret = encrypt($secretKey);
        $user->save();
        
        return response()->json([
            'secret' => $secretKey,
            'qr_code' => $qrCodeUrl,
        ]);
    }
    
    public function verify(Verify2FARequest $request)
    {
        $user = $request->user();
        $secret = decrypt($user->two_factor_secret);
        
        $valid = $this->google2fa->verifyKey($secret, $request->code);
        
        if (!$valid) {
            return response()->json([
                'message' => 'Invalid authentication code',
            ], 422);
        }
        
        $user->two_factor_enabled = true;
        $user->save();
        
        // Generate recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();
        $user->two_factor_recovery_codes = encrypt($recoveryCodes);
        $user->save();
        
        return response()->json([
            'message' => 'Two-factor authentication has been enabled',
            'recovery_codes' => $recoveryCodes,
        ]);
    }
}
```

## 🛡️ API Security

### Rate Limiting

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'api' => [
        'throttle:60,1', // 60 requests per minute
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
];

// Custom rate limiting
Route::middleware('throttle:custom')->group(function () {
    Route::post('/api/expensive-operation', ExpensiveController::class);
});

// RateLimiter configuration
// app/Providers/RouteServiceProvider.php
protected function configureRateLimiting()
{
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });
    
    RateLimiter::for('custom', function (Request $request) {
        return Limit::perHour(10)
            ->by($request->user()?->id ?: $request->ip())
            ->response(function (Request $request, array $headers) {
                return response()->json([
                    'message' => 'Request limit reached',
                    'retry_after' => $headers['Retry-After'],
                ], 429);
            });
    });
}
```

### CORS Configuration

```php
// config/cors.php
return [
    'paths' => ['api/*'],
    
    'allowed_methods' => ['*'],
    
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
        'https://app.example.com',
    ],
    
    'allowed_origins_patterns' => [
        '#^https://.*\.example\.com$#',
    ],
    
    'allowed_headers' => ['*'],
    
    'exposed_headers' => [
        'X-Request-ID',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],
    
    'max_age' => 86400,
    
    'supports_credentials' => true,
];
```

## 🔍 Input Validation and Sanitization

### Strict Input Validation

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z\s\-]+$/', // Letters, spaces, and hyphens only
            ],
            'email' => [
                'required',
                'email:rfc,dns', // Also perform DNS validation
                'unique:users,email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'min:12', // Minimum 12 characters
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', // Complexity requirements
                'confirmed',
                'not_compromised', // Have I Been Pwned API check
            ],
            'phone' => [
                'nullable',
                'string',
                'regex:/^\+?[1-9]\d{1,14}$/', // E.164 format
            ],
            'role' => [
                'required',
                Rule::in(['user', 'admin', 'moderator']),
            ],
        ];
    }
    
    public function messages()
    {
        return [
            'password.regex' => 'Password must contain uppercase, lowercase, numbers, and special characters',
            'password.not_compromised' => 'This password has been found in a data breach',
        ];
    }
    
    protected function prepareForValidation()
    {
        // Input sanitization
        $this->merge([
            'name' => strip_tags($this->name),
            'email' => strtolower(trim($this->email)),
            'phone' => preg_replace('/[^0-9+]/', '', $this->phone),
        ]);
    }
}
```

### SQL Injection Prevention

```php
// Bad example - SQL injection vulnerability
$users = DB::select("SELECT * FROM users WHERE email = '{$request->email}'");

// Good example - Parameter binding
$users = DB::select('SELECT * FROM users WHERE email = ?', [$request->email]);

// Using Eloquent (recommended)
$users = User::where('email', $request->email)->get();

// For complex queries
$results = DB::table('orders')
    ->whereRaw('created_at > ?', [$date])
    ->whereRaw('total > ?', [$amount])
    ->get();
```

## 🚨 Security Headers

### Security Middleware

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        
        // Content Security Policy
        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.example.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: https:",
            "font-src 'self' https://fonts.gstatic.com",
            "connect-src 'self' https://api.example.com",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]));
        
        // Strict Transport Security (HTTPS only)
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        
        return $response;
    }
}
```

## 🔑 API Key Management

### API Key Implementation

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'name',
        'key',
        'secret',
        'permissions',
        'rate_limit',
        'expires_at',
        'last_used_at',
        'last_ip_address',
    ];
    
    protected $casts = [
        'permissions' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];
    
    protected $hidden = [
        'secret',
    ];
    
    public static function generate(string $name, array $permissions = []): self
    {
        $key = 'pk_' . Str::random(32);
        $secret = 'sk_' . Str::random(32);
        
        return static::create([
            'name' => $name,
            'key' => $key,
            'secret' => hash('sha256', $secret),
            'permissions' => $permissions,
            'rate_limit' => 1000, // Default: 1000 requests per hour
        ]);
    }
    
    public function validateSecret(string $secret): bool
    {
        return hash('sha256', $secret) === $this->secret;
    }
    
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []) ||
               in_array('*', $this->permissions ?? []);
    }
    
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
```

### API Key Authentication Middleware

```php
namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next, string $permission = null)
    {
        $key = $request->header('X-API-Key');
        $secret = $request->header('X-API-Secret');
        
        if (!$key || !$secret) {
            return response()->json([
                'error' => 'API key required',
            ], 401);
        }
        
        $apiKey = ApiKey::where('key', $key)->first();
        
        if (!$apiKey || !$apiKey->validateSecret($secret)) {
            return response()->json([
                'error' => 'Invalid API key',
            ], 401);
        }
        
        if ($apiKey->isExpired()) {
            return response()->json([
                'error' => 'API key has expired',
            ], 401);
        }
        
        if ($permission && !$apiKey->hasPermission($permission)) {
            return response()->json([
                'error' => 'Insufficient permissions for this operation',
                'required_permission' => $permission,
            ], 403);
        }
        
        // Update usage
        $apiKey->update([
            'last_used_at' => now(),
            'last_ip_address' => $request->ip(),
        ]);
        
        $request->merge(['api_key' => $apiKey]);
        
        return $next($request);
    }
}
```

## 📊 Audit Logging

### Activity Log Implementation

```php
namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function ($model) {
            $model->logActivity('created');
        });
        
        static::updated(function ($model) {
            $model->logActivity('updated', $model->getChanges());
        });
        
        static::deleted(function ($model) {
            $model->logActivity('deleted');
        });
    }
    
    public function logActivity(string $event, array $properties = [])
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'user_type' => Auth::user() ? get_class(Auth::user()) : null,
            'event' => $event,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->getKey(),
            'old_values' => $event === 'updated' ? $this->getOriginal() : null,
            'new_values' => $event === 'updated' ? $this->getAttributes() : null,
            'properties' => $properties,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'performed_at' => now(),
        ]);
    }
}
```

## 🔒 Data Encryption

### Sensitive Data Encryption

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SensitiveData extends Model
{
    protected $casts = [
        'data' => 'encrypted',
        'metadata' => 'encrypted:array',
    ];
    
    // Custom encryption
    public function setCustomDataAttribute($value)
    {
        $this->attributes['custom_data'] = encrypt($value, false);
    }
    
    public function getCustomDataAttribute($value)
    {
        return $value ? decrypt($value, false) : null;
    }
    
    // Hashing (searchable encryption)
    public function setSsnAttribute($value)
    {
        $this->attributes['ssn'] = bcrypt($value);
        $this->attributes['ssn_encrypted'] = encrypt($value);
    }
    
    public function verifySsn($value): bool
    {
        return password_verify($value, $this->ssn);
    }
}
```

## 💡 Security Best Practices

### 1. Principle of Least Privilege
- Grant users only the minimum necessary permissions
- Set specific permissions for API keys
- Strictly manage administrator privileges

### 2. Security Updates
```bash
# Regular dependency updates
composer update --with-all-dependencies
npm audit fix

# Security vulnerability checks
composer audit
npm audit
```

### 3. Environment Variable Management
```php
// .env.example
APP_DEBUG=false
APP_ENV=production

# Always use HTTPS in production
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict
```

## 📚 Related Documentation

- [Authentication Configuration](./authentication.md) - Authentication system details
- [Middleware](./middleware.md) - Security middleware
- [Error Handling](./error-handling.md) - Secure error handling