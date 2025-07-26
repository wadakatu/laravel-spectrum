---
id: response-analysis
title: レスポンス解析ガイド
sidebar_label: レスポンス解析ガイド
---

# レスポンス解析ガイド

Laravel Spectrumは、APIリソース、Fractalトランスフォーマー、ページネーション、そして様々なレスポンスパターンを自動的に解析してドキュメント化します。

## 📦 APIリソース

### 基本的なAPIリソース

```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'avatar_url' => $this->avatar_url,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

生成されるOpenAPIスキーマ：

```json
{
  "type": "object",
  "properties": {
    "id": { "type": "integer" },
    "name": { "type": "string" },
    "email": { "type": "string", "format": "email" },
    "role": { "type": "string" },
    "avatar_url": { "type": "string", "format": "uri" },
    "created_at": { "type": "string", "format": "date-time" },
    "updated_at": { "type": "string", "format": "date-time" }
  }
}
```

### ネストされたリソース

```php
class PostResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'author' => new UserResource($this->author),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'tags' => TagResource::collection($this->tags),
            'meta' => [
                'views_count' => $this->views_count,
                'likes_count' => $this->likes_count,
                'is_featured' => $this->is_featured,
            ],
        ];
    }
}
```

### 条件付き属性

```php
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            
            // 認証ユーザーのみに表示
            'phone' => $this->when($request->user(), $this->phone),
            
            // 管理者のみに表示
            'internal_notes' => $this->when(
                $request->user()?->isAdmin(),
                $this->internal_notes
            ),
            
            // 条件付きでマージ
            $this->mergeWhen($request->user()?->id === $this->id, [
                'private_settings' => $this->private_settings,
                'notification_preferences' => $this->notification_preferences,
            ]),
            
            // リレーションがロードされている場合のみ
            'posts' => PostResource::collection($this->whenLoaded('posts')),
            'posts_count' => $this->whenCounted('posts'),
        ];
    }
}
```

### メタデータとラッピング

```php
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

    public function with($request)
    {
        return [
            'meta' => [
                'version' => '1.0',
                'api_version' => config('app.api_version'),
                'generated_at' => now()->toISOString(),
            ],
        ];
    }

    // カスタムラッピング
    public static $wrap = 'user';
}
```

## 🔄 コレクションリソース

### 基本的なコレクション

```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_active' => $this->collection->where('is_active', true)->count(),
                'total_inactive' => $this->collection->where('is_active', false)->count(),
            ],
        ];
    }

    public function with($request)
    {
        return [
            'links' => [
                'self' => route('users.index'),
            ],
            'meta' => [
                'generated_at' => now()->toISOString(),
            ],
        ];
    }
}
```

### ページネーション付きコレクション

```php
// コントローラー
public function index(Request $request)
{
    $users = User::query()
        ->when($request->search, function ($query, $search) {
            $query->where('name', 'like', "%{$search}%");
        })
        ->paginate($request->input('per_page', 15));

    return UserResource::collection($users);
}
```

自動的に検出されるページネーション構造：

```json
{
  "data": [...],
  "links": {
    "first": "http://api.example.com/users?page=1",
    "last": "http://api.example.com/users?page=10",
    "prev": null,
    "next": "http://api.example.com/users?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "path": "http://api.example.com/users",
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
```

## 🦴 Fractalトランスフォーマー

### 基本的なトランスフォーマー

```php
namespace App\Transformers;

use App\Models\Product;
use League\Fractal\TransformerAbstract;

class ProductTransformer extends TransformerAbstract
{
    protected array $availableIncludes = ['category', 'reviews'];
    protected array $defaultIncludes = ['brand'];

    public function transform(Product $product)
    {
        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => [
                'amount' => (float) $product->price,
                'currency' => $product->currency,
                'formatted' => $product->formatted_price,
            ],
            'in_stock' => (bool) $product->in_stock,
            'created_at' => $product->created_at->toIso8601String(),
        ];
    }

    public function includeCategory(Product $product)
    {
        return $this->item($product->category, new CategoryTransformer);
    }

    public function includeReviews(Product $product)
    {
        return $this->collection($product->reviews, new ReviewTransformer);
    }

    public function includeBrand(Product $product)
    {
        return $this->item($product->brand, new BrandTransformer);
    }
}
```

### Fractalマネージャーの使用

```php
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;

class ProductController extends Controller
{
    private Manager $fractal;

    public function __construct(Manager $fractal)
    {
        $this->fractal = $fractal;
        $this->fractal->setSerializer(new \League\Fractal\Serializer\ArraySerializer());
    }

    public function index(Request $request)
    {
        $paginator = Product::paginate(20);
        $products = new Collection($paginator->items(), new ProductTransformer);
        $products->setPaginator(new IlluminatePaginatorAdapter($paginator));

        if ($request->has('include')) {
            $this->fractal->parseIncludes($request->include);
        }

        return $this->fractal->createData($products)->toArray();
    }

    public function show($id)
    {
        $product = Product::findOrFail($id);
        $resource = new Item($product, new ProductTransformer);

        return $this->fractal->createData($resource)->toArray();
    }
}
```

## 📊 複雑なレスポンスパターン

### 多態的リレーション

```php
class ActivityResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'description' => $this->description,
            'subject' => $this->whenMorphLoaded('subject', function () {
                return match ($this->subject_type) {
                    Post::class => new PostResource($this->subject),
                    Comment::class => new CommentResource($this->subject),
                    User::class => new UserResource($this->subject),
                    default => null,
                };
            }),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

### 再帰的な構造

```php
class CategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'products_count' => $this->whenCounted('products'),
        ];
    }
}
```

### カスタムレスポンスフォーマット

```php
class ApiResponse
{
    public static function success($data, string $message = '', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ], $code);
    }

    public static function error(string $message, array $errors = [], int $code = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => now()->toISOString(),
        ], $code);
    }

    public static function paginated($paginator, $resource)
    {
        return response()->json([
            'success' => true,
            'data' => $resource::collection($paginator),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
```

## 🎯 型推論とスキーマ生成

### モデル属性からの型推論

Laravel Spectrumは以下から型を推論します：

1. **データベーススキーマ**
    - マイグレーションファイル
    - モデルの`$casts`プロパティ

2. **モデルのキャスト**
   ```php
   protected $casts = [
       'is_active' => 'boolean',
       'metadata' => 'array',
       'published_at' => 'datetime',
       'price' => 'decimal:2',
       'settings' => 'json',
   ];
   ```

3. **アクセサとミューテタ**
   ```php
   // 属性のキャスト
   protected function price(): Attribute
   {
       return Attribute::make(
           get: fn ($value) => $value / 100,
           set: fn ($value) => $value * 100,
       );
   }
   ```

## 💡 ベストプラクティス

### 1. 一貫性のあるレスポンス構造

```php
// すべてのリソースで共通の基底クラス
abstract class BaseResource extends JsonResource
{
    protected function formatTimestamp($timestamp): ?string
    {
        return $timestamp?->toISOString();
    }

    protected function formatMoney($amount, string $currency = 'JPY'): array
    {
        return [
            'amount' => $amount,
            'currency' => $currency,
            'formatted' => number_format($amount) . ' ' . $currency,
        ];
    }
}
```

### 2. 明示的な型指定

```php
public function toArray($request)
{
    return [
        'id' => (int) $this->id,  // 明示的にintにキャスト
        'name' => (string) $this->name,
        'price' => (float) $this->price,
        'is_active' => (bool) $this->is_active,
        'tags' => $this->tags->pluck('name')->toArray(), // 配列として返す
    ];
}
```

### 3. リレーションの適切な処理

```php
public function toArray($request)
{
    return [
        // N+1問題を避ける
        'comments_count' => $this->whenCounted('comments'),
        
        // ロードされている場合のみ含める
        'comments' => CommentResource::collection(
            $this->whenLoaded('comments')
        ),
        
        // デフォルト値を提供
        'author' => $this->whenLoaded(
            'author',
            fn() => new UserResource($this->author),
            fn() => null
        ),
    ];
}
```

### 4. エラーレスポンスの統一

```php
trait ApiResponses
{
    protected function successResponse($data, string $message = '', int $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function errorResponse(string $message, int $code = 400, array $errors = [])
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    protected function validationErrorResponse($validator)
    {
        return $this->errorResponse(
            'Validation failed',
            422,
            $validator->errors()->toArray()
        );
    }
}
```

## 🔍 トラブルシューティング

### レスポンス構造が検出されない

1. **リターン文を確認**
   ```php
   // ✅ 検出される
   return new UserResource($user);
   return UserResource::collection($users);
   
   // ❌ 検出されない可能性
   return response()->json(new UserResource($user));
   ```

2. **リソースクラスの名前空間**
   ```php
   use App\Http\Resources\UserResource; // 正しい名前空間
   ```

3. **キャッシュのクリア**
   ```bash
   php artisan spectrum:cache:clear --schemas
   ```

### ネストが深すぎる警告

```php
// config/spectrum.php
'analysis' => [
    'max_depth' => 5, // ネストの最大深さを調整
],
```

## 📚 関連ドキュメント

- [APIリソース](./api-resources.md) - Laravelリソースの詳細
- [ページネーション](./pagination.md) - ページネーション対応
- [エラーハンドリング](./error-handling.md) - エラーレスポンス