---
id: api-resources
title: APIリソースガイド
sidebar_label: APIリソースガイド
---

# APIリソースガイド

Laravel Spectrumは、Laravel API Resourcesを完全に解析し、レスポンススキーマを自動生成します。

## 🎯 基本的な使い方

### シンプルなリソース

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
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

生成されるOpenAPIスキーマ：

```json
{
  "UserResource": {
    "type": "object",
    "properties": {
      "id": {
        "type": "integer"
      },
      "name": {
        "type": "string"
      },
      "email": {
        "type": "string",
        "format": "email"
      },
      "created_at": {
        "type": "string",
        "format": "date-time"
      },
      "updated_at": {
        "type": "string",
        "format": "date-time"
      }
    },
    "required": ["id", "name", "email", "created_at", "updated_at"]
  }
}
```

## 📦 コレクション

### リソースコレクション

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
                'total_users' => $this->collection->count(),
                'active_users' => $this->collection->where('is_active', true)->count(),
            ],
        ];
    }
}
```

### ページネーション付きコレクション

```php
// コントローラー
public function index()
{
    $users = User::paginate(20);
    return UserResource::collection($users);
}
```

自動的に以下のスキーマが生成されます：

```json
{
  "UserResourceCollection": {
    "type": "object",
    "properties": {
      "data": {
        "type": "array",
        "items": {
          "$ref": "#/components/schemas/UserResource"
        }
      },
      "links": {
        "type": "object",
        "properties": {
          "first": { "type": "string", "nullable": true },
          "last": { "type": "string", "nullable": true },
          "prev": { "type": "string", "nullable": true },
          "next": { "type": "string", "nullable": true }
        }
      },
      "meta": {
        "type": "object",
        "properties": {
          "current_page": { "type": "integer" },
          "from": { "type": "integer", "nullable": true },
          "last_page": { "type": "integer" },
          "path": { "type": "string" },
          "per_page": { "type": "integer" },
          "to": { "type": "integer", "nullable": true },
          "total": { "type": "integer" }
        }
      }
    }
  }
}
```

## 🔄 条件付きフィールド

### whenLoaded()の使用

```php
class PostResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'author' => new UserResource($this->whenLoaded('author')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->pluck('name');
            }),
        ];
    }
}
```

### when()の使用

```php
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // 管理者のみに表示
            'phone' => $this->when($request->user()->isAdmin(), $this->phone),
            'address' => $this->when($request->user()->isAdmin(), $this->address),
            // 自分のプロフィールの場合のみ
            'private_notes' => $this->when(
                $request->user()->id === $this->id, 
                $this->private_notes
            ),
        ];
    }
}
```

## 🎨 ネストしたリソース

### 複雑なネスト構造

```php
class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'customer' => new CustomerResource($this->customer),
            'items' => OrderItemResource::collection($this->items),
            'shipping' => [
                'method' => $this->shipping_method,
                'address' => new AddressResource($this->shippingAddress),
                'tracking_number' => $this->tracking_number,
            ],
            'payment' => [
                'method' => $this->payment_method,
                'status' => $this->payment_status,
                'transaction_id' => $this->when(
                    $request->user()->can('view-payment-details'),
                    $this->transaction_id
                ),
            ],
            'totals' => [
                'subtotal' => $this->subtotal,
                'tax' => $this->tax,
                'shipping' => $this->shipping_cost,
                'total' => $this->total,
            ],
        ];
    }
}
```

### リソース内でのリソース使用

```php
class BlogPostResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->when($request->route()->getName() === 'posts.show', $this->content),
            'author' => new AuthorResource($this->author),
            'category' => new CategoryResource($this->category),
            'tags' => TagResource::collection($this->tags),
            'featured_image' => $this->when($this->featured_image, function () {
                return new ImageResource($this->featured_image);
            }),
            'related_posts' => $this->when(
                $request->route()->getName() === 'posts.show',
                PostSummaryResource::collection($this->relatedPosts())
            ),
        ];
    }
}
```

## 🔧 カスタマイズ

### メタデータの追加

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
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}
```

### ラップキーのカスタマイズ

```php
class UserResource extends JsonResource
{
    public static $wrap = 'user';
    
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}

// レスポンス例：
{
    "user": {
        "id": 1,
        "name": "John Doe"
    }
}
```

### ラップの無効化

```php
class UserResource extends JsonResource
{
    public static $wrap = null;
    
    // または AppServiceProvider で全体的に無効化
    public function boot()
    {
        JsonResource::withoutWrapping();
    }
}
```

## 🚀 高度な使用例

### ポリモーフィックリレーション

```php
class ActivityResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'description' => $this->description,
            'subject' => $this->when($this->subject, function () {
                return $this->morphToResource($this->subject);
            }),
            'causer' => new UserResource($this->causer),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
    
    private function morphToResource($model)
    {
        $resourceMap = [
            'App\Models\Post' => PostResource::class,
            'App\Models\Comment' => CommentResource::class,
            'App\Models\User' => UserResource::class,
        ];
        
        $resourceClass = $resourceMap[get_class($model)] ?? null;
        
        return $resourceClass ? new $resourceClass($model) : null;
    }
}
```

### 動的フィールド

```php
class DynamicResource extends JsonResource
{
    public function toArray($request)
    {
        $fields = $request->input('fields', []);
        $data = [
            'id' => $this->id,
            'type' => $this->getMorphClass(),
        ];
        
        // 要求されたフィールドのみを含める
        if (empty($fields)) {
            return array_merge($data, $this->resource->toArray());
        }
        
        foreach ($fields as $field) {
            if ($this->resource->hasAttribute($field)) {
                $data[$field] = $this->resource->$field;
            }
        }
        
        return $data;
    }
}
```

## 💡 ベストプラクティス

### 1. 一貫性のある構造

```php
// 基底リソースクラスを作成
abstract class BaseResource extends JsonResource
{
    protected function baseFields()
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}

// 継承して使用
class UserResource extends BaseResource
{
    public function toArray($request)
    {
        return array_merge($this->baseFields(), [
            'name' => $this->name,
            'email' => $this->email,
        ]);
    }
}
```

### 2. 型の一貫性

```php
class ProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'price' => (float) $this->price,
            'in_stock' => (bool) $this->in_stock,
            'quantity' => (int) $this->quantity,
            'tags' => $this->tags->pluck('name')->toArray(),
        ];
    }
}
```

### 3. Null値の処理

```php
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'bio' => $this->bio ?? '',
            'avatar_url' => $this->avatar_url ?? null,
            'last_login_at' => $this->last_login_at?->toISOString(),
        ];
    }
}
```

## 🔍 Spectrumでの検出

Laravel Spectrumは以下を自動的に検出します：

1. **フィールドタイプ** - プロパティの型を推測
2. **必須フィールド** - null許容性を分析
3. **ネスト構造** - 他のリソースへの参照を検出
4. **コレクション** - 配列とコレクションを識別
5. **条件付きフィールド** - `when()`と`whenLoaded()`を理解

## 📚 関連ドキュメント

- [レスポンス解析](./response-analysis.md) - レスポンス構造の詳細
- [ページネーション](./pagination.md) - ページネーション対応
- [高度な機能](./advanced-features.md) - 動的レスポンス構造