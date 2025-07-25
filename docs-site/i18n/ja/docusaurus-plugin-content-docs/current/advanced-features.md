# 高度な機能ガイド

Laravel Spectrumの高度な機能と使い方について説明します。

## 🎯 条件付きバリデーション

### HTTPメソッドベースの条件

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ];

        // POSTリクエスト（作成）の場合
        if ($this->isMethod('POST')) {
            $rules['password'] = 'required|string|min:8|confirmed';
            $rules['email'] .= '|unique:users,email';
            $rules['terms_accepted'] = 'required|accepted';
        }
        
        // PUT/PATCHリクエスト（更新）の場合
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['password'] = 'sometimes|nullable|string|min:8|confirmed';
            $rules['email'] .= '|unique:users,email,' . $this->route('user');
            $rules['current_password'] = 'required_with:password|current_password';
        }

        return $rules;
    }
}
```

生成されるOpenAPIスキーマ：

```json
{
  "oneOf": [
    {
      "title": "Create User (POST)",
      "type": "object",
      "required": ["name", "email", "password", "password_confirmation", "terms_accepted"],
      "properties": {
        "name": { "type": "string", "maxLength": 255 },
        "email": { "type": "string", "format": "email" },
        "password": { "type": "string", "minLength": 8 },
        "password_confirmation": { "type": "string", "minLength": 8 },
        "terms_accepted": { "type": "boolean" }
      }
    },
    {
      "title": "Update User (PUT/PATCH)",
      "type": "object",
      "required": ["name", "email"],
      "properties": {
        "name": { "type": "string", "maxLength": 255 },
        "email": { "type": "string", "format": "email" },
        "password": { "type": "string", "minLength": 8, "nullable": true },
        "password_confirmation": { "type": "string", "minLength": 8 },
        "current_password": { "type": "string" }
      }
    }
  ]
}
```

### 動的な条件付きルール

```php
public function rules()
{
    return [
        'account_type' => 'required|in:personal,business,enterprise',
        
        // personalアカウントの場合の必須フィールド
        'first_name' => 'required_if:account_type,personal|string|max:100',
        'last_name' => 'required_if:account_type,personal|string|max:100',
        'date_of_birth' => 'required_if:account_type,personal|date|before:today',
        
        // businessアカウントの場合の必須フィールド
        'company_name' => 'required_if:account_type,business,enterprise|string|max:255',
        'tax_id' => 'required_if:account_type,business,enterprise|string|regex:/^[A-Z0-9\-]+$/',
        'business_type' => 'required_if:account_type,business|in:llc,corporation,partnership',
        
        // enterpriseアカウントの場合の追加フィールド
        'contract_type' => 'required_if:account_type,enterprise|in:annual,multi-year',
        'sla_level' => 'required_if:account_type,enterprise|in:standard,premium,custom',
        
        // 条件付きネストデータ
        'billing' => 'required_unless:account_type,personal|array',
        'billing.address' => 'required_unless:account_type,personal|string',
        'billing.city' => 'required_unless:account_type,personal|string',
        'billing.postal_code' => 'required_unless:account_type,personal|string',
        
        // 複雑な条件
        'payment_method' => Rule::requiredIf(function () {
            return in_array($this->account_type, ['business', 'enterprise']) 
                   && $this->billing_cycle === 'monthly';
        }),
    ];
}
```

## 🔄 動的レスポンス構造

### 権限ベースのレスポンス

```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at->toISOString(),
        ];

        // 自分のプロフィールの場合
        if ($request->user()?->id === $this->id) {
            $data = array_merge($data, [
                'phone' => $this->phone,
                'address' => $this->address,
                'preferences' => $this->preferences,
                'two_factor_enabled' => $this->two_factor_enabled,
            ]);
        }

        // 管理者の場合
        if ($request->user()?->isAdmin()) {
            $data = array_merge($data, [
                'internal_notes' => $this->internal_notes,
                'account_status' => $this->account_status,
                'last_login_at' => $this->last_login_at?->toISOString(),
                'login_count' => $this->login_count,
                'ip_addresses' => $this->ip_addresses,
            ]);
        }

        // リレーションの条件付き読み込み
        if ($this->relationLoaded('posts') && $request->user()->can('view-posts', $this)) {
            $data['posts'] = PostResource::collection($this->posts);
            $data['posts_count'] = $this->posts->count();
        }

        return $data;
    }

    /**
     * 追加のメタデータ
     */
    public function with($request)
    {
        $with = [];

        if ($request->user()?->isAdmin()) {
            $with['meta'] = [
                'permissions' => $this->getAllPermissions()->pluck('name'),
                'roles' => $this->roles->pluck('name'),
            ];
        }

        return $with;
    }
}
```

## 🎨 カスタムバリデーションルール

### 複雑なバリデーションルール

```php
namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class ValidBusinessHours implements Rule, DataAwareRule
{
    protected array $data = [];
    
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }
    
    public function passes($attribute, $value)
    {
        if (!is_array($value)) {
            return false;
        }
        
        // 営業時間の形式をチェック
        foreach ($value as $day => $hours) {
            if (!in_array($day, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])) {
                return false;
            }
            
            if (!isset($hours['open']) || !isset($hours['close'])) {
                continue; // 休業日
            }
            
            // 時間形式の検証
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $hours['open']) ||
                !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $hours['close'])) {
                return false;
            }
            
            // 開店時間が閉店時間より前か
            if (strtotime($hours['open']) >= strtotime($hours['close'])) {
                return false;
            }
        }
        
        return true;
    }
    
    public function message()
    {
        return 'The :attribute must contain valid business hours.';
    }
}

