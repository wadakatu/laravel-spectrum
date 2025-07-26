---
id: middleware
title: ミドルウェアガイド
sidebar_label: ミドルウェアガイド
---

# ミドルウェアガイド

Laravel Spectrumは、APIルートに適用されているミドルウェアを自動的に検出し、認証要件やその他の制約をOpenAPIドキュメントに反映します。

## 🎯 ミドルウェアの検出

### 認証ミドルウェア

Laravel Spectrumは以下の認証ミドルウェアを自動的に検出します：

```php
// Sanctum認証
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/api/profile', [ProfileController::class, 'show']);
    Route::put('/api/profile', [ProfileController::class, 'update']);
});

// JWT認証
Route::middleware('auth:api')->group(function () {
    Route::apiResource('api/posts', PostController::class);
});

// 複数のガード
Route::middleware(['auth:sanctum,api'])->group(function () {
    Route::post('/api/admin/users', [AdminController::class, 'createUser']);
});
```

生成されるOpenAPIスキーマ：

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

## 🔐 カスタムミドルウェア

### APIバージョニング

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
        
        // レスポンスにバージョン情報を追加
        $response = $next($request);
        $response->header('X-API-Version', $version);
        
        return $response;
    }
}

// ルートでの使用
Route::middleware('api.version:v2')->group(function () {
    Route::get('/api/v2/users', [UserV2Controller::class, 'index']);
});
```

### レート制限

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

## 🎨 ミドルウェアグループ

### API用ミドルウェアグループ

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

### 条件付きミドルウェア

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ConditionalAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        // 特定のエンドポイントでは認証をスキップ
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
        
        // その他のエンドポイントでは認証が必要
        if (!$request->user()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        
        return $next($request);
    }
}
```

## 🔄 ミドルウェアパラメータ

### 動的パラメータ

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

// 使用例
Route::middleware('role:admin,editor')->group(function () {
    Route::post('/api/posts', [PostController::class, 'store']);
    Route::put('/api/posts/{post}', [PostController::class, 'update']);
    Route::delete('/api/posts/{post}', [PostController::class, 'destroy']);
});
```

### 複数条件のミドルウェア

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
        
        // プランのチェック
        if ($plan && !$user->subscribedToPlan($plan)) {
            return response()->json([
                'error' => 'Subscription required',
                'required_plan' => $plan,
                'current_plan' => $user->subscription?->plan
            ], 403);
        }
        
        // 機能のチェック
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

## 🚀 ミドルウェアの優先順位

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

## 📊 ミドルウェアでのロギング

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
        
        // リクエストログ
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
        
        // レスポンスログ
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
        // センシティブなヘッダーを除外
        $sensitive = ['authorization', 'cookie', 'x-api-key'];
        
        return collect($headers)->except($sensitive)->toArray();
    }
    
    private function filterBody(array $body)
    {
        // パスワードなどのセンシティブなデータを除外
        $sensitive = ['password', 'password_confirmation', 'credit_card'];
        
        return collect($body)->except($sensitive)->toArray();
    }
}
```

## 🔧 テスト用ミドルウェア

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

// テスト用ルート
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

## 💡 ベストプラクティス

### 1. ミドルウェアの単一責任
各ミドルウェアは1つの責任のみを持つようにする：
- 認証チェック
- 認可チェック
- レート制限
- ロギング
- リクエスト変換

### 2. エラーレスポンスの一貫性
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

### 3. パフォーマンスの考慮
- 重い処理は避ける
- キャッシュを活用する
- 必要な場合のみDBクエリを実行

## 📚 関連ドキュメント

- [認証設定](./authentication.md) - 認証の詳細設定
- [エラーハンドリング](./error-handling.md) - エラーレスポンスの管理
- [セキュリティ](./security.md) - セキュリティのベストプラクティス