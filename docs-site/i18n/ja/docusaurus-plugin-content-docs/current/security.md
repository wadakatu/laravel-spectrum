# セキュリティガイド

Laravel SpectrumとAPIのセキュリティベストプラクティスについて説明します。

## 🔐 認証と認可

### 認証の実装

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

// ルートの保護
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class);
});
```

### 多要素認証（2FA）

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
        
        // シークレットキーの生成
        $secretKey = $this->google2fa->generateSecretKey();
        
        // QRコードURL生成
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secretKey
        );
        
        // 一時的に保存
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
                'message' => '認証コードが正しくありません',
            ], 422);
        }
        
        $user->two_factor_enabled = true;
        $user->save();
        
        // リカバリーコードの生成
        $recoveryCodes = $this->generateRecoveryCodes();
        $user->two_factor_recovery_codes = encrypt($recoveryCodes);
        $user->save();
        
        return response()->json([
            'message' => '2要素認証が有効になりました',
            'recovery_codes' => $recoveryCodes,
        ]);
    }
}
```

## 🛡️ APIセキュリティ

### レート制限

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'api' => [
        'throttle:60,1', // 1分間に60リクエストまで
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
];

// カスタムレート制限
Route::middleware('throttle:custom')->group(function () {
    Route::post('/api/expensive-operation', ExpensiveController::class);
});

// RateLimiterの設定
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
                    'message' => 'リクエスト制限に達しました',
                    'retry_after' => $headers['Retry-After'],
                ], 429);
            });
    });
}
```

### CORS設定

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

## 🔍 入力検証とサニタイゼーション

### 厳格な入力検証

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
                'regex:/^[a-zA-Z\s\-]+$/', // 英字、スペース、ハイフンのみ
            ],
            'email' => [
                'required',
                'email:rfc,dns', // DNS検証も実行
                'unique:users,email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'min:12', // 最小12文字
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', // 複雑性要件
                'confirmed',
                'not_compromised', // Have I Been Pwned APIチェック
            ],
            'phone' => [
                'nullable',
                'string',
                'regex:/^\+?[1-9]\d{1,14}$/', // E.164形式
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
            'password.regex' => 'パスワードは大文字、小文字、数字、特殊文字を含む必要があります',
            'password.not_compromised' => 'このパスワードは漏洩データベースに含まれています',
        ];
    }
    
    protected function prepareForValidation()
    {
        // 入力のサニタイゼーション
        $this->merge([
            'name' => strip_tags($this->name),
            'email' => strtolower(trim($this->email)),
            'phone' => preg_replace('/[^0-9+]/', '', $this->phone),
        ]);
    }
}
```

### SQLインジェクション対策

```php
// 悪い例 - SQLインジェクションの脆弱性
$users = DB::select("SELECT * FROM users WHERE email = '{$request->email}'");

// 良い例 - パラメータバインディング
$users = DB::select('SELECT * FROM users WHERE email = ?', [$request->email]);

// Eloquentの使用（推奨）
$users = User::where('email', $request->email)->get();

// 複雑なクエリの場合
$results = DB::table('orders')
    ->whereRaw('created_at > ?', [$date])
    ->whereRaw('total > ?', [$amount])
    ->get();
```

## 🚨 セキュリティヘッダー

### セキュリティミドルウェア

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
        
        // Strict Transport Security (HTTPS環境のみ)
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        
        return $response;
    }
}
```

## 🔑 APIキー管理

### APIキーの実装

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
            'rate_limit' => 1000, // デフォルト: 1時間あたり1000リクエスト
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

### APIキー認証ミドルウェア

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
                'error' => 'APIキーが必要です',
            ], 401);
        }
        
        $apiKey = ApiKey::where('key', $key)->first();
        
        if (!$apiKey || !$apiKey->validateSecret($secret)) {
            return response()->json([
                'error' => '無効なAPIキーです',
            ], 401);
        }
        
        if ($apiKey->isExpired()) {
            return response()->json([
                'error' => 'APIキーの有効期限が切れています',
            ], 401);
        }
        
        if ($permission && !$apiKey->hasPermission($permission)) {
            return response()->json([
                'error' => 'この操作を実行する権限がありません',
                'required_permission' => $permission,
            ], 403);
        }
        
        // 使用状況の更新
        $apiKey->update([
            'last_used_at' => now(),
            'last_ip_address' => $request->ip(),
        ]);
        
        $request->merge(['api_key' => $apiKey]);
        
        return $next($request);
    }
}
```

## 📊 監査ログ

### アクティビティログの実装

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

## 🔒 データ暗号化

### 機密データの暗号化

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
    
    // カスタム暗号化
    public function setCustomDataAttribute($value)
    {
        $this->attributes['custom_data'] = encrypt($value, false);
    }
    
    public function getCustomDataAttribute($value)
    {
        return $value ? decrypt($value, false) : null;
    }
    
    // ハッシュ化（検索可能な暗号化）
    public function setSsn Attribute($value)
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

## 💡 セキュリティのベストプラクティス

### 1. 最小権限の原則
- ユーザーに必要最小限の権限のみを付与
- APIキーには具体的な権限を設定
- 管理者権限は厳格に管理

### 2. セキュリティアップデート
```bash
# 定期的な依存関係の更新
composer update --with-all-dependencies
npm audit fix

# セキュリティ脆弱性のチェック
composer audit
npm audit
```

### 3. 環境変数の管理
```php
// .env.example
APP_DEBUG=false
APP_ENV=production

# 本番環境では必ずHTTPS
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=strict
```

## 📚 関連ドキュメント

- [認証設定](./authentication.md) - 認証システムの詳細
- [ミドルウェア](./middleware.md) - セキュリティミドルウェア
- [エラーハンドリング](./error-handling.md) - セキュアなエラー処理