// 使用例
public function rules()
{
    return [
        'business_hours' => ['required', new ValidBusinessHours],
        'timezone' => 'required|timezone',
    ];
}
```

### データベース依存のバリデーション

```php
namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Product;

class SufficientStock implements Rule
{
    protected $productId;
    protected $availableStock;
    
    public function __construct($productId)
    {
        $this->productId = $productId;
    }
    
    public function passes($attribute, $value)
    {
        $product = Product::find($this->productId);
        
        if (!$product) {
            return false;
        }
        
        $this->availableStock = $product->stock;
        
        return $value <= $this->availableStock;
    }
    
    public function message()
    {
        return "The requested quantity exceeds available stock. Only {$this->availableStock} items available.";
    }
}
```

## 🔍 高度なクエリパラメータ

### 複雑なフィルタリング

```php
namespace App\Http\Controllers;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();
        
        // 価格範囲フィルター
        if ($request->has('price_min') || $request->has('price_max')) {
            $query->whereBetween('price', [
                $request->input('price_min', 0),
                $request->input('price_max', PHP_INT_MAX)
            ]);
        }
        
        // 複数カテゴリーフィルター
        if ($request->has('categories')) {
            $categories = is_array($request->categories) 
                ? $request->categories 
                : explode(',', $request->categories);
            
            $query->whereHas('categories', function ($q) use ($categories) {
                $q->whereIn('slug', $categories);
            });
        }
        
        // 属性フィルター（動的）
        if ($request->has('attributes')) {
            foreach ($request->attributes as $key => $value) {
                $query->whereHas('attributes', function ($q) use ($key, $value) {
                    $q->where('key', $key)->where('value', $value);
                });
            }
        }
        
        // ソート（複数フィールド対応）
        if ($request->has('sort')) {
            $sortFields = explode(',', $request->sort);
            foreach ($sortFields as $field) {
                $direction = 'asc';
                if (str_starts_with($field, '-')) {
                    $direction = 'desc';
                    $field = substr($field, 1);
                }
                
                if (in_array($field, ['name', 'price', 'created_at', 'popularity'])) {
                    $query->orderBy($field, $direction);
                }
            }
        }
        
        // 検索（複数フィールド）
        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%")
                  ->orWhereHas('tags', function ($q) use ($search) {
                      $q->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }
        
        // インクルード（関連データ）
        if ($includes = $request->input('include')) {
            $allowedIncludes = ['category', 'brand', 'reviews', 'variants'];
            $includes = array_intersect(explode(',', $includes), $allowedIncludes);
            $query->with($includes);
        }
        
        return ProductResource::collection(
            $query->paginate($request->input('per_page', 20))
        );
    }
}
```

## 📦 バッチ処理とバルク操作

### バルク作成/更新

```php
namespace App\Http\Controllers\Api;

use App\Http\Requests\BulkUserRequest;
use App\Jobs\ProcessBulkUsers;

