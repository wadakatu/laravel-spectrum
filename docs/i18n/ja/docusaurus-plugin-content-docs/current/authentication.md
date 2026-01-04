---
id: authentication
title: 認証設定ガイド
sidebar_label: 認証設定ガイド
---

# 認証設定ガイド

Laravel Spectrumは、様々な認証方式を自動的に検出し、適切にドキュメント化します。JWT、Laravel Sanctum、OAuth2、API Key認証など、一般的な認証パターンをサポートしています。

## 🔐 対応する認証方式

### 1. Bearer Token認証（JWT/Sanctum）

最も一般的な認証方式です。

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'profile']);
    Route::apiResource('posts', PostController::class);
});

// または
Route::middleware('auth:api')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

**設定（config/spectrum.php）:**

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

### 2. API Key認証

ヘッダーまたはクエリパラメータでAPIキーを送信する方式。

```php
// カスタムミドルウェア
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

// 使用例
Route::middleware('api-key')->group(function () {
    Route::get('/data', [DataController::class, 'index']);
});
```

**設定:**

```php
'authentication' => [
    'flows' => [
        'apiKey' => [
            'type' => 'apiKey',
            'in' => 'header', // または 'query'
            'name' => 'X-API-Key',
            'description' => 'API key for authentication',
        ],
    ],
    'middleware_map' => [
        'api-key' => 'apiKey',
    ],
],
```

### 3. Basic認証

HTTPベーシック認証。

```php
Route::middleware('auth.basic')->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});
```

**設定:**

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

### 4. OAuth2認証

Laravel PassportなどのOAuth2実装。

```php
Route::middleware('auth:passport')->group(function () {
    Route::get('/oauth/user', [OAuthController::class, 'user']);
});
```

**設定:**

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

## 🎯 実装パターン

### Laravel Sanctum

#### SPA認証（Cookie）

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

#### APIトークン認証

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

### JWT認証（tymon/jwt-auth）

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

### カスタム認証ミドルウェア

```php
namespace App\Http\Middleware;

use Closure;
use App\Models\ApiClient;

class CustomApiAuthentication
{
    public function handle($request, Closure $next, ...$scopes)
    {
        // ヘッダーから認証情報を取得
        $apiKey = $request->header('X-API-Key');
        $apiSecret = $request->header('X-API-Secret');

        if (!$apiKey || !$apiSecret) {
            return response()->json([
                'error' => 'Missing authentication credentials'
            ], 401);
        }

        // クライアントを検証
        $client = ApiClient::where('key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (!$client || !hash_equals($client->secret, $apiSecret)) {
            return response()->json([
                'error' => 'Invalid credentials'
            ], 401);
        }

        // スコープをチェック
        if (!empty($scopes) && !$client->hasScopes($scopes)) {
            return response()->json([
                'error' => 'Insufficient permissions'
            ], 403);
        }

        // リクエストレート制限
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
        // レート制限のロジック
        return true;
    }
}
```

## 🛡️ セキュリティヘッダー

### CORS設定

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

### セキュリティミドルウェア

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

## 📝 ドキュメント生成の設定

### グローバル認証設定

```php
// config/spectrum.php
'authentication' => [
    // デフォルトの認証方式
    'default' => 'bearer',
    
    // 認証が不要なルート
    'exclude_patterns' => [
        'api/health',
        'api/status',
        'api/auth/login',
        'api/auth/register',
    ],
    
    // グローバルセキュリティ要件
    'global_security' => true,
    
    // カスタムセキュリティスキーマ
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

### ルート別の認証設定

```php
// 複数の認証方式を使用
Route::middleware(['auth:sanctum', 'verified', '2fa'])->group(function () {
    Route::post('/sensitive-action', [SecureController::class, 'action']);
});

// 認証オプション（いずれか）
Route::middleware('auth:sanctum,api')->group(function () {
    Route::get('/flexible-endpoint', [FlexibleController::class, 'index']);
});
```

## 💡 ベストプラクティス

### 1. 環境別の設定

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

### 2. トークンの適切な管理

```php
class TokenService
{
    public function generateToken(User $user, string $name = 'api-token'): array
    {
        // 既存のトークンを削除（オプション）
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

### 3. レート制限

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

### 4. 認証エラーのハンドリング

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

## 🔍 トラブルシューティング

### 認証が検出されない

1. **ミドルウェアマッピングを確認**
   ```php
   // config/spectrum.php
   'middleware_map' => [
       'your-custom-auth' => 'bearer',
   ],
   ```

2. **ルートミドルウェアを確認**
   ```bash
   php artisan route:list --path=api
   ```

3. **キャッシュをクリア**
   ```bash
   php artisan config:clear
   php artisan route:clear
   ```

### 複数の認証方式

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
    // ルートごとに異なる認証を使用
    'route_overrides' => [
        'api/external/*' => 'apiKey',
        'api/internal/*' => 'bearer',
    ],
],
```

## 📚 関連ドキュメント

- [セキュリティ](./security.md) - セキュリティのベストプラクティス
- [ミドルウェア](./middleware.md) - カスタムミドルウェアの作成
- [設定リファレンス](./config-reference.md) - 認証設定の詳細