class BulkUserController extends Controller
{
    public function store(BulkUserRequest $request)
    {
        $validated = $request->validated();
        
        // 非同期処理の場合
        if ($request->input('async', false)) {
            $job = ProcessBulkUsers::dispatch($validated['users'])
                ->onQueue('bulk-operations');
                
            return response()->json([
                'message' => 'Bulk operation queued',
                'job_id' => $job->getJobId(),
                'status_url' => route('bulk.status', $job->getJobId()),
            ], 202);
        }
        
        // 同期処理
        $results = [];
        DB::beginTransaction();
        
        try {
            foreach ($validated['users'] as $index => $userData) {
                try {
                    $user = User::create($userData);
                    $results[] = [
                        'index' => $index,
                        'status' => 'success',
                        'data' => new UserResource($user),
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'index' => $index,
                        'status' => 'error',
                        'error' => $e->getMessage(),
                    ];
                }
            }
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        
        return response()->json([
            'total' => count($validated['users']),
            'successful' => collect($results)->where('status', 'success')->count(),
            'failed' => collect($results)->where('status', 'error')->count(),
            'results' => $results,
        ]);
    }
}

// BulkUserRequest
public function rules()
{
    return [
        'users' => 'required|array|min:1|max:100',
        'users.*.name' => 'required|string|max:255',
        'users.*.email' => 'required|email|distinct|unique:users,email',
        'users.*.role' => 'required|in:admin,user,guest',
        'async' => 'boolean',
        'validate_only' => 'boolean',
    ];
}
```

## 🔐 高度な認証パターン

### マルチテナント認証

```php
namespace App\Http\Middleware;

use Closure;
use App\Models\Tenant;

class TenantAuthentication
{
    public function handle($request, Closure $next)
    {
        // ヘッダーからテナントIDを取得
        $tenantId = $request->header('X-Tenant-ID');
        
        if (!$tenantId) {
            // サブドメインから取得を試みる
            $host = $request->getHost();
            $subdomain = explode('.', $host)[0];
            
            $tenant = Tenant::where('subdomain', $subdomain)->first();
        } else {
            $tenant = Tenant::find($tenantId);
        }
        
        if (!$tenant || !$tenant->is_active) {
            return response()->json([
                'error' => 'Invalid or inactive tenant'
            ], 401);
        }
        
        // テナントコンテキストを設定
        app()->instance('tenant', $tenant);
        
        // データベース接続を切り替え
        config(['database.default' => 'tenant']);
        config(['database.connections.tenant.database' => $tenant->database]);
        
        // キャッシュとセッションのプレフィックスを設定
        config(['cache.prefix' => $tenant->id]);
        config(['session.cookie' => 'session_' . $tenant->id]);
        
        return $next($request);
    }
}
```

## 🎯 Webhookとイベント

### Webhook送信システム

```php
namespace App\Services;

use App\Models\Webhook;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Http;

class WebhookService
{
    public function dispatch(string $event, array $payload)
    {
        $webhooks = Webhook::active()
            ->whereJsonContains('events', $event)
            ->get();
            
        foreach ($webhooks as $webhook) {
            $this->sendWebhook($webhook, $event, $payload);
        }
    }
    
    protected function sendWebhook(Webhook $webhook, string $event, array $payload)
    {
        $webhookEvent = WebhookEvent::create([
            'webhook_id' => $webhook->id,
            'event' => $event,
            'payload' => $payload,
            'status' => 'pending',
        ]);
        
        try {
            $signature = $this->generateSignature($webhook->secret, $payload);
            
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Webhook-Event' => $event,
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Timestamp' => now()->timestamp,
                ])
                ->retry(3, 1000)
                ->post($webhook->url, $payload);
                
            $webhookEvent->update([
                'status' => 'delivered',
                'response_code' => $response->status(),
                'response_body' => $response->body(),
                'delivered_at' => now(),
            ]);
            
        } catch (\Exception $e) {
            $webhookEvent->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'failed_at' => now(),
            ]);
            
            // リトライロジック
            if ($webhookEvent->attempts < 5) {
                RetryWebhook::dispatch($webhookEvent)
                    ->delay(now()->addMinutes(pow(2, $webhookEvent->attempts)));
            }
        }
    }
    
    protected function generateSignature(string $secret, array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), $secret);
    }
}
```

## 🔄 リアルタイムAPI

### Server-Sent Events (SSE)

```php
namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\StreamedResponse;

class RealtimeController extends Controller
{
    public function stream(Request $request)
    {
        return new StreamedResponse(function () use ($request) {
            // ヘッダー設定
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');
            
            $lastEventId = $request->header('Last-Event-ID', 0);
            
            while (true) {
                // 新しいイベントを取得
                $events = $this->getNewEvents($lastEventId);
                
                foreach ($events as $event) {
                    echo "id: {$event->id}\n";
                    echo "event: {$event->type}\n";
                    echo "data: " . json_encode($event->data) . "\n\n";
                    
                    $lastEventId = $event->id;
                }
                
                ob_flush();
                flush();
                
                // 接続が切断されたかチェック
                if (connection_aborted()) {
                    break;
                }
                
                sleep(1);
            }
        });
    }
}
```

## 📚 関連ドキュメント

- [条件付きバリデーション](./conditional-validation.md) - 詳細なバリデーションパターン
- [APIリファレンス](./api-reference.md) - 高度なAPI使用方法
- [カスタマイズ](./customization.md) - 機能の拡張方法